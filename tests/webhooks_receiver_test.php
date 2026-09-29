<?php
/**
 * Inbound webhook receiver tests (Phase 65).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/birkly_paths.php';

$testRoot = sys_get_temp_dir() . '/birkly-webhooks-test-' . uniqid();
mkdir($testRoot, 0755, true);
mkdir($testRoot . '/settings', 0755, true);
mkdir($testRoot . '/logs', 0755, true);

$GLOBALS['__birkly_project_root'] = $testRoot;
putenv('BIRKLY_SECRETS_KEY=' . bin2hex(random_bytes(32)));

// Set internal call so the endpoint does not exit.
$GLOBALS['__HARU_INTERNAL_CALL'] = true;
require_once dirname(__DIR__) . '/api/webhooks.php';
require_once dirname(__DIR__) . '/core/connections/registry.php';

$failures = 0;

function assert_true(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '  ' . $detail . "\n";
        }
    }
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
    }
    rmdir($dir);
}

$hookFired = false;
$hookPayload = null;
$hookConnection = null;
register_hook('birkly_webhook_received', function ($payload, $connection) use (&$hookFired, &$hookPayload, &$hookConnection) {
    $hookFired = true;
    $hookPayload = $payload;
    $hookConnection = $connection;
});

birkly_connections_save(['connections' => []]);

$conn = birkly_connection_create([
    'type' => 'http_credential',
    'provider' => 'bearer',
    'alias' => 'incoming-hook',
    'name' => 'Inbound Webhook',
    'config' => ['base_url' => 'https://example.com', 'timeout' => 5],
    'secrets' => ['token' => 'outbound-token', 'signing_secret' => 'super-secret'],
]);
assert_true('webhook connection created', $conn['ok'] === true);

$payload = json_encode(['event' => 'test', 'data' => ['id' => 123]]);
$signature = hash_hmac('sha256', $payload, 'super-secret');
$headers = ['X-Birkly-Signature' => $signature];

$result = birkly_webhook_process_request('incoming-hook', 'POST', $payload, $headers);
assert_true('verified webhook returns ok', $result['status'] === 'ok' && $result['alias'] === 'incoming-hook');
assert_true('webhook hook fired', $hookFired === true);
assert_true('webhook hook payload decoded', is_array($hookPayload) && ($hookPayload['event'] ?? '') === 'test');
assert_true('webhook hook connection passed', is_array($hookConnection) && ($hookConnection['alias'] ?? '') === 'incoming-hook');

// Missing signature.
$result2 = birkly_webhook_process_request('incoming-hook', 'POST', $payload, []);
assert_true('missing signature rejected', $result2['status'] === 'error' && strpos($result2['error'] ?? '', 'Missing') !== false);

// Invalid signature.
$result3 = birkly_webhook_process_request('incoming-hook', 'POST', $payload, ['X-Birkly-Signature' => 'bad']);
assert_true('invalid signature rejected', $result3['status'] === 'error' && strpos($result3['error'] ?? '', 'Invalid') !== false);

// Unknown connection.
$result4 = birkly_webhook_process_request('unknown-hook', 'POST', $payload, []);
assert_true('unknown connection rejected', $result4['status'] === 'error' && strpos($result4['error'] ?? '', 'not found') !== false);

// Non-POST method.
$result5 = birkly_webhook_process_request('incoming-hook', 'GET', $payload, $headers);
assert_true('non-post rejected', $result5['status'] === 'error' && strpos($result5['error'] ?? '', 'POST') !== false);

// A connection saved with no signing secret is upgraded, then unsigned posts fail.
$openConn = birkly_connection_create([
    'type' => 'http_credential',
    'provider' => 'bearer',
    'alias' => 'open-hook',
    'name' => 'Open Webhook',
    'config' => ['base_url' => 'https://example.com', 'timeout' => 5],
    'secrets' => ['token' => 'outbound-token'],
]);
assert_true('open webhook connection created', $openConn['ok'] === true);
$openId = (string) ($openConn['connection']['id'] ?? '');
$storedOpen = birkly_connection_get($openId, true);
$generated = (string) ($storedOpen['secrets']['signing_secret'] ?? '');
assert_true('empty signing secret was generated', preg_match('/^[a-f0-9]{64}$/', $generated) === 1, $generated);
assert_true('outbound token is kept', ($storedOpen['secrets']['token'] ?? '') === 'outbound-token');

$result6 = birkly_webhook_process_request('open-hook', 'POST', $payload, []);
assert_true('unsigned webhook rejected', $result6['status'] === 'error' && strpos($result6['error'] ?? '', 'Missing') !== false);

$signedHeaders = ['X-Birkly-Signature' => hash_hmac('sha256', $payload, $generated)];
$result7 = birkly_webhook_process_request('open-hook', 'POST', $payload, $signedHeaders);
assert_true('post signed with the generated secret is accepted', $result7['status'] === 'ok' && ($result7['verified'] ?? false) === true);

$storedAgain = birkly_connection_get($openId, true);
assert_true('generated secret is not rotated', ($storedAgain['secrets']['signing_secret'] ?? '') === $generated);

$adminView = birkly_connection_admin_view($storedAgain);
assert_true('connection screen shows the signing secret', ($adminView['inbound_signing_secret'] ?? '') === $generated);
assert_true('connection screen still masks other secrets', ($adminView['secrets']['_masked'] ?? false) === true && !isset($adminView['secrets']['token']));

// A record written before this change, with no signing secret, is filled on the next post.
$legacySecrets = birkly_connection_encrypt_secret(['token' => 'legacy-token']);
$legacy = [
    'id' => 'conn_legacy_open',
    'type' => 'http_credential',
    'provider' => 'bearer',
    'alias' => 'legacy-open',
    'name' => 'Legacy Open',
    'enabled' => true,
    'config' => ['base_url' => 'https://example.com'],
    'secrets_ciphertext' => $legacySecrets,
    'created_at' => date('c'),
    'updated_at' => date('c'),
];
$store = birkly_connections_load();
$store['connections'][] = $legacy;
birkly_connections_save($store);

$legacyUnsigned = birkly_webhook_process_request('legacy-open', 'POST', $payload, []);
assert_true('legacy unsigned post is rejected', ($legacyUnsigned['status'] ?? '') === 'error');
$legacyStored = birkly_connection_get('conn_legacy_open', true);
$legacySecret = (string) ($legacyStored['secrets']['signing_secret'] ?? '');
assert_true('legacy empty secret was generated', preg_match('/^[a-f0-9]{64}$/', $legacySecret) === 1, $legacySecret);
assert_true('legacy token is kept', ($legacyStored['secrets']['token'] ?? '') === 'legacy-token');
$legacySigned = birkly_webhook_process_request('legacy-open', 'POST', $payload, [
    'X-Birkly-Signature' => hash_hmac('sha256', $payload, $legacySecret),
]);
assert_true('legacy post signed with the generated secret is accepted', ($legacySigned['status'] ?? '') === 'ok');

rrmdir($testRoot);

echo "\nwebhooks_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
