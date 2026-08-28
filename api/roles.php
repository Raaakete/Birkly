<?php
require_once __DIR__ . '/../core/admin.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/roles.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/log.php';

// Only set headers if this file is being called directly (not included)
// When included via admin/index.php, headers are already sent, so skip
if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    header('Content-Type: application/json');
}

$action = $_REQUEST['action'] ?? '';
$storage = new Storage(__DIR__ . '/../');
$rolesFile = 'settings/roles.json';

function requireAuthRole($perm) {
    requireAuth($perm);
}

if ($action === 'list_roles') {
    requireAuthRole('manage_users');
    $roles = birkly_ensure_default_roles();
    echo json_encode(['status' => 'ok', 'roles' => $roles]);
    exit;
}

if ($action === 'list_role_plugins') {
    requireAuthRole('manage_users');
    if (!function_exists('get_all_plugins')) {
        require_once __DIR__ . '/../core/plugins.php';
    }
    $plugins = [];
    foreach (get_all_plugins() as $id => $plugin) {
        if (empty($plugin['active'])) {
            continue;
        }
        $plugins[] = [
            'id' => (string) $id,
            'name' => (string) ($plugin['name'] ?? $id),
        ];
    }
    echo json_encode(['status' => 'ok', 'plugins' => $plugins]);
    exit;
}

if ($action === 'get_role') {
    requireAuthRole('manage_users');
    $id = $_REQUEST['id'] ?? '';
    $roles = $storage->read($rolesFile) ?: [];
    foreach ($roles as $role) {
        if ($role['id'] === $id) {
            echo json_encode(['status' => 'ok', 'role' => $role]);
            exit;
        }
    }
    echo json_encode(['status' => 'error', 'message' => 'Role not found']);
    exit;
}

if ($action === 'create_role') {
    requireAuthRole('manage_users');
    $input = json_decode(file_get_contents('php://input'), true);
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    $roles = $storage->read($rolesFile) ?: [];
    $roles[] = $input['role'];
    $storage->write($rolesFile, $roles);
    $roleId = (string) ($input['role']['id'] ?? '');
    log_security_event('role_created', [
        'role_id' => $roleId,
        'user' => $_SESSION['user']['email'] ?? 'unknown',
    ]);
    echo json_encode(['status' => 'ok']);
    exit;
}

if ($action === 'update_role') {
    requireAuthRole('manage_users');
    $input = json_decode(file_get_contents('php://input'), true);
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    $roles = $storage->read($rolesFile) ?: [];
    foreach ($roles as &$role) {
        if ($role['id'] === $input['role']['id']) {
            $role = $input['role'];
            break;
        }
    }
    unset($role);
    $storage->write($rolesFile, $roles);
    log_security_event('role_updated', [
        'role_id' => (string) ($input['role']['id'] ?? ''),
        'user' => $_SESSION['user']['email'] ?? 'unknown',
    ]);
    echo json_encode(['status' => 'ok']);
    exit;
}

if ($action === 'delete_role') {
    requireAuthRole('manage_users');
    $input = json_decode(file_get_contents('php://input'), true);
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    $roles = $storage->read($rolesFile) ?: [];
    $roles = array_filter($roles, function($role) use ($input) {
        return $role['id'] !== $input['id'];
    });
    $storage->write($rolesFile, array_values($roles));
    log_security_event('role_deleted', [
        'role_id' => (string) ($input['id'] ?? ''),
        'user' => $_SESSION['user']['email'] ?? 'unknown',
    ]);
    echo json_encode(['status' => 'ok']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action']); 