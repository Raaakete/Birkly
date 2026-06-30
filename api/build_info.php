<?php
/**
 * Public build stamp for deploy verification (no auth).
 */
header('Content-Type: application/json');
header('Cache-Control: no-store');

$versionFile = __DIR__ . '/../VERSION';
$version = is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : 'unknown';

echo json_encode([
    'status' => 'ok',
    'version' => $version,
    'php' => PHP_VERSION,
    'helpers' => is_file(__DIR__ . '/admin_content_helpers.php'),
], JSON_UNESCAPED_SLASHES);
