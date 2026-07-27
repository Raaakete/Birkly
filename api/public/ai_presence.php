<?php
/**
 * Public read-only AI Presence API (P46 + P47 views + cross-link graph).
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
require_once __DIR__ . '/../../core/ai_presence_links.php';

$action = $_GET['action'] ?? 'brand';
$view = trim((string) ($_GET['view'] ?? 'full'));
$graph = !empty($_GET['graph']);

$published = birkly_ai_presence_load_published();
if ($published === null || empty($published['publication_settings']['enabled'])) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Agent Guide not published or disabled']);
    exit;
}

$meta = birkly_ai_presence_load_meta();
$version = (int) ($meta['published_version'] ?? 0);
$at = (string) ($meta['last_published_at'] ?? date('c'));
$export = birkly_ai_presence_build_public_export($published, $version, $at);

header('Cache-Control: public, max-age=300');
header('X-Agent-Guide-Version: ' . $version);
header('X-AI-Presence-Version: ' . $version);

if ($action === 'brand' || $action === 'guide') {
    $body = birkly_ai_presence_public_view($export, $view);
    echo json_encode(['status' => 'ok', 'data' => $body], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'product') {
    $id = trim((string) ($_GET['id'] ?? ''));
    if ($graph) {
        $found = birkly_ai_presence_public_product_graph($export, $id);
        if ($found === null) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Product not found']);
            exit;
        }
        echo json_encode(['status' => 'ok', 'data' => $found], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
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
    if ($graph) {
        $found = birkly_ai_presence_public_claim_graph($export, $id);
        if ($found === null) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Claim not found']);
            exit;
        }
        echo json_encode(['status' => 'ok', 'data' => $found], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
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

if ($action === 'source') {
    $url = trim((string) ($_GET['url'] ?? ''));
    if ($url === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'url parameter required']);
        exit;
    }
    $matches = [];
    foreach ($export['products_services'] ?? [] as $product) {
        if (!is_array($product)) {
            continue;
        }
        foreach ($product['sources'] ?? [] as $src) {
            if (is_array($src) && ($src['url'] ?? '') === $url) {
                $matches[] = ['scope' => 'product', 'id' => $product['id'] ?? '', 'label' => $product['name'] ?? '', 'source' => $src];
            }
        }
    }
    foreach ($export['evidence'] ?? [] as $ev) {
        if (is_array($ev) && ($ev['url'] ?? '') === $url) {
            $matches[] = ['scope' => 'evidence', 'id' => $ev['id'] ?? '', 'label' => $ev['title'] ?? '', 'source' => $ev];
        }
    }
    echo json_encode(['status' => 'ok', 'data' => ['url' => $url, 'matches' => $matches]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
