<?php
/**
 * P11-M2 — Dunning tests (failed payment, recovery, retry).
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Commerce dunning tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';

$dataDir = sys_get_temp_dir() . '/birkly-commerce-dunning-' . getmypid();
$subsDir = $dataDir . '/subscriptions';
@mkdir($subsDir, 0755, true);

require_once $root . '/plugins/commerce/includes/ProviderInterface.php';
require_once $root . '/plugins/commerce/includes/AbstractProvider.php';
require_once $root . '/plugins/commerce/includes/CredentialStore.php';
require_once $root . '/plugins/commerce/includes/ActivityLog.php';
require_once $root . '/plugins/commerce/includes/ProviderResolver.php';
require_once $root . '/plugins/commerce/includes/PaymentShelf.php';
require_once $root . '/plugins/commerce/includes/providers/StripeProvider.php';
require_once $root . '/plugins/commerce/includes/CommerceSubscriptionStore.php';
require_once $root . '/plugins/commerce/includes/CommerceSubscriptionService.php';
require_once $root . '/plugins/commerce/includes/CommerceDunningNotifier.php';

class TestDunningCredentialStore extends PaymentsCredentialStore
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
$store = new TestDunningCredentialStore($payDir);
$activityLog = new PaymentsActivityLog($payDir);
$resolver = new PaymentsProviderResolver($store);
$resolver->register(new StripeProvider());
$shelf = new PaymentShelf($resolver, $store, $activityLog);
$store->saveConnection([
    'provider' => 'stripe', 'label' => 'Mock', 'mode' => 'test', 'enabled' => true,
    'credentials' => ['mock' => true, 'secret_key' => 'sk_test'],
]);

$automationEvents = [];
if (!function_exists('enqueue_automation')) {
    function enqueue_automation(string $event, array $context): void
    {
        global $automationEvents;
        $automationEvents[] = ['event' => $event, 'context' => $context];
    }
}

$subStore = new CommerceSubscriptionStore($subsDir);
$subService = new CommerceSubscriptionService($subStore, $shelf, new CommerceDunningNotifier());

$sub = $subStore->create([
    'order_id' => 'ord_dun_1',
    'status' => 'active',
    'provider' => 'stripe',
    'provider_subscription_id' => 'sub_dunning_1',
    'customer_email' => 'member@example.com',
    'interval' => 'month',
    'amount_cents' => 900,
    'currency' => 'eur',
    'next_billing_at' => date('c', time() + 2592000),
    'lines' => [],
]);

$stripe = new StripeProvider();
$failEvent = $stripe->processWebhook(
    ['mock' => true],
    [
        'type' => 'invoice.payment_failed',
        'data' => ['object' => [
            'id' => 'in_fail_1',
            'subscription' => 'sub_dunning_1',
            'attempt_count' => 2,
        ]],
    ],
    []
);

test_assert('stripe maps payment_failed flag', !empty($failEvent['payment_failed']));
test_assert('stripe maps attempt_count', (int) ($failEvent['attempt_count'] ?? 0) === 2);
test_assert('stripe maps invoice_id', ($failEvent['invoice_id'] ?? '') === 'in_fail_1');

$automationEvents = [];
$pastDue = $subService->applyProviderEvent($failEvent);
test_assert('subscription marked past_due', ($pastDue['status'] ?? '') === 'past_due');
test_assert('dunning attempt_count stored', (int) (($pastDue['dunning']['attempt_count'] ?? 0)) === 2);
test_assert('dunning last_failed_at set', !empty($pastDue['dunning']['last_failed_at']));
test_assert('dunning invoice id stored', ($pastDue['dunning']['last_invoice_id'] ?? '') === 'in_fail_1');

$failedFired = false;
foreach ($automationEvents as $evt) {
    if (($evt['event'] ?? '') === 'commerce_subscription_payment_failed') {
        $failedFired = true;
    }
}
test_assert('payment_failed automation fired', $failedFired);

// Second failure without attempt_count increments counter
$failEvent2 = $stripe->processWebhook(
    ['mock' => true],
    [
        'type' => 'invoice.payment_failed',
        'data' => ['object' => [
            'id' => 'in_fail_2',
            'subscription' => 'sub_dunning_1',
        ]],
    ],
    []
);
$again = $subService->applyProviderEvent($failEvent2);
test_assert('attempt_count increments', (int) (($again['dunning']['attempt_count'] ?? 0)) === 3);

// Recovery via successful invoice
$automationEvents = [];
$renewEvent = $stripe->processWebhook(
    ['mock' => true],
    [
        'type' => 'invoice.paid',
        'data' => ['object' => ['id' => 'in_paid_1', 'subscription' => 'sub_dunning_1']],
    ],
    []
);
$recovered = $subService->applyProviderEvent($renewEvent);
test_assert('recovery restores active', ($recovered['status'] ?? '') === 'active');
test_assert('recovery clears dunning', empty($recovered['dunning']));
test_assert('recovery clears past_due_at', empty($recovered['past_due_at']));

$recoveredFired = false;
foreach ($automationEvents as $evt) {
    if (($evt['event'] ?? '') === 'commerce_subscription_recovered') {
        $recoveredFired = true;
    }
}
test_assert('recovered automation fired', $recoveredFired);

// Manual retry on past_due subscription
$subStore->update($sub['id'], [
    'status' => 'past_due',
    'past_due_at' => date('c'),
    'dunning' => ['attempt_count' => 1, 'last_failed_at' => date('c'), 'last_invoice_id' => 'in_open_1'],
]);
$retry = $subService->retryPayment($sub['id']);
test_assert('manual retry succeeds', ($retry['ok'] ?? false) === true);
test_assert('retry clears past_due', ($retry['subscription']['status'] ?? '') === 'active');
test_assert('retry clears dunning metadata', empty($retry['subscription']['dunning']));

$badRetry = $subService->retryPayment($sub['id']);
test_assert('retry rejected when active', ($badRetry['ok'] ?? true) === false);

// Dunning notifier respects disabled setting
$mailSent = false;
$originalMail = null;
if (function_exists('mail')) {
    // Notifier uses mail(); we only verify it does not throw when disabled.
    $notifier = new CommerceDunningNotifier();
    $sent = $notifier->sendPaymentFailedEmail($sub);
    test_assert('dunning email skipped when disabled', $sent === false);
}

foreach (glob($subsDir . '/*.json') ?: [] as $f) { @unlink($f); }

exit(test_finish('Commerce dunning tests'));
