<?php
/**
 * CMS update install must not delete live files when overwrite fails; backup restore must merge.
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
$restore = $ref->getMethod('restoreBackup');
$restore->setAccessible(true);
$priority = $ref->getMethod('updateInstallPathPriority');
$priority->setAccessible(true);

assert_true(
    'core/update_staging.php installs before api/session.php',
    $priority->invoke(null, 'core/update_staging.php') < $priority->invoke(null, 'api/session.php')
);

$tmp = sys_get_temp_dir() . '/birkly-atomic-' . uniqid();
$source = $tmp . '/source.txt';
$target = $tmp . '/target.txt';
mkdir($tmp, 0755, true);
file_put_contents($source, 'new-content');
file_put_contents($target, 'keep-me');

$ok = $copy->invoke(null, $source, $target, 'target.txt');
assert_true('copyFileForUpdate updates writable file', $ok && file_get_contents($target) === 'new-content');

$cms = $tmp . '/cms';
$backup = $tmp . '/backup';
mkdir($cms . '/api', 0755, true);
file_put_contents($cms . '/api/updates.php', '<?php // broken');
mkdir($backup . '/api', 0755, true);
file_put_contents($backup . '/api/updates.php', '<?php // restored');

$restored = $restore->invoke(null, $backup, $cms);
assert_true('restoreBackup merges into existing cms root', $restored === true);
assert_true(
    'restored updates.php content',
    file_get_contents($cms . '/api/updates.php') === '<?php // restored'
);

cleanup_dir($tmp);

echo "\nversion_update_atomic_copy_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
