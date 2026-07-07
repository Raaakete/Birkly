<?php
require_once __DIR__ . '/../core/admin.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/roles.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';

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
    $storage->write($rolesFile, $roles);
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
    echo json_encode(['status' => 'ok']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action']); 