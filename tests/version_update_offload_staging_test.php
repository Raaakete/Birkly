<?php
/**
 * Hub update can stage files under data/cms_update_staging when core co-located staging fails.
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

$sourceDir = sys_get_temp_dir() . '/birkly-defer-src-' . uniqid();
$targetDir = sys_get_temp_dir() . '/birkly-defer-tgt-' . uniqid();

try {
    mkdir($sourceDir . '/core', 0777, true);
    file_put_contents($sourceDir . '/core/public_current_user.php', '<?php // new');

    mkdir($targetDir, 0777, true);
    mkdir($targetDir . '/core', 0777, true);
    file_put_contents($targetDir . '/core/public_current_user.php', '<?php // old');
    chmod($targetDir . '/core', 0555);
    mkdir($targetDir . '/data', 0777, true);

    $exclude = ['content/', 'media/', 'settings/', 'project/', 'backups/', 'data/', 'users/'];
    $ok = $move->invoke(null, $sourceDir, $targetDir, $exclude, true);
    assert_true('defer moveUpdateFiles succeeds with read-only core dir', $ok === true);

    $offload = birkly_update_offload_staging_path('core/public_current_user.php', $targetDir);
    assert_true('offload staging file exists', is_file($offload));

    chmod($targetDir . '/core', 0755);
    $GLOBALS['__birkly_project_root'] = $targetDir;
    birkly_apply_staged_update_files($targetDir);
    assert_true(
        'live file updated after apply',
        str_contains((string) file_get_contents($targetDir . '/core/public_current_user.php'), 'new'),
    );
    assert_true('no pending staged files', birkly_count_pending_staged_update_files($targetDir) === 0);
} finally {
    chmod($targetDir . '/core', 0755);
    cleanup_dir($sourceDir);
    cleanup_dir($targetDir);
}

if ($failures > 0) {
    echo "\nversion_update_offload_staging_test: FAIL ($failures)\n";
    exit(1);
}

echo "\nversion_update_offload_staging_test: PASS\n";
exit(0);
