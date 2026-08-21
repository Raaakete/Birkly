<?php
/**
 * CMS update install must not overwrite live project/ site files.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/version.php';

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

$excludeFiles = [
    'users/',
    'content/',
    'media/',
    'logs/',
    'settings/',
    'backups/',
    'project/',
    '.env',
    'config.php',
];

$sourceDir = sys_get_temp_dir() . '/birkly-update-src-' . uniqid();
$targetDir = sys_get_temp_dir() . '/birkly-update-tgt-' . uniqid();

try {
    mkdir($sourceDir . '/core', 0755, true);
    mkdir($sourceDir . '/admin', 0755, true);
    mkdir($sourceDir . '/project', 0755, true);
    file_put_contents($sourceDir . '/core/version.php', '<?php // updated core');
    file_put_contents($sourceDir . '/admin/index.php', '<?php // updated admin');
    file_put_contents($sourceDir . '/project/index.html', '<title>Birkly - Demo Website</title>');

    mkdir($targetDir . '/project', 0755, true);
    file_put_contents($targetDir . '/project/index.html', '<title>Butterbrotbande</title>');
    file_put_contents($targetDir . '/core/version.php', '<?php // old core');

    $ref = new ReflectionClass(BirklyVersion::class);
    $move = $ref->getMethod('moveUpdateFiles');
    $move->setAccessible(true);
    $ok = $move->invoke(null, $sourceDir, $targetDir, $excludeFiles);
    assert_true('moveUpdateFiles succeeds', $ok === true);

    $projectHtml = (string) file_get_contents($targetDir . '/project/index.html');
    assert_true(
        'project/index.html is preserved during update',
        str_contains($projectHtml, 'Butterbrotbande'),
        'got: ' . substr($projectHtml, 0, 80)
    );

    $corePhp = (string) file_get_contents($targetDir . '/core/version.php');
    assert_true(
        'core files are still updated',
        str_contains($corePhp, 'updated core'),
        'got: ' . substr($corePhp, 0, 80)
    );
} finally {
    cleanup_dir($sourceDir);
    cleanup_dir($targetDir);
}

if ($failures > 0) {
    echo "\nversion_install_update_test: FAIL ($failures)\n";
    exit(1);
}

echo "\nversion_install_update_test: PASS\n";
exit(0);
