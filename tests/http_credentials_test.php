<?php
/**
 * HTTP credential connection tests (Phase 65).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/birkly_paths.php';

$testRoot = sys_get_temp_dir() . '/birkly-http-cred-test-' . uniqid();
mkdir($testRoot, 0755, true);
mkdir($testRoot . '/settings', 0755, true);
mkdir($testRoot . '/logs', 0755, true);

$GLOBALS['__birkly_project_root'] = $testRoot;
putenv('BIRKLY_SECRETS_KEY=' . bin2hex(random_bytes(32)));

require_once dirname(__DIR__) . '/core/connections/registry.php';
require_once dirname(__DIR__) . '/core/connections/http.php';

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

birkly_connections_save(['connections' => []]);

$captured = [];
$GLOBALS['__birkly_http_transport'] = function (string $url, string $method, string $body, array $headers, int $timeout) use (&$captured): array {
    $captured[] = ['url' => $url, 'method' => $method, 'body' => $body, 'headers' => $headers];
    return ['status' => 'accepted', 'http_code' => 200, 'response' => '{"ok":true}', 'error' => null];
};

// Basic auth credential.
$basic = birkly_connection_create([
    'type' => 'http_credential',
    'provider' => 'basic_auth',
    'alias' => 'basic-cred',
    'name' => 'Basic Auth',
    'config' => ['base_url' => 'https://api.example.com', 'timeout' => 5],
    'secrets' => ['username' => 'alice', 'password' => 'secret'],
]);
assert_true('basic auth connection created', $basic['ok'] === true);

$result = birkly_http_request('basic-cred', 'POST', '/webhook', ['foo' => 'bar']);
assert_true('basic auth request accepted', $result['status'] === 'accepted' && $result['http_code'] === 200);
$last = $captured[count($captured) - 1] ?? [];
assert_true('basic auth URL built', ($last['url'] ?? '') === 'https://api.example.com/webhook');
$authHeader = '';
foreach ($last['headers'] ?? [] as $h) {
    if (stripos($h, 'Authorization:') === 0) {
        $authHeader = $h;
        break;
    }
}
assert_true('basic auth header present', strpos($authHeader, 'Basic ') !== false);
if (strpos($authHeader, 'Basic ') !== false) {
    $encoded = trim(substr($authHeader, strpos($authHeader, 'Basic ') + 6));
    assert_true('basic auth credentials correct', base64_decode($encoded, true) === 'alice:secret');
}

// Bearer credential.
$bearer = birkly_connection_create([
    'type' => 'http_credential',
    'provider' => 'bearer',
    'alias' => 'bearer-cred',
    'name' => 'Bearer Token',
    'config' => ['base_url' => 'https://api.example.com', 'timeout' => 5],
    'secrets' => ['token' => 'bearer-token'],
]);
assert_true('bearer connection created', $bearer['ok'] === true);

$result = birkly_http_request('bearer-cred', 'POST', '/hook', ['ok' => true]);
assert_true('bearer request accepted', $result['status'] === 'accepted' && $result['http_code'] === 200);
$last = $captured[count($captured) - 1] ?? [];
$authHeader = '';
foreach ($last['headers'] ?? [] as $h) {
    if (stripos($h, 'Authorization:') === 0) {
        $authHeader = $h;
        break;
    }
}
assert_true('bearer authorization present', $authHeader === 'Authorization: Bearer bearer-token');

// Custom header credential.
$custom = birkly_connection_create([
    'type' => 'http_credential',
    'provider' => 'custom_header',
    'alias' => 'custom-cred',
    'name' => 'Custom Header',
    'config' => ['base_url' => 'https://api.example.com', 'timeout' => 5, 'header_name' => 'X-Api-Key'],
    'secrets' => ['header_value' => 'secret-value'],
]);
assert_true('custom header connection created', $custom['ok'] === true);

$result = birkly_http_request('custom-cred', 'GET', '/resource', null);
assert_true('custom header request accepted', $result['status'] === 'accepted');
$last = $captured[count($captured) - 1] ?? [];
$customHeader = '';
foreach ($last['headers'] ?? [] as $h) {
    if (stripos($h, 'X-Api-Key:') === 0) {
        $customHeader = $h;
        break;
    }
}
assert_true('custom header present', $customHeader === 'X-Api-Key: secret-value');

// Missing credential returns failed honestly.
unset($GLOBALS['__birkly_http_transport']);
$missing = birkly_http_request('does-not-exist', 'GET', 'https://127.0.0.1:1/');
assert_true('missing connection fails honestly', $missing['status'] === 'failed');

rrmdir($testRoot);

echo "\nhttp_credentials_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
