<?php
/**
 * Hub update must accept zero-byte placeholder files (e.g. plugins/.gitkeep in release zips).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/version.php';
require_once dirname(__DIR__) . '/core/update_staging.php';

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
$move = $ref->getMethod('moveUpdateFiles');
$move->setAccessible(true);
$excludeMethod = $ref->getMethod('updateInstallExcludePrefixes');
$excludeMethod->setAccessible(true);
$excludeFiles = $excludeMethod->invoke(null);

$sourceDir = dirname(__DIR__) . '/temp/empty-update-src-' . uniqid();
$targetDir = dirname(__DIR__) . '/temp/empty-update-tgt-' . uniqid();

try {
    mkdir($sourceDir . '/core', 0777, true);
    mkdir($sourceDir . '/plugins', 0777, true);
    file_put_contents($sourceDir . '/core/version.php', '<?php // patched');
    touch($sourceDir . '/plugins/.gitkeep');

    mkdir($targetDir . '/core', 0777, true);
    mkdir($targetDir . '/plugins', 0777, true);
    file_put_contents($targetDir . '/core/version.php', '<?php // old');
    file_put_contents($targetDir . '/plugins/existing-plugin.txt', 'keep-me');
    mkdir($targetDir . '/data', 0777, true);

    $ok = $move->invoke(null, $sourceDir, $targetDir, $excludeFiles, true);
    assert_true('moveUpdateFiles succeeds with zero-byte files in package', $ok === true);

    birkly_apply_staged_update_files($targetDir);
    assert_true('no pending staged files after apply', birkly_count_pending_staged_update_files($targetDir) === 0);
    assert_true(
        'core file updated',
        str_contains((string) file_get_contents($targetDir . '/core/version.php'), 'patched'),
    );
    assert_true(
        'plugins dir not touched by update (marketplace-owned)',
        is_file($targetDir . '/plugins/existing-plugin.txt')
            && !is_file($targetDir . '/plugins/.birkly-staged'),
    );
} finally {
    cleanup_dir($sourceDir);
    cleanup_dir($targetDir);
}

if ($failures > 0) {
    echo "\nversion_empty_update_file_test: FAIL ($failures)\n";
    exit(1);
}

echo "\nversion_empty_update_file_test: PASS\n";
exit(0);
