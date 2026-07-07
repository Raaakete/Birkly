<?php
/**
 * Read-only setup environment probe (no auth).
 * Used by START_HERE.html to verify PHP and writable directories.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');

$root = dirname(__DIR__);
$writableDirs = ['content', 'media', 'users', 'settings', 'logs', 'temp'];
$writable = [];
foreach ($writableDirs as $dir) {
    $path = $root . '/' . $dir;
    if (!is_dir($path)) {
        $writable[$dir] = ['exists' => false, 'writable' => false];
        continue;
    }
    $writable[$dir] = [
        'exists' => true,
        'writable' => is_writable($path),
    ];
}

$extensions = ['json', 'mbstring', 'curl', 'openssl'];
$loaded = [];
foreach ($extensions as $ext) {
    $loaded[$ext] = extension_loaded($ext);
}

echo json_encode([
    'status' => 'ok',
    'data' => [
        'php_version' => PHP_VERSION,
        'php_ok' => version_compare(PHP_VERSION, '8.0.0', '>='),
        'sapi' => PHP_SAPI,
        'extensions' => $loaded,
        'writable_dirs' => $writable,
        'has_server_router' => is_file($root . '/server-router.php'),
        'has_start_server' => is_file($root . '/start-server.sh'),
        'has_admin' => is_dir($root . '/admin'),
        'timestamp' => date('c'),
    ],
], JSON_PRETTY_PRINT);
