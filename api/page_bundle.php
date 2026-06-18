<?php
/**
 * page_bundle.php — batch public reads for template-first sites (M4).
 *
 * POST JSON: { "language": "en", "requests": [ {"type":"list_entries","into":"blog","collection":"blog"} ] }
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
require_once __DIR__ . '/../core/page_bundle_runner.php';

if (birkly_request_is_local_dev()) {
    setDynamicCORS();
} else {
    requireDomainAuth('read');
    applyDomainRateLimit('read');
}

function page_bundle_respond($data, $code = 200) {
    if (function_exists('set_public_read_cache_headers')) {
        set_public_read_cache_headers(120);
    }
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    page_bundle_respond(['status' => 'error', 'message' => 'POST required'], 405);
}

$raw = json_decode(file_get_contents('php://input'), true) ?? [];
$language = $raw['language'] ?? 'en';
$requests = $raw['requests'] ?? [];

if (!is_array($requests) || count($requests) === 0) {
    page_bundle_respond(['status' => 'error', 'message' => 'No requests'], 400);
}

if (count($requests) > 40) {
    page_bundle_respond(['status' => 'error', 'message' => 'Too many requests'], 400);
}

$storage = get_storage(__DIR__ . '/../content');
$result = birkly_run_page_bundle_requests($storage, $requests, $language);
page_bundle_respond($result);
