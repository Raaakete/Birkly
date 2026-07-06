<?php
/**
 * P6.1 — Payment shelf + provider resolver tests (mock consumer).
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Payments shelf tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';

$dataDir = sys_get_temp_dir() . '/birkly-payments-shelf-' . getmypid();
if (is_dir($dataDir)) {
    array_map('unlink', glob($dataDir . '/connections/*.json') ?: []);
    @unlink($dataDir . '/activity/log.json');
    @unlink($dataDir . '/encryption_key.txt');
}

// Override data dir via custom store
require_once $root . '/plugins/commerce/includes/ProviderInterface.php';
require_once $root . '/plugins/commerce/includes/AbstractProvider.php';
require_once $root . '/plugins/commerce/includes/CredentialStore.php';
require_once $root . '/plugins/commerce/includes/ActivityLog.php';
require_once $root . '/plugins/commerce/includes/ProviderResolver.php';
require_once $root . '/plugins/commerce/includes/WebhookRouter.php';
require_once $root . '/plugins/commerce/includes/PaymentShelf.php';
foreach (['StripeProvider', 'MollieProvider', 'PayPalProvider', 'AdyenProvider', 'SquareProvider', 'RazorpayProvider', 'KomojuProvider', 'PayssionProvider'] as $f) {
    require_once $root . '/plugins/commerce/includes/providers/' . $f . '.php';
}

// Build isolated shelf with temp data dir
class TestPaymentsCredentialStore extends PaymentsCredentialStore
{
    private string $testDir;

    public function __construct(string $testDir)
    {
        $this->testDir = $testDir;
        if (!is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }
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
            chmod($keyFile, 0600);
        }
        $ref = new ReflectionClass(PaymentsCredentialStore::class);
        $baseProp = $ref->getProperty('baseDir');
        $baseProp->setAccessible(true);
        $baseProp->setValue($this, $testDir);
        $keyProp = $ref->getProperty('encryptionKey');
        $keyProp->setAccessible(true);
        $keyProp->setValue($this, trim(file_get_contents($keyFile)));
    }

    public function getDataDir(): string
    {
        return $this->testDir;
    }
}

$store = new TestPaymentsCredentialStore($dataDir);
$activityLog = new PaymentsActivityLog($dataDir);
$resolver = new PaymentsProviderResolver($store);
$resolver->register(new StripeProvider());
$resolver->register(new MollieProvider());
$resolver->register(new KomojuProvider());
$resolver->register(new PayssionProvider());
$resolver->register(new RazorpayProvider());
$shelf = new PaymentShelf($resolver, $store, $activityLog);

// Mock consumer: save connections for resolver tests
$mockCreds = ['mock' => true, 'secret_key' => 'sk_test_mock'];
$store->saveConnection([
    'provider' => 'stripe',
    'label' => 'Stripe Mock',
    'mode' => 'test',
    'enabled' => true,
    'credentials' => $mockCreds,
]);
$store->saveConnection([
    'provider' => 'komoju',
    'label' => 'Komoju Mock',
    'mode' => 'test',
    'enabled' => true,
    'credentials' => $mockCreds,
]);
$store->saveConnection([
    'provider' => 'payssion',
    'label' => 'Payssion Mock',
    'mode' => 'test',
    'enabled' => true,
    'credentials' => $mockCreds,
]);
$store->saveConnection([
    'provider' => 'mollie',
    'label' => 'Mollie Mock',
    'mode' => 'test',
    'enabled' => true,
    'credentials' => $mockCreds,
]);

test_assert('shelf isConfigured true', $shelf->isConfigured() === true);

// Provider resolver: JPY → komoju
$resolved = $shelf->resolveProvider(['currency' => 'jpy']);
test_assert('auto-resolve JPY to komoju', $resolved === 'komoju');

// Country CN → payssion
$resolved = $shelf->resolveProvider(['country' => 'CN']);
test_assert('auto-resolve CN to payssion', $resolved === 'payssion');

// Method ideal → mollie
$resolved = $shelf->resolveProvider(['method' => 'ideal']);
test_assert('auto-resolve ideal to mollie', $resolved === 'mollie');

// Explicit provider
$resolved = $shelf->resolveProvider(['provider' => 'stripe']);
test_assert('explicit provider stripe', $resolved === 'stripe');

// listAvailableProviders with currency filter
$available = $shelf->listAvailableProviders(['currency' => 'jpy', 'configured_only' => true]);
$ids = array_column($available, 'id');
test_assert('listAvailableProviders includes komoju for JPY', in_array('komoju', $ids, true));

// Mock consumer createPayment
$result = $shelf->createPayment('stripe', [
    'amount_cents' => 2900,
    'currency' => 'usd',
    'description' => 'Test order',
], [
    'source_plugin' => 'mock_consumer',
    'source_ref' => 'order_123',
]);
test_assert('createPayment ok', ($result['ok'] ?? false) === true);
test_assert('createPayment returns payment_id', !empty($result['payment_id']));

// Activity log records source_plugin without secrets
$entries = $activityLog->list(['source_plugin' => 'mock_consumer']);
test_assert('activity log has mock consumer entry', count($entries) >= 1);
test_assert('activity log source_ref', ($entries[0]['source_ref'] ?? '') === 'order_123');

// Credential encryption round-trip
$encrypted = $store->encryptArray(['api_key' => 'sk_live_secret_value']);
$decrypted = $store->decryptArray($encrypted);
test_assert('credential encrypt/decrypt', ($decrypted['api_key'] ?? '') === 'sk_live_secret_value');

// Activity log redacts secrets in metadata
$activityLog->append([
    'type' => 'test',
    'metadata' => ['api_key' => 'sk_live_should_redact', 'note' => 'visible'],
]);
$logged = $activityLog->list(['type' => 'test'], 1);
test_assert('activity log redacts api_key', ($logged[0]['metadata']['api_key'] ?? '') === '[REDACTED]');
test_assert('activity log keeps non-secret metadata', ($logged[0]['metadata']['note'] ?? '') === 'visible');

// getProviderCapabilities
$caps = $shelf->getProviderCapabilities('stripe');
test_assert('stripe supports refunds', !empty($caps['features']['refunds']));
test_assert('stripe supports subscriptions', !empty($caps['features']['subscriptions']));

// Refund on mock stripe
$refund = $shelf->refundPayment('stripe', $result['payment_id'], 1000, [
    'source_plugin' => 'mock_consumer',
    'source_ref' => 'refund_1',
]);
test_assert('refundPayment ok on mock stripe', ($refund['ok'] ?? false) === true);

// Subscription on stripe mock
$sub = $shelf->createSubscription('stripe', [
    'customer_id' => 'cus_mock',
    'price_id' => 'price_mock',
], ['source_plugin' => 'mock_consumer', 'source_ref' => 'sub_1']);
test_assert('createSubscription ok on mock stripe', ($sub['ok'] ?? false) === true);

$cancel = $shelf->cancelSubscription('stripe', $sub['subscription_id'] ?? 'mock_sub', [
    'source_plugin' => 'mock_consumer',
]);
test_assert('cancelSubscription ok', ($cancel['ok'] ?? false) === true);

// Cleanup
array_map('unlink', glob($dataDir . '/connections/*.json') ?: []);
@unlink($dataDir . '/activity/log.json');
@rmdir($dataDir . '/connections');
@rmdir($dataDir . '/activity');
@rmdir($dataDir . '/webhooks');
@unlink($dataDir . '/encryption_key.txt');
@rmdir($dataDir);

exit(test_finish('Payments shelf tests'));
