<?php
// api/index.php - Central API router for plugin endpoints
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
header('Content-Type: application/json');

// IMPORTANT: Use absolute paths via __DIR__.
// The PHP built-in server can have a changing working directory (cwd) across requests
// if any code calls chdir(), which makes relative requires flaky and can surface as
// intermittent 404/500 behavior.
require_once __DIR__ . '/../core/plugins.php';

// Initialize endpoints registry (always use $GLOBALS)
if (!isset($GLOBALS['birkly_api_endpoints']) || !is_array($GLOBALS['birkly_api_endpoints'])) {
    $GLOBALS['birkly_api_endpoints'] = [];
}

// Load plugins (register routes for active plugins)
load_plugins();

// Merge plugin-registered routes (haru_api_endpoints) into the router registry (birkly_api_endpoints)
// so that user_auth, user_registration, etc. are available for login/register on the demo site.
if (!empty($GLOBALS['haru_api_endpoints']) && is_array($GLOBALS['haru_api_endpoints'])) {
    foreach ($GLOBALS['haru_api_endpoints'] as $r => $file) {
        if ($file && file_exists($file)) {
            $GLOBALS['birkly_api_endpoints'][$r] = realpath($file) ?: $file;
        }
    }
}

// Fallback: ensure key user_management routes exist if plugin registered but file path was relative
$userAuthHandler = __DIR__ . '/../plugins/user_management/api/user_auth.php';
if (file_exists($userAuthHandler) && empty($GLOBALS['birkly_api_endpoints']['user_auth'])) {
    $GLOBALS['birkly_api_endpoints']['user_auth'] = realpath($userAuthHandler) ?: $userAuthHandler;
}
$userRegHandler = __DIR__ . '/../plugins/user_management/api/user_registration.php';
if (file_exists($userRegHandler) && empty($GLOBALS['birkly_api_endpoints']['user_registration'])) {
    $GLOBALS['birkly_api_endpoints']['user_registration'] = realpath($userRegHandler) ?: $userRegHandler;
}

// Resolve route (GET or POST)
$route = $_GET['route'] ?? $_POST['route'] ?? '';
if (!$route) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing route parameter']);
    exit;
}

// Route lookup
$handler = $GLOBALS['birkly_api_endpoints'][$route] ?? null;
if (!$handler) {
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'message' => 'API route not found',
        'route' => $route,
        'available_routes' => array_keys($GLOBALS['birkly_api_endpoints'])
    ]);
    exit;
}

// Handler file must exist
$handler = realpath($handler) ?: $handler;
if (!file_exists($handler)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'API handler file missing', 'handler' => $handler]);
    exit;
}

require $handler;