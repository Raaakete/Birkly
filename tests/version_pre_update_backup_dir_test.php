<?php
/**
 * Pre-update backup dir: auto-create backups/ and fall back when CMS root backups/ is not writable.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/version.php';
require_once dirname(__DIR__) . '/core/birkly_paths.php';

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

function cleanup_dir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($dir);
}

$ref = new ReflectionClass(BirklyVersion::class);
$allocate = $ref->getMethod('allocatePreUpdateBackupDir');
$allocate->setAccessible(true);
$candidates = $ref->getMethod('preUpdateBackupBaseCandidates');
$candidates->setAccessible(true);

$root = sys_get_temp_dir() . '/birkly-backup-dir-' . uniqid();
mkdir($root, 0755, true);
mkdir($root . '/core', 0755, true);
file_put_contents($root . '/index.php', '<?php');

try {
    $runtime = birkly_ensure_runtime_dirs($root);
    assert_true('runtime report includes backups', isset($runtime['backups']));
    assert_true('backups dir created under cms root', is_dir($root . '/backups'));

    $dir = $allocate->invoke(null, $root);
    assert_true('allocate succeeds on writable tree', is_string($dir) && is_dir($dir), 'got: ' . var_export($dir, true));
    assert_true(
        'backup lives under backups/ when writable',
        is_string($dir) && str_contains($dir, '/backups/pre-update-'),
    );

    $fallbackRoot = sys_get_temp_dir() . '/birkly-backup-fallback-' . uniqid();
    mkdir($fallbackRoot, 0755, true);
    mkdir($fallbackRoot . '/data', 0755, true);
    mkdir($fallbackRoot . '/core', 0755, true);
    file_put_contents($fallbackRoot . '/index.php', '<?php');
    file_put_contents($fallbackRoot . '/backups', 'not-a-directory');

    try {
        $fallbackDir = $allocate->invoke(null, $fallbackRoot);
        assert_true(
            'falls back to data/pre_update_backups when backups/ is unusable',
            is_string($fallbackDir) && str_contains($fallbackDir, '/data/pre_update_backups/pre-update-'),
            'got: ' . var_export($fallbackDir, true),
        );
    } finally {
        unlink($fallbackRoot . '/backups');
        cleanup_dir($fallbackRoot);
    }

    $list = $candidates->invoke(null, $root);
    assert_true('three backup base candidates', count($list) === 3);
} finally {
    cleanup_dir($root);
}

if ($failures > 0) {
    echo "\nversion_pre_update_backup_dir_test: FAIL ($failures)\n";
    exit(1);
}

echo "\nversion_pre_update_backup_dir_test: PASS\n";
exit(0);
