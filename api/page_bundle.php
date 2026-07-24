<?php
/**
 * page_bundle.php — batch public reads for template-first sites (M4 + P21 v2).
 *
 * POST JSON: { "language": "en", "requests": [ ... ] }
 * GET: ?language=en&requests=<base64url JSON>&lean=1
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
require_once __DIR__ . '/../core/storage/StorageFactory.php';
require_once __DIR__ . '/../core/site_domain.php';
require_once __DIR__ . '/../core/starter_content_repair.php';

birkly_run_starter_content_repairs();
birkly_ensure_site_domain_registered();

if (birkly_request_is_local_dev() || birkly_request_is_same_cms_origin()) {
    setDynamicCORS();
} else {
    requireDomainAuth('read');
    applyDomainRateLimit('read');
}

function page_bundle_respond($data, $code = 200, $etag = null) {
    if (function_exists('set_public_read_cache_headers')) {
        set_public_read_cache_headers(120);
    }
    if ($etag) {
        header('ETag: "' . $etag . '"');
    }
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function page_bundle_decode_requests($raw): array {
    if (!is_array($raw)) {
        return [];
    }
    $requests = $raw['requests'] ?? [];
    return is_array($requests) ? $requests : [];
}

$language = 'en';
$requests = [];
$lean = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = json_decode(file_get_contents('php://input'), true) ?? [];
    $language = $raw['language'] ?? 'en';
    $requests = $raw['requests'] ?? [];
    if (array_key_exists('lean', $raw)) {
        $lean = !empty($raw['lean']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $language = $_GET['language'] ?? 'en';
    $encoded = $_GET['requests'] ?? '';
    if ($encoded !== '') {
        $json = base64_decode(strtr($encoded, '-_', '+/'), true);
        $decoded = json_decode($json ?: '[]', true);
        $requests = is_array($decoded) ? $decoded : [];
    }
    if (isset($_GET['lean'])) {
        $lean = $_GET['lean'] !== '0' && $_GET['lean'] !== 'false';
    }
} else {
    page_bundle_respond(['status' => 'error', 'message' => 'POST or GET required'], 405);
}

if (!is_array($requests) || count($requests) === 0) {
    page_bundle_respond(['status' => 'error', 'message' => 'No requests'], 400);
}

if (count($requests) > 40) {
    page_bundle_respond(['status' => 'error', 'message' => 'Too many requests'], 400);
}

$storage = get_storage(__DIR__ . '/../content');
$revision = birkly_page_bundle_compute_revision($storage, $requests, $language);
$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
$ifNoneMatch = trim(str_replace('"', '', $ifNoneMatch));

if ($ifNoneMatch !== '' && $ifNoneMatch === $revision) {
    if (function_exists('set_public_read_cache_headers')) {
        set_public_read_cache_headers(120);
    }
    header('ETag: "' . $revision . '"');
    http_response_code(304);
    exit;
}

$result = birkly_run_page_bundle_requests($storage, $requests, $language, ['lean' => $lean]);
$result['revision'] = $revision;
page_bundle_respond($result, 200, $revision);
