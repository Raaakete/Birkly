<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
// auth.php: Session authentication and permission helpers for HaruCMS

require_once 'storage.php';
require_once 'session.php';

function getSessionTimeout() {
    // Load session timeout from settings, fallback to 3600s
    $settingsStorage = new Storage('../settings');
    $security = $settingsStorage->read('security.json') ?? [];
    return isset($security['session_timeout']) ? (int)$security['session_timeout'] : 3600;
}

function requireAuth($permission = null) {
    haru_secure_session_start();
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    // Session timeout check
    $timeout = getSessionTimeout();
    $now = time();
    if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Session expired']);
        exit;
    }
    $_SESSION['last_activity'] = $now;
    // Permission check
    if ($permission && !checkPermission($_SESSION['user'], $permission)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }
}

function checkPermission($user, $permission) {
    if (!isset($user['role'])) return false;
    $roles = [
        'admin' => ['*'], // Admin has all permissions
        'editor' => [
            'manage_content', 'manage_media', 'manage_plugins', 'read', 'write', 'delete', 'upload'
        ],
        'author' => [
            'manage_content', 'manage_media', 'read', 'write', 'upload'
        ],
        'viewer' => ['read']
    ];
    $userPermissions = $roles[$user['role']] ?? [];
    return in_array('*', $userPermissions) || in_array($permission, $userPermissions);
} 