<?php
/**
 * CMS update path helpers (Windows-safe excludes + package storage).
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

$ref = new ReflectionClass(BirklyVersion::class);
$isExcluded = $ref->getMethod('updatePathIsExcluded');
$isExcluded->setAccessible(true);
$excludes = $ref->getMethod('updateInstallExcludePrefixes');
$excludes->setAccessible(true);
$list = $excludes->invoke(null);

assert_true(
    'excludes runtime with backslashes',
    $isExcluded->invoke(null, 'runtime\\php\\php.exe', $list) === true
);
assert_true(
    'excludes project forward slash',
    $isExcluded->invoke(null, 'project/index.html', $list) === true
);
assert_true(
    'allows core files',
    $isExcluded->invoke(null, 'core/version.php', $list) === false
);

$version = '1.0.2-p86.2';
$basename = BirklyVersion::updatePackageBasename($version);
assert_true('basename sanitizes version', $basename === 'birkly-update-1.0.2-p86.2.zip', $basename);

$dir = BirklyVersion::updatePackageStorageDir();
$path = BirklyVersion::updatePackagePath($version);
assert_true('package path under data/cms_updates', str_contains($path, 'data/cms_updates'), $path);

if (!is_dir($dir)) {
    mkdir($dir, 0750, true);
}
file_put_contents($path, 'zip-placeholder');
$resolved = BirklyVersion::resolveDownloadedUpdatePath($version, $basename);
assert_true('resolve finds package in storage dir', $resolved === $path, (string) $resolved);
@unlink($path);

echo "\nversion_update_windows_paths_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
