<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
// auth.php: Session authentication and permission helpers for Birkly

require_once 'storage.php';
require_once 'session.php';
// Load ai_helpers for getPermissionsForRole if available
if (file_exists(__DIR__ . '/ai_helpers.php')) {
    require_once __DIR__ . '/ai_helpers.php';
}

function get_security_settings() {
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $settingsStorage = new Storage(__DIR__ . '/../settings');
    $file = $settingsStorage->read('security.json') ?? [];

    $settings = array_merge([
        'session_timeout' => 3600,
        'password_min_length' => 8,
        'require_strong_password' => true,
        'max_login_attempts' => 5,
        'lockout_time' => 900,
        'enable_csrf' => true,
    ], $file);

    return $settings;
}

function getSessionTimeout() {
    $security = get_security_settings();
    return (int) ($security['session_timeout'] ?? 3600);
}

function requireAuth($permission = null) {
    // Core CMS API auth is for the admin panel, not public site users.
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

function permissionGranted(array $permissions, string $permission): bool
{
    if (in_array('*', $permissions, true) || in_array($permission, $permissions, true)) {
        return true;
    }

    // Higher privileges imply lower-level read permissions.
    $impliedBy = [
        'read_media' => ['manage_media'],
        'read_settings' => ['manage_settings'],
        'list_users' => ['manage_users'],
    ];

    foreach ($impliedBy[$permission] ?? [] as $parent) {
        if (in_array($parent, $permissions, true)) {
            return true;
        }
    }

    return false;
}

function checkPermission($user, $permission) {
    if (!isset($user['role'])) return false;

    if (isset($user['permissions']) && is_array($user['permissions'])) {
        if (permissionGranted($user['permissions'], $permission)) {
            return true;
        }
    }
    
    // Use getPermissionsForRole from ai_helpers.php if available
    // Otherwise fall back to legacy hardcoded roles
    if (function_exists('getPermissionsForRole')) {
        $userPermissions = getPermissionsForRole($user['role']);
    } else {
        // Fallback to legacy hardcoded roles
        $roles = [
            'admin' => ['*'], // Admin has all permissions
            'editor' => [
                'manage_content', 'manage_media', 'manage_plugins', 'read', 'write', 'delete', 'upload', 'read_media'
            ],
            'author' => [
                'manage_content', 'manage_media', 'read', 'write', 'upload', 'read_media'
            ],
            'viewer' => ['read', 'read_media']
        ];
        $userPermissions = $roles[$user['role']] ?? [];
    }
    
    return permissionGranted($userPermissions, $permission);
} 