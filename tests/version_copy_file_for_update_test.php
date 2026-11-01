<?php
/**
 * CMS update file copy: read-only targets and deferred .birkly-staged swaps.
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

$ref = new ReflectionClass(BirklyVersion::class);
$copy = $ref->getMethod('copyFileForUpdate');
$copy->setAccessible(true);

$tmp = sys_get_temp_dir() . '/birkly-copy-update-' . uniqid();
$core = $tmp . '/core';
mkdir($core, 0777, true);

$target = $core . '/public_current_user.php';
$source = $tmp . '/source.php';
file_put_contents($source, '<?php // new');
file_put_contents($target, '<?php // old');
chmod($target, 0444);

try {
    $ok = $copy->invoke(null, $source, $target, 'core/public_current_user.php');
    assert_true('overwrites read-only target via unlink/replace', $ok === true);
    assert_true(
        'target has new contents',
        is_file($target) && str_contains((string) file_get_contents($target), 'new'),
    );
    assert_true('no staged file left behind', !is_file($target . '.birkly-staged'));
} finally {
    chmod($target, 0644);
    cleanup_dir($tmp);
}

if ($failures > 0) {
    echo "\nversion_copy_file_for_update_test: FAIL ($failures)\n";
    exit(1);
}

echo "\nversion_copy_file_for_update_test: PASS\n";
exit(0);
