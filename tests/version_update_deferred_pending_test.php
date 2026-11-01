<?php
/**
 * Hub install must not fail when only self-replacing entrypoints remain staged.
 */

declare(strict_types=1);

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

$root = dirname(__DIR__) . '/temp/deferred-pending-' . uniqid();
mkdir($root . '/data/cms_update_staging/api', 0777, true);
mkdir($root . '/core', 0777, true);

try {
    $deferredPath = birkly_update_offload_staging_path('api/updates.php', $root);
    file_put_contents($deferredPath, '<?php // staged');
    touch($root . '/core/blocking.php.birkly-staged');

    assert_true(
        'total pending includes deferred and blocking',
        birkly_count_pending_staged_update_files($root) >= 2,
    );
    assert_true(
        'blocking count ignores deferred entrypoints',
        birkly_count_blocking_pending_staged_update_files($root) === 1,
    );

    $listed = birkly_list_pending_staged_relative_paths($root);
    assert_true('lists relative paths', in_array('api/updates.php', $listed, true));

    birkly_purge_update_staging($root);
    assert_true('purge clears staging', birkly_count_pending_staged_update_files($root) === 0);
} finally {
    cleanup_dir($root);
}

if ($failures > 0) {
    echo "\nversion_update_deferred_pending_test: FAIL ($failures)\n";
    exit(1);
}

echo "\nversion_update_deferred_pending_test: PASS\n";
exit(0);
