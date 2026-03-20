<?php
/**
 * Plugin Permissions API for Birkly
 * 
 * Handles plugin permission and denylist management.
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/session.php';
haru_secure_session_start();

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../core/plugins.php';
require_once __DIR__ . '/../core/plugins/capabilities.php';
require_once __DIR__ . '/../core/plugins/permissions.php';
require_once __DIR__ . '/../core/csrf.php';

// Require admin authentication
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

requireAuth('manage_plugins');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

// Get plugin permissions
if ($action === 'get_permissions') {
    $plugin_id = $input['plugin_id'] ?? $_GET['plugin_id'] ?? '';
    
    if (empty($plugin_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Plugin ID required']);
        exit;
    }
    
    $permissions = get_plugin_permissions($plugin_id);
    $declared = get_plugin_declared_capabilities($plugin_id);
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'permissions' => $permissions,
            'declared' => $declared
        ]
    ]);
    exit;
}

// Get all plugins with their permissions
if ($action === 'list_plugins_permissions') {
    $plugins = get_all_plugins();
    $result = [];
    
    foreach ($plugins as $plugin_id => $plugin) {
        $permissions = get_plugin_permissions($plugin_id);
        $declared = get_plugin_declared_capabilities($plugin_id);
        
        $result[] = [
            'id' => $plugin_id,
            'name' => $plugin['name'] ?? $plugin_id,
            'version' => $plugin['version'] ?? '1.0.0',
            'active' => $plugin['active'] ?? false,
            'declared_capabilities' => $declared,
            'granted_capabilities' => $permissions,
            'has_permissions' => plugin_has_any_capabilities($plugin_id)
        ];
    }
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'plugins' => $result
        ]
    ]);
    exit;
}

// Grant capability
if ($action === 'grant_capability') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $plugin_id = $input['plugin_id'] ?? '';
    $capability = $input['capability'] ?? '';
    $scope = $input['scope'] ?? 'global';
    
    if (empty($plugin_id) || empty($capability)) {
        echo json_encode(['status' => 'error', 'message' => 'Plugin ID and capability required']);
        exit;
    }
    
    $result = grant_plugin_capability($plugin_id, $capability, $scope);
    
    if ($result) {
        echo json_encode(['status' => 'ok', 'message' => 'Capability granted']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to grant capability']);
    }
    exit;
}

// Revoke capability
if ($action === 'revoke_capability') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $plugin_id = $input['plugin_id'] ?? '';
    $capability = $input['capability'] ?? '';
    $scope = $input['scope'] ?? 'global';
    
    if (empty($plugin_id) || empty($capability)) {
        echo json_encode(['status' => 'error', 'message' => 'Plugin ID and capability required']);
        exit;
    }
    
    $result = revoke_plugin_capability($plugin_id, $capability, $scope);
    
    if ($result) {
        echo json_encode(['status' => 'ok', 'message' => 'Capability revoked']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to revoke capability']);
    }
    exit;
}

// Get denylist
if ($action === 'get_denylist') {
    $denylist = get_plugin_denylist();
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'resources' => $denylist
        ]
    ]);
    exit;
}

// Set denylist
if ($action === 'set_denylist') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $resources = $input['resources'] ?? [];
    
    if (!is_array($resources)) {
        echo json_encode(['status' => 'error', 'message' => 'Resources must be an array']);
        exit;
    }
    
    $result = set_plugin_denylist($resources);
    
    if ($result) {
        echo json_encode(['status' => 'ok', 'message' => 'Denylist updated']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update denylist']);
    }
    exit;
}

// Add to denylist
if ($action === 'add_to_denylist') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $resource = $input['resource'] ?? '';
    
    if (empty($resource)) {
        echo json_encode(['status' => 'error', 'message' => 'Resource required']);
        exit;
    }
    
    $result = add_to_denylist($resource);
    
    if ($result) {
        echo json_encode(['status' => 'ok', 'message' => 'Resource added to denylist']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to add resource']);
    }
    exit;
}

// Remove from denylist
if ($action === 'remove_from_denylist') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $resource = $input['resource'] ?? '';
    
    if (empty($resource)) {
        echo json_encode(['status' => 'error', 'message' => 'Resource required']);
        exit;
    }
    
    $result = remove_from_denylist($resource);
    
    if ($result) {
        echo json_encode(['status' => 'ok', 'message' => 'Resource removed from denylist']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to remove resource']);
    }
    exit;
}

// Default response
echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
