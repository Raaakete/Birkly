<?php
/**
 * Public read-only AI Presence API (P46).
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');

header('Content-Type: application/json');

require_once __DIR__ . '/../validate.php';
set_security_headers('api');
require_once __DIR__ . '/../../core/cors.php';
set_cors_headers('public');
require_once __DIR__ . '/../../core/ai_presence.php';

$action = $_GET['action'] ?? 'brand';

if ($action !== 'brand') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
    exit;
}

$published = birkly_ai_presence_load_published();
if ($published === null || empty($published['publication_settings']['enabled'])) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'AI Presence not published or disabled']);
    exit;
}

$meta = birkly_ai_presence_load_meta();
$version = (int) ($meta['published_version'] ?? 0);
$at = (string) ($meta['last_published_at'] ?? date('c'));
$export = birkly_ai_presence_build_public_export($published, $version, $at);

header('Cache-Control: public, max-age=300');
header('X-AI-Presence-Version: ' . $version);

echo json_encode(['status' => 'ok', 'data' => $export], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
