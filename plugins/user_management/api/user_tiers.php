<?php
/**
 * User Tiers API
 * Handles tier management operations
 */

// Include core files
require_once __DIR__ . '/../../../api/auth.php';
require_once __DIR__ . '/../../../core/plugins.php';
require_once __DIR__ . '/../../../core/csrf.php';
require_once __DIR__ . '/../includes/UserTiers.php';
require_once __DIR__ . '/../includes/UserStorage.php';

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
    $tiers = new UserTiers();
    $storage = new UserStorage();
    
    switch ($action) {
        case 'list':
            $tiersList = $storage->listTiers();
            echo json_encode([
                'success' => true,
                'tiers' => $tiersList
            ]);
            break;
            
        case 'get':
            $tier_id = $input['tier_id'] ?? $_GET['tier_id'] ?? '';
            if (empty($tier_id)) {
                http_response_code(400);
                echo json_encode(['error' => 'Tier ID is required']);
                break;
            }
            
            $tier = $storage->getTier($tier_id);
            if (!$tier) {
                http_response_code(404);
                echo json_encode(['error' => 'Tier not found']);
                break;
            }
            
            echo json_encode([
                'success' => true,
                'tier' => $tier
            ]);
            break;
            
        case 'create':
            $tierData = [
                'id' => $input['id'] ?? '',
                'name' => trim($input['name'] ?? ''),
                'description' => trim($input['description'] ?? ''),
                'permissions' => $input['permissions'] ?? [
                    'collections' => [],
                    'features' => []
                ],
                'features' => $input['features'] ?? [],
                'custom' => $input['custom'] ?? []
            ];
            
            if (empty($tierData['id']) || empty($tierData['name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Tier ID and name are required']);
                break;
            }
            
            // Check if tier already exists
            $existing = $storage->getTier($tierData['id']);
            if ($existing) {
                http_response_code(400);
                echo json_encode(['error' => 'Tier already exists']);
                break;
            }
            
            $tierData['created_at'] = date('c');
            $tierData['updated_at'] = date('c');
            
            if (!$storage->saveTier($tierData['id'], $tierData)) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create tier']);
                break;
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Tier created successfully',
                'tier' => $tierData
            ]);
            break;
            
        case 'update':
            $tier_id = $input['tier_id'] ?? '';
            if (empty($tier_id)) {
                http_response_code(400);
                echo json_encode(['error' => 'Tier ID is required']);
                break;
            }
            
            $tier = $storage->getTier($tier_id);
            if (!$tier) {
                http_response_code(404);
                echo json_encode(['error' => 'Tier not found']);
                break;
            }
            
            // Update fields
            if (isset($input['name'])) {
                $tier['name'] = trim($input['name']);
            }
            if (isset($input['description'])) {
                $tier['description'] = trim($input['description']);
            }
            if (isset($input['permissions'])) {
                $tier['permissions'] = $input['permissions'];
            }
            if (isset($input['features'])) {
                $tier['features'] = $input['features'];
            }
            if (isset($input['custom'])) {
                $tier['custom'] = $input['custom'];
            }
            
            $tier['updated_at'] = date('c');
            
            if (!$storage->saveTier($tier_id, $tier)) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update tier']);
                break;
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Tier updated successfully',
                'tier' => $tier
            ]);
            break;
            
        case 'delete':
            $tier_id = $input['tier_id'] ?? $_GET['tier_id'] ?? '';
            if (empty($tier_id)) {
                http_response_code(400);
                echo json_encode(['error' => 'Tier ID is required']);
                break;
            }
            
            $tier = $storage->getTier($tier_id);
            if (!$tier) {
                http_response_code(404);
                echo json_encode(['error' => 'Tier not found']);
                break;
            }
            
            // Don't allow deleting the "all" tier (the only default tier)
            if ($tier_id === 'all') {
                http_response_code(400);
                echo json_encode(['error' => 'Cannot delete the default "All" tier']);
                break;
            }
            
            if (!$storage->deleteTier($tier_id)) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to delete tier']);
                break;
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Tier deleted successfully'
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
