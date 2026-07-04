<?php
/**
 * P6.4-M0 — Commerce shelf + provider resolver tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Commerce shelf tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';

$dataDir = sys_get_temp_dir() . '/birkly-commerce-shelf-' . getmypid();
if (is_dir($dataDir)) {
    array_map('unlink', glob($dataDir . '/connections/*.json') ?: []);
    @unlink($dataDir . '/activity/log.json');
    @unlink($dataDir . '/encryption_key.txt');
}

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

class TestCommerceCredentialStore extends PaymentsCredentialStore
{
    public function __construct(string $testDir)
    {
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
        $ref = new ReflectionClass(PaymentsCredentialStore::class);
        $baseProp = $ref->getProperty('baseDir');
        $baseProp->setAccessible(true);
        return $baseProp->getValue($this);
    }
}

$store = new TestCommerceCredentialStore($dataDir);
$activityLog = new PaymentsActivityLog($dataDir);
$resolver = new PaymentsProviderResolver($store);
$resolver->register(new StripeProvider());
$resolver->register(new MollieProvider());
$resolver->register(new KomojuProvider());
$resolver->register(new PayssionProvider());
$resolver->register(new RazorpayProvider());
$shelf = new PaymentShelf($resolver, $store, $activityLog);

$mockCreds = ['mock' => true, 'secret_key' => 'sk_test_mock'];
$store->saveConnection([
    'provider' => 'stripe', 'label' => 'Stripe Mock', 'mode' => 'test', 'enabled' => true,
    'credentials' => $mockCreds,
]);
$store->saveConnection([
    'provider' => 'komoju', 'label' => 'Komoju Mock', 'mode' => 'test', 'enabled' => true,
    'credentials' => $mockCreds,
]);
$store->saveConnection([
    'provider' => 'payssion', 'label' => 'Payssion Mock', 'mode' => 'test', 'enabled' => true,
    'credentials' => $mockCreds,
]);
$store->saveConnection([
    'provider' => 'mollie', 'label' => 'Mollie Mock', 'mode' => 'test', 'enabled' => true,
    'credentials' => $mockCreds,
]);

test_assert('shelf isConfigured true', $shelf->isConfigured() === true);
test_assert('auto-resolve JPY to komoju', $shelf->resolveProvider(['currency' => 'jpy']) === 'komoju');
test_assert('auto-resolve CN to payssion', $shelf->resolveProvider(['country' => 'CN']) === 'payssion');
test_assert('auto-resolve ideal to mollie', $shelf->resolveProvider(['method' => 'ideal']) === 'mollie');
test_assert('explicit provider stripe', $shelf->resolveProvider(['provider' => 'stripe']) === 'stripe');

$result = $shelf->createPayment('stripe', [
    'amount_cents' => 2900, 'currency' => 'usd', 'description' => 'Test order',
], ['source_plugin' => 'mock_consumer', 'source_ref' => 'order_123']);
test_assert('createPayment ok', ($result['ok'] ?? false) === true);
test_assert('createPayment returns payment_id', !empty($result['payment_id']));

$encrypted = $store->encryptArray(['api_key' => 'sk_live_secret_value']);
$decrypted = $store->decryptArray($encrypted);
test_assert('credential encrypt/decrypt', ($decrypted['api_key'] ?? '') === 'sk_live_secret_value');

test_assert('payments_bootstrap alias exists', function_exists('payments_bootstrap'));
test_assert('commerce_plugin_active alias', payments_plugin_active() === commerce_plugin_active() || !function_exists('birkly_is_plugin_active'));

array_map('unlink', glob($dataDir . '/connections/*.json') ?: []);
@unlink($dataDir . '/activity/log.json');
@rmdir($dataDir . '/connections');
@rmdir($dataDir . '/activity');
@rmdir($dataDir . '/webhooks');
@unlink($dataDir . '/encryption_key.txt');
@rmdir($dataDir);

exit(test_finish('Commerce shelf tests'));
