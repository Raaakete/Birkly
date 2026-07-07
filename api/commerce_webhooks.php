<?php
/**
 * Commerce inventory webhooks — Shopify inventory_levels/update, Woo product.updated (P14-M2).
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

require_once __DIR__ . '/../plugins/commerce/init.php';

if (!commerce_plugin_active()) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Commerce not active']);
    exit;
}

require_once __DIR__ . '/../plugins/commerce/includes/CommerceImportCredentialStore.php';
require_once __DIR__ . '/../plugins/commerce/includes/CommerceInventoryWebhookService.php';

$platform = strtolower(trim((string) ($_GET['platform'] ?? '')));
if ($platform === 'woo') {
    $platform = 'woocommerce';
}

if (!in_array($platform, ['shopify', 'woocommerce'], true)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Invalid platform']);
    exit;
}

$rawBody = (string) file_get_contents('php://input');
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$credentials = new CommerceImportCredentialStore();
$config = $credentials->getPlatformConfig($platform, true);
$secret = (string) ($config['webhook_secret'] ?? '');
$service = new CommerceInventoryWebhookService();

if ($platform === 'shopify') {
    $hmac = (string) ($_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? '');
    $topic = (string) ($_SERVER['HTTP_X_SHOPIFY_TOPIC'] ?? '');
    if ($secret !== '' && !$service->verifyShopifyHmac($rawBody, $hmac, $secret)) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Invalid signature']);
        exit;
    }
    if ($topic !== '' && $topic !== 'inventory_levels/update') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'ignored' => true, 'topic' => $topic]);
        exit;
    }
    $result = $service->handleShopifyInventoryUpdate($payload);
} else {
    $signature = (string) ($_SERVER['HTTP_X_WC_WEBHOOK_SIGNATURE'] ?? '');
    if ($secret !== '' && !$service->verifyWooSignature($rawBody, $signature, $secret)) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Invalid signature']);
        exit;
    }
    $result = $service->handleWooProductUpdated($payload);
}

header('Content-Type: application/json');
echo json_encode($result);
