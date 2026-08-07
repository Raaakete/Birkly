<?php
/**
 * Read-only setup environment probe (no auth).
 * Used by START_HERE.html to verify PHP and writable directories.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../core/birkly_paths.php';

$root = birkly_project_root();
$writable = birkly_ensure_runtime_dirs($root);

$extensions = ['json', 'mbstring', 'curl', 'openssl'];
$loaded = [];
foreach ($extensions as $ext) {
    $loaded[$ext] = extension_loaded($ext);
}

$langFile = $root . '/lang/en.json';

echo json_encode([
    'status' => 'ok',
    'data' => [
        'php_version' => PHP_VERSION,
        'php_ok' => version_compare(PHP_VERSION, '8.0.0', '>='),
        'sapi' => PHP_SAPI,
        'extensions' => $loaded,
        'writable_dirs' => $writable,
        'has_lang_en' => is_file($langFile),
        'has_server_router' => is_file($root . '/server-router.php'),
        'has_start_server' => is_file($root . '/start-server.sh'),
        'has_admin' => is_dir($root . '/admin'),
        'timestamp' => date('c'),
    ],
], JSON_PRETTY_PRINT);
