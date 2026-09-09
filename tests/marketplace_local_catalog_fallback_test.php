<?php
/**
 * Local dev artifact downloads fall back to Birkly-Marketplace catalog zips.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/marketplace.php';
require_once $root . '/core/marketplace/install.php';

$errors = [];

$_SERVER['HTTP_HOST'] = 'localhost:8080';

$zip = birkly_marketplace_find_local_catalog_zip('birkly-automation', '1.1.25');
if ($zip === null || !is_file($zip)) {
    $errors[] = 'Expected a local catalog zip for birkly-automation 1.1.25 in sibling Birkly-Marketplace';
}

$parsed = birkly_marketplace_parse_artifact_slug_version(
    'https://marketplace.birkly.cloud/api/v1/artifacts/birkly-automation/1.1.25/download'
);
if (($parsed['slug'] ?? '') !== 'birkly-automation' || ($parsed['version'] ?? '') !== '1.1.25') {
    $errors[] = 'Artifact URL parser should extract slug and version';
}

$GLOBALS['birkly_marketplace_http_transport'] = static function (): array {
    return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Connection timed out'];
};

$url = 'https://marketplace.birkly.cloud/api/v1/artifacts/birkly-automation/1.1.25/download';
$result = birkly_marketplace_download_artifact($url, null);
unset($GLOBALS['birkly_marketplace_http_transport'], $_SERVER['HTTP_HOST']);

if (empty($result['ok']) || empty($result['path'])) {
    $errors[] = 'Artifact download should fall back to local catalog zip when HTTP fails: '
        . ($result['error'] ?? 'unknown error');
} elseif (!empty($result['path']) && is_file($result['path'])) {
    @unlink($result['path']);
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "marketplace_local_catalog_fallback_test: OK\n";
