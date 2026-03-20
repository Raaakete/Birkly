<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
require_once 'session.php';
// IMPORTANT:
// This endpoint is used by both:
// - Admin panel JS (should use admin session)
// - Public website client library (should use site session)
//
// To avoid leaking admin cookies onto public pages, we detect scope:
// - If the request already has an admin session cookie, use admin scope.
// - Otherwise use site scope.
$hasAdminCookie = isset($_COOKIE['birkly_admin_sid']) && $_COOKIE['birkly_admin_sid'] !== '';
if ($hasAdminCookie) {
    haru_secure_session_start();
} else {
    birkly_site_session_start();
}
header('Content-Type: application/json');
require_once '../core/csrf.php';

echo json_encode([
    'status' => 'ok',
    'data' => [
        'csrf_token' => get_csrf_token()
    ]
]); 