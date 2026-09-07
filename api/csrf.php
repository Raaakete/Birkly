<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

require_once __DIR__ . '/domain_auth.php';
setDynamicCORS();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'session.php';
// IMPORTANT:
// This endpoint is used by both:
// - Admin panel JS (default PHP session / PHPSESSID)
// - Public website client library (birkly_site_sid)
//
// Scope is resolved in birkly_csrf_session_start() so onboarding and admin
// share the same CSRF session as settings.php and other admin APIs.
birkly_csrf_session_start();
header('Content-Type: application/json');
require_once '../core/csrf.php';

echo json_encode([
    'status' => 'ok',
    'data' => [
        'csrf_token' => get_csrf_token()
    ]
]); 