<?php
/**
 * Fresh Birkly installs can install official plugins from bundled catalog/packages zips.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/marketplace.php';
require_once $root . '/core/marketplace/install.php';

$errors = [];

$bundled = $root . '/catalog/packages/birkly-automation-1.1.31.zip';
if (!is_file($bundled)) {
    $errors[] = 'Bundled automation package should ship in catalog/packages/';
}

$zip = birkly_marketplace_find_local_catalog_zip('birkly-automation', '1.1.31');
if ($zip !== $bundled) {
    $errors[] = 'Bundled catalog lookup should resolve to catalog/packages in the CMS repo';
}

$GLOBALS['birkly_marketplace_http_transport'] = static function (): array {
    return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Connection timed out'];
};

$start = microtime(true);
$result = birkly_marketplace_download_artifact(
    'https://marketplace.birkly.cloud/api/v1/artifacts/birkly-automation/1.1.31/download',
    '424db62b9be37a381f951af0beb3a0347569eb8195ac100871e4925dab8206da'
);
$elapsedMs = (int) round((microtime(true) - $start) * 1000);
unset($GLOBALS['birkly_marketplace_http_transport']);

if (empty($result['ok']) || empty($result['path'])) {
    $errors[] = 'Bundled install should succeed without network: ' . ($result['error'] ?? 'unknown');
}
if ($elapsedMs > 2000) {
    $errors[] = 'Bundled install should be instant, took ' . $elapsedMs . 'ms';
}

if (!empty($result['path']) && is_file($result['path'])) {
    @unlink($result['path']);
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "bundled_catalog_install_test: OK ({$elapsedMs}ms)\n";
