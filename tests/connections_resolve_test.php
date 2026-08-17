<?php
/**
 * Email connection resolve helper tests.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/birkly_paths.php';

$testRoot = sys_get_temp_dir() . '/birkly-resolve-test-' . uniqid();
mkdir($testRoot, 0755, true);
mkdir($testRoot . '/settings', 0755, true);
mkdir($testRoot . '/logs', 0755, true);

$GLOBALS['__birkly_project_root'] = $testRoot;
putenv('BIRKLY_SECRETS_KEY=' . bin2hex(random_bytes(32)));

require_once dirname(__DIR__) . '/core/connections/registry.php';
require_once dirname(__DIR__) . '/core/connections/resolve.php';

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
$created = birkly_connection_create([
    'type' => 'email',
    'provider' => 'log',
    'alias' => 'primary-email',
    'name' => 'Primary',
    'from_email' => 'hello@example.com',
    'is_default' => true,
]);
assert_true('connection created', $created['ok'] === true);

assert_true('empty config resolves to null', birkly_resolve_email_connection_alias('') === null);
assert_true('known alias resolves', birkly_resolve_email_connection_alias('primary-email') === 'primary-email');
assert_true('unknown alias resolves to null', birkly_resolve_email_connection_alias('missing') === null);

$picker = birkly_email_connections_for_picker();
assert_true('picker has connections', ($picker['has_connections'] ?? false) === true);
assert_true('picker default alias', ($picker['default_alias'] ?? '') === 'primary-email');

rrmdir($testRoot);

echo "\nconnections_resolve_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
