<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
require_once 'session.php';
haru_secure_session_start();
header('Content-Type: application/json');
require_once '../core/csrf.php';

echo json_encode([
    'status' => 'ok',
    'data' => [
        'csrf_token' => get_csrf_token()
    ]
]); 