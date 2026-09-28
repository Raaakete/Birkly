<?php
/**
 * Hub update checks only offer releases whose min_birkly accepts this CMS (P96-M14).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/marketplace.php';
require_once $root . '/core/plugins/version_constraints.php';

$errors = [];
$versionFile = $root . '/VERSION';
$original = is_file($versionFile) ? (string) file_get_contents($versionFile) : null;

$releases = [
    ['version' => '1.0.150', 'min_birkly' => '>=1.0.0'],
    ['version' => '1.1.0', 'min_birkly' => '>=1.1.0'],
    ['version' => '1.0.149', 'min_birkly' => ''],
    ['version' => '1.0.148', 'min_birkly' => '1.0.0'],
];

file_put_contents($versionFile, "1.0.1\n");
$GLOBALS['__birkly_platform_config'] = ['release_channel' => 'stable'];
$compatible = array_column(birkly_marketplace_releases_compatible_with_site($releases), 'version');
if (in_array('1.1.0', $compatible, true)) {
    $errors[] = 'a 1.0.1 site must not be offered a >=1.1.0 release';
}
if (!in_array('1.0.150', $compatible, true) || !in_array('1.0.149', $compatible, true) || !in_array('1.0.148', $compatible, true)) {
    $errors[] = 'compatible releases (>=1.0.0, empty, bare 1.0.0) must stay: ' . implode(',', $compatible);
}
if (birkly_marketplace_latest_release_version(birkly_marketplace_releases_compatible_with_site($releases)) !== '1.0.150') {
    $errors[] = 'latest compatible release for 1.0.1 should be 1.0.150';
}
if (birkly_marketplace_release_min_birkly($releases, '1.1.0') !== '>=1.1.0') {
    $errors[] = 'min_birkly lookup by version';
}

file_put_contents($versionFile, "1.1.0\n");
$compatible = array_column(birkly_marketplace_releases_compatible_with_site($releases), 'version');
if (!in_array('1.1.0', $compatible, true)) {
    $errors[] = 'a 1.1.0 site gets the >=1.1.0 release';
}

if ($original !== null) {
    file_put_contents($versionFile, $original);
} else {
    @unlink($versionFile);
}
unset($GLOBALS['__birkly_platform_config']);

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}
echo "marketplace_min_birkly_filter_test: OK\n";
