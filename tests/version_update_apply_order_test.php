<?php
/**
 * Deferred apply must promote core/ before api/ (dependency-safe order).
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

$root = sys_get_temp_dir() . '/birkly-apply-order-' . uniqid();
$suffix = birkly_update_staging_suffix();

try {
    mkdir($root . '/core/storage', 0777, true);
    mkdir($root . '/api', 0777, true);
    mkdir($root . '/data/cms_update_staging/core/storage', 0777, true);
    mkdir($root . '/data/cms_update_staging/api', 0777, true);

    file_put_contents($root . '/core/storage/MySqlStorage.php', '<?php // old');
    file_put_contents($root . '/api/storage.php', '<?php // old api');

    $coreStaged = birkly_update_offload_staging_path('core/storage/AbstractSqlStorage.php', $root);
    $apiStaged = birkly_update_offload_staging_path('api/storage.php', $root);
    file_put_contents($coreStaged, '<?php // abstract');
    file_put_contents($apiStaged, '<?php require_once __DIR__ . "/../core/storage/AbstractSqlStorage.php"; // new api');

    $promotions = birkly_collect_staged_update_promotions($root);
    assert_true('two staged promotions collected', count($promotions) === 2);
    assert_true(
        'core/storage promoted before api/',
        birkly_update_install_path_priority($promotions[0]['relative'])
        < birkly_update_install_path_priority($promotions[1]['relative']),
        $promotions[0]['relative'] . ' vs ' . $promotions[1]['relative']
    );

    birkly_apply_staged_update_files($root);
    assert_true(
        'abstract core file exists after apply',
        is_file($root . '/core/storage/AbstractSqlStorage.php')
    );
    assert_true('api file updated after apply', str_contains((string) file_get_contents($root . '/api/storage.php'), 'new api'));
    assert_true('no pending staged files', birkly_count_pending_staged_update_files($root) === 0);
} finally {
    cleanup_dir($root);
}

if ($failures > 0) {
    echo "\nversion_update_apply_order_test: FAIL ($failures)\n";
    exit(1);
}

echo "\nversion_update_apply_order_test: PASS\n";
exit(0);
