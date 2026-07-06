<?php
/**
 * P8-M3 — Commerce refund tests (full + partial via shelf) and order_paid
 * automation email triggers.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Commerce refund tests\n\n";

// Capture automation events fired during checkout / refund.
$GLOBALS['captured_automation'] = [];
if (!function_exists('enqueue_automation')) {
    function enqueue_automation($trigger_type, $context = [])
    {
        $GLOBALS['captured_automation'][] = ['trigger' => $trigger_type, 'context' => $context];
        return true;
    }
}

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';

$dataDir = sys_get_temp_dir() . '/birkly-commerce-refund-' . getmypid();
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

class TestRefundCredentialStore extends PaymentsCredentialStore
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
$store = new TestRefundCredentialStore($payDir);
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

// Digital product (deliver) so no shipping address is required; tax stays off.
$indexer = new CatalogIndexer($catalogDir);
$indexer->indexEntry([
    'id' => 'prod-ref',
    'slug' => 'refundable-product',
    'title' => 'Refundable Product',
    'commerce' => [
        'price_cents' => 10000,
        'currency' => 'eur',
        'billing' => 'one_time',
        'quantity_available' => 20,
        'fulfillment' => 'deliver',
        'sku' => 'REF',
    ],
], 'products');

if (!isset($_SESSION)) {
    $_SESSION = [];
}

$orderStore = new CommerceOrderStore($ordersDir);
$cart = new CommerceCartSession();
$catalog = new CommerceCatalog($indexer);
$checkout = new CommerceCheckoutService($orderStore, $catalog, $cart, $indexer);

$cart->addItem('products', 'prod-ref', 1); // total 10000, no tax, no shipping
$result = $checkout->checkout('buyer@example.com', '', '', []);
test_assert('checkout ok', ($result['ok'] ?? false) === true, $result['error'] ?? json_encode($result));

$orderId = $result['order_id'];
$paid = $checkout->markPaidByPaymentId($result['payment_id']);
test_assert('order marked paid', ($paid['status'] ?? '') === 'paid');
test_assert('order total 10000', ($paid['total_cents'] ?? 0) === 10000);

// --- Automation email triggers fired on order_paid, with order payload ---
$triggers = array_column($GLOBALS['captured_automation'], 'trigger');
test_assert('order_paid trigger fired', in_array('commerce_order_paid', $triggers, true));
test_assert('order_receipt trigger fired', in_array('commerce_order_receipt', $triggers, true));
test_assert('order_admin_notify trigger fired', in_array('commerce_order_admin_notify', $triggers, true));

$receipt = null;
foreach ($GLOBALS['captured_automation'] as $ev) {
    if ($ev['trigger'] === 'commerce_order_receipt') {
        $receipt = $ev['context'];
        break;
    }
}
test_assert('receipt payload has customer_email', ($receipt['customer_email'] ?? '') === 'buyer@example.com');
test_assert('receipt payload has line items', !empty($receipt['lines']) && ($receipt['lines'][0]['quantity'] ?? 0) === 1);
test_assert('receipt payload has total_cents', ($receipt['total_cents'] ?? 0) === 10000);

// --- Refund guards ---
$notFound = $checkout->refundOrder('does-not-exist', 100);
test_assert('refund unknown order rejected', ($notFound['ok'] ?? true) === false);

$overRefund = $checkout->refundOrder($orderId, 20000);
test_assert('refund over balance rejected', ($overRefund['ok'] ?? true) === false, json_encode($overRefund));

$zeroRefund = $checkout->refundOrder($orderId, 0);
test_assert('refund zero rejected', ($zeroRefund['ok'] ?? true) === false);

// --- Partial refund ---
$partial = $checkout->refundOrder($orderId, 4000, 'Damaged item');
test_assert('partial refund ok', ($partial['ok'] ?? false) === true, $partial['error'] ?? '');
$afterPartial = $orderStore->get($orderId);
test_assert('status partially_refunded', ($afterPartial['status'] ?? '') === 'partially_refunded');
test_assert('refunded_cents = 4000', ($afterPartial['refunded_cents'] ?? 0) === 4000);
test_assert('one refund record', count($afterPartial['refunds'] ?? []) === 1);
test_assert('refund reason stored', ($afterPartial['refunds'][0]['reason'] ?? '') === 'Damaged item');

// --- Second partial exceeding remaining is rejected (remaining now 6000) ---
$tooMuch = $checkout->refundOrder($orderId, 7000);
test_assert('second refund over remaining rejected', ($tooMuch['ok'] ?? true) === false);

// --- Full refund of remaining balance (null amount) ---
$full = $checkout->refundOrder($orderId, null);
test_assert('full refund ok', ($full['ok'] ?? false) === true, $full['error'] ?? '');
$afterFull = $orderStore->get($orderId);
test_assert('status refunded', ($afterFull['status'] ?? '') === 'refunded');
test_assert('refunded_cents = 10000', ($afterFull['refunded_cents'] ?? 0) === 10000);
test_assert('two refund records', count($afterFull['refunds'] ?? []) === 2);

// --- Nothing left to refund ---
$empty = $checkout->refundOrder($orderId, 100);
test_assert('refund after full rejected', ($empty['ok'] ?? true) === false);

// --- order_refunded automation fired ---
$refundTriggers = array_column($GLOBALS['captured_automation'], 'trigger');
test_assert('order_refunded trigger fired', in_array('commerce_order_refunded', $refundTriggers, true));

exit(test_finish('Commerce refund tests'));
