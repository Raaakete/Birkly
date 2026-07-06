<?php
/**
 * P6.1 — Webhook router tests (signature verify + idempotency).
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Payments webhook tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/includes/ProviderInterface.php';
require_once $root . '/plugins/commerce/includes/AbstractProvider.php';
require_once $root . '/plugins/commerce/includes/CredentialStore.php';
require_once $root . '/plugins/commerce/includes/ActivityLog.php';
require_once $root . '/plugins/commerce/includes/ProviderResolver.php';
require_once $root . '/plugins/commerce/includes/WebhookRouter.php';
require_once $root . '/plugins/commerce/includes/providers/StripeProvider.php';
require_once $root . '/plugins/commerce/includes/providers/MollieProvider.php';

$dataDir = sys_get_temp_dir() . '/birkly-payments-webhook-' . getmypid();
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}
foreach (['/connections', '/activity', '/webhooks'] as $sub) {
    if (!is_dir($dataDir . $sub)) {
        mkdir($dataDir . $sub, 0755, true);
    }
}
file_put_contents($dataDir . '/activity/log.json', '[]');
file_put_contents($dataDir . '/webhooks/idempotency.json', '{}');
file_put_contents($dataDir . '/encryption_key.txt', base64_encode(hash('sha256', 'webhook-test', true)));

class WebhookTestCredentialStore extends PaymentsCredentialStore
{
    public function __construct(private string $testDir)
    {
        $ref = new ReflectionClass(PaymentsCredentialStore::class);
        $thisRef = $ref->newInstanceWithoutConstructor();
        $baseProp = $ref->getProperty('baseDir');
        $baseProp->setAccessible(true);
        $baseProp->setValue($this, $testDir);
        $keyProp = $ref->getProperty('encryptionKey');
        $keyProp->setAccessible(true);
        $keyProp->setValue($this, trim(file_get_contents($testDir . '/encryption_key.txt')));
    }

    public function getDataDir(): string
    {
        return $this->testDir;
    }
}

$store = new WebhookTestCredentialStore($dataDir);
$activityLog = new PaymentsActivityLog($dataDir);
$resolver = new PaymentsProviderResolver($store);
$resolver->register(new StripeProvider());
$resolver->register(new MollieProvider());

$store->saveConnection([
    'provider' => 'stripe',
    'label' => 'Stripe Webhook Test',
    'enabled' => true,
    'credentials' => ['mock' => true, 'webhook_secret' => 'whsec_test'],
]);
$store->saveConnection([
    'provider' => 'mollie',
    'label' => 'Mollie Webhook Test',
    'enabled' => true,
    'credentials' => ['mock' => true],
]);

$router = new PaymentsWebhookRouter($resolver, $store, $activityLog);
$router->clearIdempotencyLog();

// Stripe mock webhook with valid signature header
$stripePayload = json_encode([
    'id' => 'evt_test_webhook_001',
    'type' => 'payment_intent.succeeded',
    'data' => ['object' => ['id' => 'pi_test_001', 'amount' => 2900, 'currency' => 'usd']],
]);
$stripeHeaders = ['X-Mock-Signature' => 'valid'];

$result = $router->handle('stripe', $stripePayload, $stripeHeaders);
test_assert('stripe webhook ok', ($result['ok'] ?? false) === true);
test_assert('stripe webhook not duplicate', empty($result['duplicate']));

// Idempotency: same event again
$result2 = $router->handle('stripe', $stripePayload, $stripeHeaders);
test_assert('stripe webhook duplicate detected', !empty($result2['duplicate']));
test_assert('router hasProcessed event', $router->hasProcessed('evt_test_webhook_001'));

// Invalid signature rejected
$badResult = $router->handle('stripe', $stripePayload, ['X-Mock-Signature' => 'invalid']);
test_assert('invalid signature rejected', ($badResult['ok'] ?? true) === false);
test_assert('invalid signature 401', ($badResult['status'] ?? 0) === 401);

// Mollie mock webhook
$molliePayload = json_encode(['id' => 'tr_mock_mollie_1', 'status' => 'paid']);
$mollieResult = $router->handle('mollie', $molliePayload, ['X-Mock-Signature' => 'valid']);
test_assert('mollie webhook ok', ($mollieResult['ok'] ?? false) === true);

// Activity log has webhook entries without secrets
$webhookEntries = $activityLog->list(['type' => 'webhook'], 10);
test_assert('activity log has webhook entries', count($webhookEntries) >= 2);
$hasSecret = false;
foreach ($webhookEntries as $entry) {
    $encoded = json_encode($entry);
    if (strpos($encoded, 'whsec_') !== false || strpos($encoded, 'sk_') !== false) {
        $hasSecret = true;
    }
}
test_assert('activity log has no webhook secrets', $hasSecret === false);

// Unknown provider
$unknown = $router->handle('unknown_provider', '{}', []);
test_assert('unknown provider 404', ($unknown['status'] ?? 0) === 404);

// Cleanup
array_map('unlink', glob($dataDir . '/connections/*.json') ?: []);
@unlink($dataDir . '/activity/log.json');
@unlink($dataDir . '/webhooks/idempotency.json');
@unlink($dataDir . '/encryption_key.txt');
@rmdir($dataDir . '/connections');
@rmdir($dataDir . '/activity');
@rmdir($dataDir . '/webhooks');
@rmdir($dataDir);

exit(test_finish('Payments webhook tests'));
