<?php
/**
 * P6.5-M2 — Commerce checkout v2 tests (purchase options, shipping, mixed cart block).
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/fixtures/commerce_scenarios.php';

test_reset_failures();
echo "Commerce checkout v2 tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';

$dataDir = sys_get_temp_dir() . '/birkly-commerce-checkout-v2-' . getmypid();
$ordersDir = $dataDir . '/orders';
$catalogDir = $dataDir;
@mkdir($ordersDir, 0755, true);

require_once $root . '/plugins/commerce/includes/ProviderInterface.php';
require_once $root . '/plugins/commerce/includes/AbstractProvider.php';
require_once $root . '/plugins/commerce/includes/CredentialStore.php';
require_once $root . '/plugins/commerce/includes/ActivityLog.php';
require_once $root . '/plugins/commerce/includes/ProviderResolver.php';
require_once $root . '/plugins/commerce/includes/WebhookRouter.php';
require_once $root . '/plugins/commerce/includes/PaymentShelf.php';
require_once $root . '/plugins/commerce/includes/providers/StripeProvider.php';
require_once $root . '/plugins/commerce/includes/CommerceSchema.php';
require_once $root . '/plugins/commerce/includes/CatalogIndexer.php';
require_once $root . '/plugins/commerce/includes/CommerceCatalog.php';
require_once $root . '/plugins/commerce/includes/CommerceCartSession.php';
require_once $root . '/plugins/commerce/includes/CommerceOrderStore.php';
require_once $root . '/plugins/commerce/includes/DiscountEngine.php';
require_once $root . '/plugins/commerce/includes/PurchaseMappingService.php';
require_once $root . '/plugins/commerce/includes/FulfillmentAdapterInterface.php';
require_once $root . '/plugins/commerce/includes/ManualFulfillmentAdapter.php';
require_once $root . '/plugins/commerce/includes/ShipStationStubAdapter.php';
require_once $root . '/plugins/commerce/includes/EventRegistrationStore.php';
require_once $root . '/plugins/commerce/includes/DigitalDeliveryStore.php';
require_once $root . '/plugins/commerce/includes/PostPurchaseRouter.php';
require_once $root . '/plugins/commerce/includes/CommerceCheckoutService.php';

class TestCheckoutV2CredentialStore extends PaymentsCredentialStore
{
    public function __construct(string $testDir)
    {
        foreach (['/connections', '/activity', '/webhooks'] as $sub) {
            if (!is_dir($testDir . $sub)) {
                mkdir($testDir . $sub, 0755, true);
            }
        }
        if (!is_file($testDir . '/activity/log.json')) {
            file_put_contents($testDir . '/activity/log.json', '[]');
        }
        $keyFile = $testDir . '/encryption_key.txt';
        if (!is_file($keyFile)) {
            file_put_contents($keyFile, base64_encode(hash('sha256', 'test-key', true)));
        }
        $ref = new ReflectionClass(PaymentsCredentialStore::class);
        $baseProp = $ref->getProperty('baseDir');
        $baseProp->setAccessible(true);
        $baseProp->setValue($this, $testDir);
        $keyProp = $ref->getProperty('encryptionKey');
        $keyProp->setAccessible(true);
        $keyProp->setValue($this, trim(file_get_contents($keyFile)));
    }
}

$payDir = $dataDir . '/payments';
$store = new TestCheckoutV2CredentialStore($payDir);
$activityLog = new PaymentsActivityLog($payDir);
$resolver = new PaymentsProviderResolver($store);
$resolver->register(new StripeProvider());
$shelf = new PaymentShelf($resolver, $store, $activityLog);
$store->saveConnection([
    'provider' => 'stripe', 'label' => 'Mock', 'mode' => 'test', 'enabled' => true,
    'credentials' => ['mock' => true, 'secret_key' => 'sk_test'],
]);

require_once $root . '/core/plugins/services.php';
global $haru_plugin_services;
$haru_plugin_services['payment_shelf'] = [
    'service' => $shelf,
    'plugin_id' => 'commerce',
    'version' => '1.0.0',
    'capability' => 'access_payment_shelf',
    'scope' => 'global',
];
$haru_plugin_services['commerce_shelf'] = $haru_plugin_services['payment_shelf'];

file_put_contents($dataDir . '/shipping_zones.json', json_encode([
    'zones' => [['id' => 'eu', 'name' => 'EU', 'countries' => ['*'], 'flat_rate_cents' => 499]],
], JSON_PRETTY_PRINT));

$indexer = new CatalogIndexer($catalogDir);
$indexer->indexEntry([
    'id' => 'var-coffee-1',
    'slug' => 'var-coffee-1',
    'title' => '250g Whole bean',
    'commerce' => commerce_scenario_coffee_variant('var-coffee-1'),
], 'products');

if (!isset($_SESSION)) {
    $_SESSION = [];
}

$orderStore = new CommerceOrderStore($ordersDir);
$cart = new CommerceCartSession();
$catalog = new CommerceCatalog($indexer);
$router = new PostPurchaseRouter($dataDir);
$checkout = new CommerceCheckoutService($orderStore, $catalog, $cart, $indexer, null, null, $router);

$cart->addItem('products', 'var-coffee-1', 1, 'subscribe');
$built = $catalog->buildCartLines($cart->getItems());
test_assert('subscribe option price 1299', ($built['lines'][0]['base_unit_cents'] ?? 0) === 1299);
test_assert('line snapshots purchase_option_id', ($built['lines'][0]['purchase_option_id'] ?? '') === 'subscribe');
test_assert('line snapshots fulfillment ship', ($built['lines'][0]['fulfillment'] ?? '') === 'ship');

$result = $checkout->checkout('buyer@example.com', '', '', [
    'shipping_address' => ['line1' => '1 Coffee St', 'city' => 'Berlin', 'country' => 'DE'],
]);
test_assert('checkout v2 ok', ($result['ok'] ?? false) === true, $result['error'] ?? json_encode($result));

$order = $orderStore->get($result['order_id']);
test_assert('order includes shipping', ($order['shipping_cents'] ?? 0) === 499);
test_assert('order line has commerce snapshot', !empty($order['lines'][0]['commerce']['purchase_options']));

$paid = $checkout->markPaidByPaymentId($result['payment_id']);
test_assert('order marked paid v2', ($paid['status'] ?? '') === 'paid');

$cart->clear();
$cart->addItem('products', 'var-coffee-1', 1, 'buy_once');
$cart->addItem('products', 'var-coffee-1', 1, 'subscribe');
$mixed = $catalog->buildCartLines($cart->getItems());
test_assert('mixed billing blocked', ($mixed['ok'] ?? true) === false);

exit(test_finish('Commerce checkout v2 tests'));
