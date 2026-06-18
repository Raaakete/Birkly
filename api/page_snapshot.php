<?php
/**
 * page_snapshot.php — serve pre-built page bundle JSON from cache (M4).
 *
 * GET ?page=index  or  ?page=post&slug=my-slug
 * Snapshots are written by site publish pipelines; this endpoint only serves cached files.
 */
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    require_once __DIR__ . '/domain_auth.php';
    setDynamicCORS();
    http_response_code(200);
    exit;
}

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

require_once __DIR__ . '/validate.php';
set_security_headers('api');
require_once __DIR__ . '/domain_auth.php';

if (birkly_request_is_local_dev()) {
    setDynamicCORS();
} else {
    requireDomainAuth('read');
    applyDomainRateLimit('read');
}

function page_snapshot_respond($data, $code = 200, $longCache = false) {
    if (function_exists('set_public_read_cache_headers')) {
        set_public_read_cache_headers($longCache ? 3600 : 300);
    }
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    page_snapshot_respond(['status' => 'error', 'message' => 'GET required'], 405);
}

$page = trim((string)($_GET['page'] ?? ''));
$slug = trim((string)($_GET['slug'] ?? ''));

if ($page === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $page)) {
    page_snapshot_respond(['status' => 'error', 'message' => 'Invalid page'], 400);
}
if ($slug !== '' && !preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
    page_snapshot_respond(['status' => 'error', 'message' => 'Invalid slug'], 400);
}

$baseDir = realpath(__DIR__ . '/../cache/page-snapshots');
if (!$baseDir || !is_dir($baseDir)) {
    page_snapshot_respond(['status' => 'error', 'message' => 'Snapshot not found'], 404);
}

$filename = $slug !== '' ? $page . '/' . $slug . '.json' : $page . '.json';
$path = $baseDir . '/' . $filename;
$realPath = realpath($path);
if (!$realPath || strpos($realPath, $baseDir) !== 0 || !is_file($realPath)) {
    page_snapshot_respond(['status' => 'error', 'message' => 'Snapshot not found'], 404);
}

$snapshot = json_decode(file_get_contents($realPath), true);
if (!$snapshot || empty($snapshot['data']) || !is_array($snapshot['data'])) {
    page_snapshot_respond(['status' => 'error', 'message' => 'Snapshot not found'], 404);
}

page_snapshot_respond([
    'status' => 'ok',
    'page' => $page,
    'slug' => $slug !== '' ? $slug : null,
    'generated_at' => $snapshot['generated_at'] ?? null,
    'requests' => $snapshot['requests'] ?? [],
    'data' => $snapshot['data'],
    'rendered' => $snapshot['rendered'] ?? null,
    'errors' => $snapshot['errors'] ?? [],
], 200, true);
