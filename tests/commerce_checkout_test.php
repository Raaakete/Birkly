<?php
/**
 * P6.4-M2 — Commerce checkout + shelf tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Commerce checkout tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';

$dataDir = sys_get_temp_dir() . '/birkly-commerce-checkout-' . getmypid();
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
require_once $root . '/plugins/commerce/includes/CatalogIndexer.php';
require_once $root . '/plugins/commerce/includes/CommerceCatalog.php';
require_once $root . '/plugins/commerce/includes/CommerceCartSession.php';
require_once $root . '/plugins/commerce/includes/CommerceOrderStore.php';
require_once $root . '/plugins/commerce/includes/DiscountEngine.php';
require_once $root . '/plugins/commerce/includes/PurchaseMappingService.php';
require_once $root . '/plugins/commerce/includes/FulfillmentAdapterInterface.php';
require_once $root . '/plugins/commerce/includes/ManualFulfillmentAdapter.php';
require_once $root . '/plugins/commerce/includes/ShipStationStubAdapter.php';
require_once $root . '/plugins/commerce/includes/CommerceCheckoutService.php';

class TestCheckoutCredentialStore extends PaymentsCredentialStore
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
$store = new TestCheckoutCredentialStore($payDir);
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

$indexer = new CatalogIndexer($catalogDir);
$indexer->indexEntry([
    'id' => 'prod-1',
    'slug' => 'test-product',
    'title' => 'Test Product',
    'commerce' => [
        'price_cents' => 1999,
        'currency' => 'usd',
        'billing' => 'one_time',
        'quantity_available' => 5,
        'sku' => 'TEST',
    ],
], 'products');

if (!isset($_SESSION)) {
    $_SESSION = [];
}

$orderStore = new CommerceOrderStore($ordersDir);
$cart = new CommerceCartSession();
$catalog = new CommerceCatalog($indexer);
$checkout = new CommerceCheckoutService($orderStore, $catalog, $cart, $indexer);

$cart->addItem('products', 'prod-1', 2);
$result = $checkout->checkout('buyer@example.com');
test_assert('checkout ok', ($result['ok'] ?? false) === true, $result['error'] ?? json_encode($result));
test_assert('order_id returned', !empty($result['order_id']));
test_assert('payment_id returned', !empty($result['payment_id']));

$order = $orderStore->get($result['order_id']);
test_assert('order pending after checkout', ($order['status'] ?? '') === 'pending');
test_assert('order lines have base amounts', !empty($order['lines'][0]['base_amount_cents']));

$indexBefore = $indexer->loadIndex();
$qtyBefore = $indexBefore['entries']['products:prod-1']['quantity_available'];

$paid = $checkout->markPaidByPaymentId($result['payment_id']);
test_assert('order marked paid', ($paid['status'] ?? '') === 'paid');
test_assert('cart cleared after paid', $cart->count() === 0);

$indexAfter = $indexer->loadIndex();
$qtyAfter = $indexAfter['entries']['products:prod-1']['quantity_available'];
test_assert('quantity decremented on paid', $qtyAfter === $qtyBefore - 2);

exit(test_finish('Commerce checkout tests'));
