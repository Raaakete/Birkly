<?php
/**
 * Connection registry tests (Phase 65).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/birkly_paths.php';

$testRoot = sys_get_temp_dir() . '/birkly-connections-test-' . uniqid();
mkdir($testRoot, 0755, true);
mkdir($testRoot . '/settings', 0755, true);
mkdir($testRoot . '/logs', 0755, true);

$GLOBALS['__birkly_project_root'] = $testRoot;
putenv('BIRKLY_SECRETS_KEY=' . bin2hex(random_bytes(32)));

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

// Clean slate
birkly_connections_save(['connections' => []]);

// Create a log connection.
$created = birkly_connection_create([
    'type' => 'email',
    'provider' => 'log',
    'alias' => 'log-only',
    'name' => 'Log Only',
    'from_email' => 'test@example.com',
    'from_name' => 'Birkly Test',
]);
assert_true('create log connection succeeds', $created['ok'] === true);
assert_true('created connection has id', !empty($created['connection']['id']));
assert_true('first connection becomes default', !empty($created['connection']['is_default']));

$list = birkly_connections_list(false);
assert_true('list contains one connection', count($list) === 1);
assert_true('list masks secrets', isset($list[0]['secrets']['_masked']));

// Create an SMTP connection.
$smtp = birkly_connection_create([
    'type' => 'email',
    'provider' => 'smtp',
    'alias' => 'primary-smtp',
    'name' => 'Primary SMTP',
    'from_email' => 'smtp@example.com',
    'config' => ['host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'tls'],
    'secrets' => ['username' => 'user', 'password' => 'secret'],
]);
assert_true('create smtp connection succeeds', $smtp['ok'] === true);

// Retrieve with decrypted secrets.
$withSecrets = birkly_connection_get($smtp['connection']['id'], true);
assert_true('smtp connection stores encrypted secrets', !empty($withSecrets['secrets_ciphertext']));
assert_true('decrypted secrets contain password', ($withSecrets['secrets']['password'] ?? null) === 'secret');

// Alias uniqueness.
$dup = birkly_connection_create([
    'type' => 'email',
    'provider' => 'log',
    'alias' => 'log-only',
    'name' => 'Duplicate',
    'from_email' => 'dup@example.com',
]);
assert_true('duplicate alias rejected', $dup['ok'] === false && isset($dup['errors']['alias']));

// Update connection.
$updated = birkly_connection_update($smtp['connection']['id'], [
    'name' => 'Updated SMTP',
    'config' => ['host' => 'smtp2.example.com', 'port' => 465, 'encryption' => 'ssl'],
    'secrets' => ['password' => 'new-secret'],
]);
assert_true('update succeeds', $updated['ok'] === true);
$afterUpdate = birkly_connection_get($smtp['connection']['id'], true);
assert_true('updated host stored', ($afterUpdate['config']['host'] ?? '') === 'smtp2.example.com');
assert_true('merged secrets preserve username', ($afterUpdate['secrets']['username'] ?? '') === 'user');
assert_true('updated password stored', ($afterUpdate['secrets']['password'] ?? '') === 'new-secret');

// Set default.
$setDefault = birkly_connection_set_default($smtp['connection']['id']);
assert_true('set default succeeds', $setDefault['ok'] === true);
$default = birkly_connection_get_default('email', false);
assert_true('default is smtp connection', ($default['id'] ?? '') === $smtp['connection']['id']);

// Delete.
$deleted = birkly_connection_delete($created['connection']['id']);
assert_true('delete succeeds', $deleted['ok'] === true);
$listAfter = birkly_connections_list(false);
assert_true('one connection remains', count($listAfter) === 1);

// Audit log was created.
$auditFile = $testRoot . '/logs/connections-audit.jsonl';
assert_true('audit log file exists', file_exists($auditFile));

// Cleanup.
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
rrmdir($testRoot);

echo "\nconnections_registry_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
