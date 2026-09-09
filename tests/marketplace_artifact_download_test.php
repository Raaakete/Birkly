<?php
/**
 * Artifact downloads succeed from cloud when local marketplace is offline.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/marketplace.php';
require_once $root . '/core/marketplace/install.php';

$errors = [];

$_SERVER['HTTP_HOST'] = 'localhost:8080';
$url = 'https://marketplace.birkly.cloud/api/v1/artifacts/birkly-automation/1.1.31/download';

$start = microtime(true);
$result = birkly_marketplace_download_artifact($url, null);
$elapsedMs = (int) round((microtime(true) - $start) * 1000);
unset($_SERVER['HTTP_HOST']);

if (empty($result['ok']) || empty($result['path'])) {
    $errors[] = 'Artifact download should succeed from cloud when local marketplace is offline: '
        . ($result['error'] ?? 'unknown error');
}
if ($elapsedMs > 15000) {
    $errors[] = 'Artifact download should not spend 15s+ probing offline local hosts, took ' . $elapsedMs . 'ms';
}

if (!empty($result['path']) && is_file($result['path'])) {
    @unlink($result['path']);
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "marketplace_artifact_download_test: OK ({$elapsedMs}ms)\n";
