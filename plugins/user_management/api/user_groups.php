<?php
/**
 * User Groups API
 * Handles group management operations
 */

// Include core files
require_once __DIR__ . '/../../../api/auth.php';
require_once __DIR__ . '/../../../core/plugins.php';
require_once __DIR__ . '/../../../core/csrf.php';
require_once __DIR__ . '/../includes/UserGroups.php';
require_once __DIR__ . '/../includes/UserAuth.php';

// Admin authentication check
if (function_exists('requireAuth')) {
    requireAuth('manage_users');
} elseif (function_exists('requireAdminAuth')) {
    requireAdminAuth();
}

// Get request method and input
$method = $_SERVER['REQUEST_METHOD'];
$input = [];
if ($method === 'POST' && !empty($_POST)) {
    $input = $_POST;
} elseif ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? [];
}

$action = $input['action'] ?? $_GET['action'] ?? 'list';

// CSRF protection for POST requests
if ($method === 'POST') {
    $token = $input['csrf_token'] ?? $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (function_exists('validate_csrf_token')) {
        if (!validate_csrf_token($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }
    }
}

header('Content-Type: application/json');

try {
    $groups = new UserGroups();
    $auth = new UserAuth();
    $currentUser = $auth->getCurrentUser();
    
    switch ($action) {
        case 'list':
            $filters = [];
            if (isset($_GET['owner_id'])) {
                $filters['owner_id'] = $_GET['owner_id'];
            }
            if (isset($_GET['type'])) {
                $filters['type'] = $_GET['type'];
            }
            
            $groupsList = $groups->listGroups($filters);
            echo json_encode([
                'success' => true,
                'groups' => $groupsList
            ]);
            break;
            
        case 'get':
            $group_id = $input['group_id'] ?? $_GET['group_id'] ?? '';
            if (empty($group_id)) {
                http_response_code(400);
                echo json_encode(['error' => 'Group ID is required']);
                break;
            }
            
            $group = $groups->getGroup($group_id);
            if (!$group) {
                http_response_code(404);
                echo json_encode(['error' => 'Group not found']);
                break;
            }
            
            echo json_encode([
                'success' => true,
                'group' => $group
            ]);
            break;
            
        case 'create':
            $groupData = [
                'name' => trim($input['name'] ?? ''),
                'description' => trim($input['description'] ?? ''),
                'type' => $input['type'] ?? 'private',
                'owner_id' => $currentUser['id'] ?? $input['owner_id'] ?? null,
                'members' => $input['members'] ?? [],
                'permissions' => $input['permissions'] ?? [],
                'settings' => $input['settings'] ?? []
            ];
            
            if (empty($groupData['name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Group name is required']);
                break;
            }
            
            $group = $groups->createGroup($groupData);
            echo json_encode([
                'success' => true,
                'message' => 'Group created successfully',
                'group' => $group
            ]);
            break;
            
        case 'update':
            $group_id = $input['group_id'] ?? '';
            if (empty($group_id)) {
                http_response_code(400);
                echo json_encode(['error' => 'Group ID is required']);
                break;
            }
            
            $group = $groups->getGroup($group_id);
            if (!$group) {
                http_response_code(404);
                echo json_encode(['error' => 'Group not found']);
                break;
            }
            
            // Check permission (owner or admin)
            if ($group['owner_id'] !== $currentUser['id'] && ($currentUser['role'] ?? '') !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Permission denied']);
                break;
            }
            
            $updateData = [];
            if (isset($input['name'])) {
                $updateData['name'] = trim($input['name']);
            }
            if (isset($input['description'])) {
                $updateData['description'] = trim($input['description']);
            }
            if (isset($input['type'])) {
                $updateData['type'] = $input['type'];
            }
            if (isset($input['permissions'])) {
                $updateData['permissions'] = $input['permissions'];
            }
            if (isset($input['settings'])) {
                $updateData['settings'] = $input['settings'];
            }
            
            $group = $groups->updateGroup($group_id, $updateData);
            echo json_encode([
                'success' => true,
                'message' => 'Group updated successfully',
                'group' => $group
            ]);
            break;
            
        case 'delete':
            $group_id = $input['group_id'] ?? $_GET['group_id'] ?? '';
            if (empty($group_id)) {
                http_response_code(400);
                echo json_encode(['error' => 'Group ID is required']);
                break;
            }
            
            $group = $groups->getGroup($group_id);
            if (!$group) {
                http_response_code(404);
                echo json_encode(['error' => 'Group not found']);
                break;
            }
            
            // Check permission (owner or admin)
            if ($group['owner_id'] !== $currentUser['id'] && ($currentUser['role'] ?? '') !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Permission denied']);
                break;
            }
            
            $groups->deleteGroup($group_id);
            echo json_encode([
                'success' => true,
                'message' => 'Group deleted successfully'
            ]);
            break;
            
        case 'add_member':
            $group_id = $input['group_id'] ?? '';
            $user_id = $input['user_id'] ?? '';
            
            if (empty($group_id) || empty($user_id)) {
                http_response_code(400);
                echo json_encode(['error' => 'Group ID and User ID are required']);
                break;
            }
            
            $group = $groups->getGroup($group_id);
            if (!$group) {
                http_response_code(404);
                echo json_encode(['error' => 'Group not found']);
                break;
            }
            
            // Check permission
            if ($group['owner_id'] !== $currentUser['id'] && ($currentUser['role'] ?? '') !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Permission denied']);
                break;
            }
            
            $groups->addMember($group_id, $user_id);
            echo json_encode([
                'success' => true,
                'message' => 'Member added successfully'
            ]);
            break;
            
        case 'remove_member':
            $group_id = $input['group_id'] ?? '';
            $user_id = $input['user_id'] ?? '';
            
            if (empty($group_id) || empty($user_id)) {
                http_response_code(400);
                echo json_encode(['error' => 'Group ID and User ID are required']);
                break;
            }
            
            $group = $groups->getGroup($group_id);
            if (!$group) {
                http_response_code(404);
                echo json_encode(['error' => 'Group not found']);
                break;
            }
            
            // Check permission
            if ($group['owner_id'] !== $currentUser['id'] && ($currentUser['role'] ?? '') !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Permission denied']);
                break;
            }
            
            $groups->removeMember($group_id, $user_id);
            echo json_encode([
                'success' => true,
                'message' => 'Member removed successfully'
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
