<?php
/**
 * P6.2 — Shopping Cart checkout + payment_shelf consumer tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Shopping Cart shelf tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/payments/init.php';
require_once $root . '/plugins/shopping_cart/init.php';

$dataDir = sys_get_temp_dir() . '/birkly-cart-shelf-' . getmypid();
$ordersDir = $dataDir . '/orders';
$contentDir = $dataDir . '/content';
@mkdir($ordersDir, 0755, true);
@mkdir($contentDir . '/collections/shop_products/entries', 0755, true);

file_put_contents($contentDir . '/collections/shop_products/collection.json', json_encode([
    'id' => 'col-shop_products', 'slug' => 'shop_products', 'name' => 'Shop Products',
], JSON_PRETTY_PRINT));
file_put_contents($contentDir . '/collections/shop_products/entries/prod-1.json', json_encode([
    'id' => 'prod-1', 'title' => 'Test Product', 'price' => 19.99, 'currency' => 'usd',
    'sku' => 'TEST', 'status' => 'published',
], JSON_PRETTY_PRINT));

require_once $root . '/plugins/payments/includes/ProviderInterface.php';
require_once $root . '/plugins/payments/includes/AbstractProvider.php';
require_once $root . '/plugins/payments/includes/CredentialStore.php';
require_once $root . '/plugins/payments/includes/ActivityLog.php';
require_once $root . '/plugins/payments/includes/ProviderResolver.php';
require_once $root . '/plugins/payments/includes/WebhookRouter.php';
require_once $root . '/plugins/payments/includes/PaymentShelf.php';
require_once $root . '/plugins/payments/includes/providers/StripeProvider.php';

class TestCartPaymentsStore extends PaymentsCredentialStore
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
$store = new TestCartPaymentsStore($payDir);
$activityLog = new PaymentsActivityLog($payDir);
$resolver = new PaymentsProviderResolver($store);
$resolver->register(new StripeProvider());
$shelf = new PaymentShelf($resolver, $store, $activityLog);
$store->saveConnection([
    'provider' => 'stripe', 'label' => 'Mock', 'mode' => 'test', 'enabled' => true,
    'credentials' => ['mock' => true, 'secret_key' => 'sk_test'],
]);

require_once $root . '/core/plugins/services.php';
require_once $root . '/core/plugins/capabilities.php';

global $haru_plugin_services;
$haru_plugin_services['payment_shelf'] = [
    'service' => $shelf,
    'plugin_id' => 'payments',
    'version' => '1.0.0',
    'capability' => 'access_payment_shelf',
    'scope' => 'global',
];

require_once $root . '/core/plugins/permissions.php';
grant_plugin_capability('shopping_cart', 'access_payment_shelf', 'global');

require_once $root . '/plugins/shopping_cart/includes/ProductCatalog.php';
require_once $root . '/plugins/shopping_cart/includes/OrderStore.php';
require_once $root . '/plugins/shopping_cart/includes/CartSession.php';
require_once $root . '/plugins/shopping_cart/includes/CheckoutService.php';

class TestProductCatalog extends ShoppingCartProductCatalog
{
    private string $contentRoot;
    public function __construct(string $contentRoot) { $this->contentRoot = $contentRoot; }
    public function getProduct(string $productId): ?array
    {
        require_once dirname(__DIR__) . '/core/storage/StorageFactory.php';
        require_once dirname(__DIR__) . '/core/public_read.php';
        $storage = StorageFactory::createStorage($this->contentRoot);
        $result = birkly_public_get_entry($storage, 'shop_products', '', $productId);
        return ($result['status'] ?? '') === 'ok' ? ($result['data']['entry'] ?? null) : null;
    }
    public function listPublished(): array
    {
        require_once dirname(__DIR__) . '/core/storage/StorageFactory.php';
        require_once dirname(__DIR__) . '/core/public_read.php';
        $storage = StorageFactory::createStorage($this->contentRoot);
        $result = birkly_public_list_entries($storage, 'shop_products');
        return ($result['status'] ?? '') === 'ok' ? ($result['data']['entries'] ?? []) : [];
    }
}

shopping_cart_bootstrap();

if (!isset($_SESSION)) {
    $_SESSION = [];
}

$orderStore = new ShoppingCartOrderStore($ordersDir);
$cart = new ShoppingCartSession();
$catalog = new TestProductCatalog($contentDir);
$checkout = new ShoppingCartCheckoutService($orderStore, $catalog, $cart);

$cart->addItem('prod-1', 2);
$result = $checkout->checkout('buyer@example.com');
test_assert('checkout ok', ($result['ok'] ?? false) === true, $result['error'] ?? json_encode($result));
test_assert('order_id returned', !empty($result['order_id']));
test_assert('payment_id returned', !empty($result['payment_id']));

$order = $orderStore->get($result['order_id']);
test_assert('order pending after checkout', ($order['status'] ?? '') === 'pending');
test_assert('order has payment_id', ($order['payment_id'] ?? '') === $result['payment_id']);

$paid = $checkout->markPaidByPaymentId($result['payment_id']);
test_assert('order marked paid', ($paid['status'] ?? '') === 'paid');
test_assert('cart cleared after paid', $cart->count() === 0);

$log = $activityLog->list([], 50);
$found = false;
foreach ($log as $entry) {
    if (($entry['source_plugin'] ?? '') === 'shopping_cart' && ($entry['source_ref'] ?? '') === $result['order_id']) {
        $found = true;
        break;
    }
}
test_assert('activity log source_plugin shopping_cart', $found);

exit(test_finish('Shopping Cart shelf tests'));
