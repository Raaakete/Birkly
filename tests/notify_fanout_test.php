<?php
/**
 * Notification fan-out tests (Phase 65).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/birkly_paths.php';

$testRoot = sys_get_temp_dir() . '/birkly-notify-test-' . uniqid();
mkdir($testRoot, 0755, true);
mkdir($testRoot . '/settings', 0755, true);
mkdir($testRoot . '/logs', 0755, true);

$GLOBALS['__birkly_project_root'] = $testRoot;
putenv('BIRKLY_SECRETS_KEY=' . bin2hex(random_bytes(32)));

require_once dirname(__DIR__) . '/core/connections/registry.php';
require_once dirname(__DIR__) . '/core/mail/notify.php';

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

$emailConn = birkly_connection_create([
    'type' => 'email',
    'provider' => 'log',
    'alias' => 'notify_email_event',
    'name' => 'Test Event Mail',
    'from_email' => 'notify@example.com',
]);
assert_true('email connection created', $emailConn['ok'] === true);

$httpConn = birkly_connection_create([
    'type' => 'http_credential',
    'provider' => 'bearer',
    'alias' => 'test_event',
    'name' => 'Test Event Webhook',
    'config' => ['base_url' => 'https://hooks.example.com', 'timeout' => 5],
    'secrets' => ['token' => 'test-token'],
]);
assert_true('http connection created', $httpConn['ok'] === true);

$globalConn = birkly_connection_create([
    'type' => 'http_credential',
    'provider' => 'custom_header',
    'alias' => '*',
    'name' => 'Global Webhook',
    'config' => ['base_url' => 'https://global.example.com', 'timeout' => 5, 'header_name' => 'X-Global-Key'],
    'secrets' => ['header_value' => 'global-value'],
]);
assert_true('global http connection created', $globalConn['ok'] === true);

$emailResult = birkly_notify('notify_email_event', [
    'to' => 'user@example.com',
    'subject' => 'Notify test',
    'message' => 'Hello from notify',
    'metadata' => ['test' => true],
]);
assert_true('email notify returns per-alias results', isset($emailResult['notify_email_event']));
assert_true('email dispatch returns logged', $emailResult['notify_email_event']['status'] === 'logged');

$results = birkly_notify('test_event', [
    'to' => 'user@example.com',
    'subject' => 'Notify test',
    'message' => 'Hello from notify',
    'metadata' => ['test' => true],
]);

assert_true('notify returns per-alias results', isset($results['test_event']) && isset($results['*']));
assert_true('http dispatch returns accepted', $results['test_event']['status'] === 'accepted' && $results['*']['status'] === 'accepted');

$httpDispatch = null;
foreach ($captured as $c) {
    if (($c['url'] ?? '') === 'https://hooks.example.com') {
        $httpDispatch = $c;
        break;
    }
}
assert_true('http webhook dispatched', $httpDispatch !== null);
if ($httpDispatch !== null) {
    $authHeader = '';
    foreach ($httpDispatch['headers'] ?? [] as $h) {
        if (stripos($h, 'Authorization:') === 0) {
            $authHeader = $h;
            break;
        }
    }
    assert_true('webhook includes bearer authorization', $authHeader === 'Authorization: Bearer test-token');
    $decoded = json_decode($httpDispatch['body'] ?? '', true);
    assert_true('webhook json contains subject', is_array($decoded) && ($decoded['subject'] ?? '') === 'Notify test');
}

// Global alias should match a different event.
$globalResults = birkly_notify('other_event', [
    'to' => 'other@example.com',
    'subject' => 'Global test',
    'message' => 'Global webhook',
]);
assert_true('global alias matches other event', isset($globalResults['*']) && $globalResults['*']['status'] === 'accepted');

$globalDispatch = null;
foreach ($captured as $c) {
    if (($c['url'] ?? '') === 'https://global.example.com') {
        $globalDispatch = $c;
    }
}
assert_true('global webhook dispatched', $globalDispatch !== null);
if ($globalDispatch !== null) {
    $globalHeader = '';
    foreach ($globalDispatch['headers'] ?? [] as $h) {
        if (stripos($h, 'X-Global-Key:') === 0) {
            $globalHeader = $h;
            break;
        }
    }
    assert_true('global webhook includes custom header', $globalHeader === 'X-Global-Key: global-value');
}

unset($GLOBALS['__birkly_http_transport']);
rrmdir($testRoot);

echo "\nnotify_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
