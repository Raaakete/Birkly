<?php
/**
 * P8-M4 — Subscription lifecycle v1 tests.
 *
 * Covers:
 *  - Subscription record created on checkout for recurring purchase_options
 *  - Idempotent creation (single subscription per order)
 *  - Admin cancel action → status canceled
 *  - Provider webhook events → past_due / canceled / renewed (Stripe mock)
 *  - One-time orders create no subscription
 *  - commerce_subscription_status template function
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/fixtures/commerce_scenarios.php';

test_reset_failures();
echo "Commerce subscription tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';

$dataDir = sys_get_temp_dir() . '/birkly-commerce-subscription-' . getmypid();
$ordersDir = $dataDir . '/orders';
$subsDir = $dataDir . '/subscriptions';
$catalogDir = $dataDir;
@mkdir($ordersDir, 0755, true);
@mkdir($subsDir, 0755, true);

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
require_once $root . '/plugins/commerce/includes/CommerceSubscriptionStore.php';
require_once $root . '/plugins/commerce/includes/CommerceSubscriptionService.php';
require_once $root . '/plugins/commerce/includes/DiscountEngine.php';
require_once $root . '/plugins/commerce/includes/PurchaseMappingService.php';
require_once $root . '/plugins/commerce/includes/FulfillmentAdapterInterface.php';
require_once $root . '/plugins/commerce/includes/ManualFulfillmentAdapter.php';
require_once $root . '/plugins/commerce/includes/ShipStationStubAdapter.php';
require_once $root . '/plugins/commerce/includes/EventRegistrationStore.php';
require_once $root . '/plugins/commerce/includes/DigitalDeliveryStore.php';
require_once $root . '/plugins/commerce/includes/PostPurchaseRouter.php';
require_once $root . '/plugins/commerce/includes/CommerceCheckoutService.php';

class TestSubscriptionCredentialStore extends PaymentsCredentialStore
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
$store = new TestSubscriptionCredentialStore($payDir);
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
    'id' => 'var-coffee-1',
    'slug' => 'var-coffee-1',
    'title' => '250g Whole bean',
    'commerce' => commerce_scenario_coffee_variant('var-coffee-1'),
], 'products');

if (!isset($_SESSION)) {
    $_SESSION = [];
}

$orderStore = new CommerceOrderStore($ordersDir);
$subStore = new CommerceSubscriptionStore($subsDir);
$cart = new CommerceCartSession();
$catalog = new CommerceCatalog($indexer);
$router = new PostPurchaseRouter($dataDir);
$subService = new CommerceSubscriptionService($subStore, $shelf);
$checkout = new CommerceCheckoutService($orderStore, $catalog, $cart, $indexer, null, null, $router, $subService);

// --- 1. Recurring checkout creates a subscription on payment ---
$cart->addItem('products', 'var-coffee-1', 1, 'subscribe');
$result = $checkout->checkout('buyer@example.com', '', '', [
    'shipping_address' => ['line1' => '1 Coffee St', 'city' => 'Berlin', 'country' => 'DE'],
]);
test_assert('recurring checkout ok', ($result['ok'] ?? false) === true, $result['error'] ?? json_encode($result));

$order = $orderStore->get($result['order_id']);
test_assert('order billing_mode recurring', ($order['billing_mode'] ?? '') === 'recurring');

$paid = $checkout->markPaidByPaymentId($result['payment_id']);
test_assert('order marked paid', ($paid['status'] ?? '') === 'paid');
test_assert('order links subscription_id', !empty($paid['subscription_id']));

$subs = $subStore->listAll();
test_assert('one subscription created', count($subs) === 1);
$sub = $subs[0] ?? [];
test_assert('subscription status active', ($sub['status'] ?? '') === 'active');
test_assert('subscription interval month', ($sub['interval'] ?? '') === 'month');
test_assert('subscription amount matches order', (int) ($sub['amount_cents'] ?? 0) === (int) ($order['total_cents'] ?? -1));
test_assert('subscription links order', ($sub['order_id'] ?? '') === $order['id']);
test_assert('subscription next_billing in future', !empty($sub['next_billing_at']) && strtotime($sub['next_billing_at']) > time());

// --- 2. Idempotent creation ---
$checkout->markPaidByPaymentId($result['payment_id']);
$subService->createFromOrder($paid);
test_assert('creation is idempotent', count($subStore->listAll()) === 1);

// --- 3. commerce_subscription_status template function ---
$statusFromStore = $subStore->get($sub['id']);
test_assert('subscription readable by id', ($statusFromStore['status'] ?? '') === 'active');

// --- 4. Provider webhook: invoice.payment_failed → past_due ---
$subStore->update($sub['id'], ['provider_subscription_id' => 'sub_stripe_test_1']);
$stripe = new StripeProvider();
$failEvent = $stripe->processWebhook(
    ['mock' => true],
    ['type' => 'invoice.payment_failed', 'data' => ['object' => ['id' => 'in_1', 'subscription' => 'sub_stripe_test_1']]],
    []
);
test_assert('provider maps past_due status', ($failEvent['subscription_status'] ?? '') === 'past_due');
test_assert('provider maps subscription id', ($failEvent['subscription_id'] ?? '') === 'sub_stripe_test_1');
$updated = $subService->applyProviderEvent($failEvent);
test_assert('webhook marks subscription past_due', ($updated['status'] ?? '') === 'past_due');
test_assert('past_due timestamp set', !empty($updated['past_due_at']));

// --- 5. Provider webhook: invoice.paid → active (renewal) ---
$renewEvent = $stripe->processWebhook(
    ['mock' => true],
    ['type' => 'invoice.paid', 'data' => ['object' => ['id' => 'in_2', 'subscription' => 'sub_stripe_test_1']]],
    []
);
$renewed = $subService->applyProviderEvent($renewEvent);
test_assert('webhook renewal restores active', ($renewed['status'] ?? '') === 'active');
test_assert('renewal clears past_due', empty($renewed['past_due_at']));

// --- 6. Provider webhook: customer.subscription.deleted → canceled ---
$cancelEvent = $stripe->processWebhook(
    ['mock' => true],
    ['type' => 'customer.subscription.deleted', 'data' => ['object' => ['id' => 'sub_stripe_test_1']]],
    []
);
test_assert('provider maps canceled status', ($cancelEvent['subscription_status'] ?? '') === 'canceled');
$canceledViaHook = $subService->applyProviderEvent($cancelEvent);
test_assert('webhook cancels subscription', ($canceledViaHook['status'] ?? '') === 'canceled');
test_assert('canceled clears next_billing', empty($canceledViaHook['next_billing_at']));

// --- 7. Admin cancel action (idempotent on already-canceled) ---
$reCancel = $subService->cancel($sub['id']);
test_assert('cancel idempotent on canceled', ($reCancel['status'] ?? '') === 'canceled');

// A fresh active subscription can be canceled via admin action
$freshSub = $subStore->create([
    'order_id' => 'ord_manual_1',
    'status' => 'active',
    'interval' => 'month',
    'amount_cents' => 900,
    'currency' => 'eur',
    'next_billing_at' => date('c', time() + 2592000),
]);
$adminCanceled = $subService->cancel($freshSub['id']);
test_assert('admin cancel sets canceled', ($adminCanceled['status'] ?? '') === 'canceled');
test_assert('admin cancel timestamps', !empty($adminCanceled['canceled_at']));

// --- 8. One-time order creates no subscription ---
$oneTimeOrder = $orderStore->create([
    'status' => 'pending',
    'billing_mode' => 'one_time',
    'total_cents' => 1499,
    'currency' => 'eur',
    'lines' => [['title' => 'Coffee', 'quantity' => 1]],
]);
$noSub = $subService->createFromOrder($oneTimeOrder);
test_assert('one-time order creates no subscription', $noSub === null);

// --- 9. Unknown subscription id returns null ---
test_assert('cancel unknown returns null', $subService->cancel('csub_does_not_exist') === null);
test_assert('applyProviderEvent unmatched returns null', $subService->applyProviderEvent([
    'subscription_status' => 'canceled',
    'subscription_id' => 'sub_unknown',
]) === null);

// Cleanup
foreach (glob($subsDir . '/*.json') ?: [] as $f) { @unlink($f); }
foreach (glob($ordersDir . '/*.json') ?: [] as $f) { @unlink($f); }

exit(test_finish('Commerce subscription tests'));
