<?php
/**
 * Public read-only AI Presence API (P46 + P47 views).
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
require_once __DIR__ . '/../../core/ai_presence_pro.php';

$action = $_GET['action'] ?? 'brand';
$view = trim((string) ($_GET['view'] ?? 'full'));

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

if ($action === 'brand') {
    $body = birkly_ai_presence_public_view($export, $view);
    echo json_encode(['status' => 'ok', 'data' => $body], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'product') {
    $id = trim((string) ($_GET['id'] ?? ''));
    $found = null;
    foreach ($export['products_services'] ?? [] as $product) {
        if (!is_array($product)) {
            continue;
        }
        if (($product['id'] ?? '') === $id || ($product['name'] ?? '') === $id) {
            if (!isset($product['public']) || $product['public']) {
                $found = $product;
            }
            break;
        }
    }
    if ($found === null) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Product not found']);
        exit;
    }
    echo json_encode(['status' => 'ok', 'data' => $found], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'claim') {
    $id = trim((string) ($_GET['id'] ?? ''));
    $found = null;
    foreach ($export['claims'] ?? [] as $claim) {
        if (!is_array($claim) || ($claim['id'] ?? '') !== $id) {
            continue;
        }
        if (($claim['status'] ?? '') === 'supported') {
            $found = $claim;
        }
        break;
    }
    if ($found === null) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Claim not found']);
        exit;
    }
    echo json_encode(['status' => 'ok', 'data' => $found], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
