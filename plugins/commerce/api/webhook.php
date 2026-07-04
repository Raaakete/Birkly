<?php
/**
 * Public webhook handler — routed per provider query param.
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../../logs/php_errors.log');

require_once __DIR__ . '/../../../core/plugins.php';
load_plugins();

require_once __DIR__ . '/../init.php';

if (!commerce_plugin_active()) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Commerce plugin is not active']);
    exit;
}

$provider = $_GET['provider'] ?? '';
if ($provider === '') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Missing provider parameter']);
    exit;
}

$router = payments_webhook_router();
if (!$router) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Webhook router unavailable']);
    exit;
}

$payload = file_get_contents('php://input');
$headers = function_exists('getallheaders') ? getallheaders() : [];
if (empty($headers)) {
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0) {
            $headers[str_replace('_', '-', substr($key, 5))] = $value;
        }
    }
}

$result = $router->handle($provider, $payload, $headers);

http_response_code($result['status'] ?? 200);
header('Content-Type: application/json');
echo json_encode([
    'status' => ($result['ok'] ?? false) ? 'ok' : 'error',
    'duplicate' => $result['duplicate'] ?? false,
    'message' => $result['error'] ?? 'Processed',
]);
