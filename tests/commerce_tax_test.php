<?php
/**
 * P8-M3 — Commerce tax rules tests (default rate + country override at checkout).
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Commerce tax tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';

$dataDir = sys_get_temp_dir() . '/birkly-commerce-tax-' . getmypid();
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
require_once $root . '/plugins/commerce/includes/CommerceSchema.php';
require_once $root . '/plugins/commerce/includes/EventRegistrationStore.php';
require_once $root . '/plugins/commerce/includes/DigitalDeliveryStore.php';
require_once $root . '/plugins/commerce/includes/PostPurchaseRouter.php';

class TestTaxCredentialStore extends PaymentsCredentialStore
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
$store = new TestTaxCredentialStore($payDir);
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

// Shippable product so the checkout can carry a destination country for tax.
// No shipping_zones.json is written, so shipping stays 0 and only tax moves the total.
$indexer = new CatalogIndexer($catalogDir);
$indexer->indexEntry([
    'id' => 'prod-tax',
    'slug' => 'taxable-product',
    'title' => 'Taxable Product',
    'commerce' => [
        'price_cents' => 10000,
        'currency' => 'eur',
        'billing' => 'one_time',
        'quantity_available' => 50,
        'fulfillment' => 'ship',
        'sku' => 'TAX',
    ],
], 'products');

if (!isset($_SESSION)) {
    $_SESSION = [];
}

$catalog = new CommerceCatalog($indexer);

// --- 1. Disabled tax -> zero ---
$catalog->saveTaxRules(['enabled' => false, 'default_rate' => 19, 'country_rates' => []]);
$lines = $catalog->buildCartLines([['collection' => 'products', 'entry_id' => 'prod-tax', 'quantity' => 1]])['lines'];
$taxOff = $catalog->calculateTax($lines, ['country' => 'DE']);
test_assert('tax disabled -> 0 cents', $taxOff['tax_cents'] === 0);
test_assert('tax disabled -> 0 rate', $taxOff['rate'] === 0.0);

// --- 2. Default rate applies when no country match ---
$catalog->saveTaxRules([
    'enabled' => true,
    'default_rate' => 10,
    'country_rates' => [['country' => 'DE', 'rate' => 19]],
]);
$taxDefault = $catalog->calculateTax($lines, ['country' => 'US']);
test_assert('default rate resolved for US', $taxDefault['rate'] === 10.0);
test_assert('default tax = 1000 on 10000 base', $taxDefault['tax_cents'] === 1000, json_encode($taxDefault));

// --- 3. Country override takes precedence ---
$taxDE = $catalog->calculateTax($lines, ['country' => 'de']);
test_assert('country override rate (case-insensitive)', $taxDE['rate'] === 19.0);
test_assert('country tax = 1900 on 10000 base', $taxDE['tax_cents'] === 1900, json_encode($taxDE));

// --- 4. resolveTaxRate direct ---
test_assert('resolveTaxRate DE = 19', $catalog->resolveTaxRate('DE') === 19.0);
test_assert('resolveTaxRate FR = default 10', $catalog->resolveTaxRate('FR') === 10.0);

// --- 5. Checkout persists tax on the order ---
$orderStore = new CommerceOrderStore($ordersDir);
$cart = new CommerceCartSession();
$checkout = new CommerceCheckoutService($orderStore, $catalog, $cart, $indexer);

$cart->addItem('products', 'prod-tax', 2); // subtotal 20000
$result = $checkout->checkout('buyer@example.com', '', '', [
    'shipping_address' => ['line1' => '1 Tax St', 'city' => 'Berlin', 'country' => 'DE'],
]);
test_assert('checkout ok', ($result['ok'] ?? false) === true, $result['error'] ?? json_encode($result));

$order = $orderStore->get($result['order_id']);
test_assert('order persists tax_cents (19% of 20000 = 3800)', ($order['tax_cents'] ?? 0) === 3800, json_encode($order['tax_cents'] ?? null));
test_assert('order persists tax_rate', (float) ($order['tax_rate'] ?? 0) === 19.0);
test_assert('order persists tax_country DE', ($order['tax_country'] ?? '') === 'DE');
test_assert('order total includes tax (20000 + 3800)', ($order['total_cents'] ?? 0) === 23800, json_encode($order['total_cents'] ?? null));

// --- 6. Persistence round-trips through JSON ---
$reloaded = $catalog->loadTaxRules();
test_assert('reloaded tax enabled', $reloaded['enabled'] === true);
test_assert('reloaded default_rate', $reloaded['default_rate'] === 10.0);
test_assert('reloaded country override', ($reloaded['country_rates'][0]['country'] ?? '') === 'DE');

exit(test_finish('Commerce tax tests'));
