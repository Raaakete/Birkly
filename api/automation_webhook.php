<?php
/**
 * Automation Webhook Inbound Trigger for Birkly
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

header('Content-Type: application/json');

require_once __DIR__ . '/../core/plugin_availability.php';
if (!birkly_bootstrap_active_plugin('automation')) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Automation plugin is not active']);
    exit;
}

require_once __DIR__ . '/../core/storage/StorageFactory.php';

$storage = StorageFactory::createStorage(__DIR__ . '/../settings');
$webhook_config = $storage->read('automation_webhook_config.json') ?? [];

$webhook_token = $_GET['token'] ?? $_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? '';
$webhook_id = $_GET['id'] ?? $_SERVER['HTTP_X_WEBHOOK_ID'] ?? '';

if (empty($webhook_token) || empty($webhook_id)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Webhook token and ID required']);
    exit;
}

$webhook = null;
foreach ($webhook_config as $config) {
    if (($config['id'] ?? '') === $webhook_id && ($config['token'] ?? '') === $webhook_token) {
        $webhook = $config;
        break;
    }
}

if (!$webhook) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid webhook credentials']);
    exit;
}

if (!($webhook['active'] ?? true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Webhook is inactive']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $payload = $_POST;
}

$rate_limit = $webhook['rate_limit'] ?? 100;
if (function_exists('check_automation_rate_limit')) {
    $rate_limit_key = 'webhook_' . $webhook_id;
    if (!check_automation_rate_limit($rate_limit_key, $rate_limit)) {
        http_response_code(429);
        echo json_encode(['status' => 'error', 'message' => 'Rate limit exceeded']);
        exit;
    }
}

$context = array_merge($payload ?? [], [
    'webhook_id' => $webhook_id,
    'webhook_name' => $webhook['name'] ?? '',
    'triggered_at' => date('c'),
    'triggered_by' => 'webhook',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
]);

enqueue_automation('webhook_inbound', $context);

if (function_exists('log_automation_execution')) {
    log_automation_execution('webhook_' . $webhook_id, $context, [], []);
}

echo json_encode([
    'status' => 'ok',
    'message' => 'Webhook received and queued',
    'webhook_id' => $webhook_id
]);
