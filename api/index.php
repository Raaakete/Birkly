<?php
// api/index.php - Central API router for plugin endpoints
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
header('Content-Type: application/json');

require_once '../core/plugins.php';

$route = $_GET['route'] ?? $_POST['route'] ?? '';
global $haru_api_endpoints;

if (!$route) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing route parameter']);
    exit;
}

if (!isset($haru_api_endpoints[$route])) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'API route not found']);
    exit;
}

$handler = $haru_api_endpoints[$route];
if (!file_exists($handler)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'API handler file missing']);
    exit;
}

require $handler; 