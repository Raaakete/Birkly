<?php
/**
 * User Admin API
 * 
 * Phase 5 implementation
 */

// Prevent direct execution if this file is being included
if (!defined('HARU_API_REQUEST')) {
    define('HARU_API_REQUEST', true);
}

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../../logs/php_errors.log');

// Only set headers if this file is being called directly (not included)
if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    header('Content-Type: application/json');
}

// Load core dependencies
require_once __DIR__ . '/../../../api/auth.php';
require_once __DIR__ . '/../../../api/session.php';
require_once __DIR__ . '/../../../core/csrf.php';
require_once __DIR__ . '/../../../core/plugins.php';

// Check if plugin is active
// Note: This check happens AFTER the route is found by the API router
// So if we get here, the route was registered, but we should still check plugin state
if (!function_exists('get_plugin_state') || !get_plugin_state('user_management')) {
    // Don't exit - let the route handler deal with it
    // This allows the route to be registered even if plugin is temporarily inactive
    // The individual actions will handle authentication and plugin state
}

// Admin authentication check
if (function_exists('requireAuth')) {
    requireAuth('admin');
} elseif (function_exists('requireAdminAuth')) {
    requireAdminAuth();
}

// Load plugin classes
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/UserPermissions.php';
require_once __DIR__ . '/../includes/UserStorage.php';
require_once __DIR__ . '/../includes/UserAuth.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Get JSON input if available
$input = [];
if ($method === 'POST' && !empty($_POST)) {
    $input = $_POST;
} elseif ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? [];
}

// Get action from GET, POST, or JSON input (GET first for GET requests)
// Also check REQUEST_URI for action parameter (in case GET params aren't parsed correctly)
$action = $_GET['action'] ?? $_POST['action'] ?? $input['action'] ?? 'list';
if (empty($action) && isset($_SERVER['REQUEST_URI'])) {
    $queryString = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
    if ($queryString) {
        parse_str($queryString, $queryParams);
        if (isset($queryParams['action'])) {
            $action = $queryParams['action'];
        }
    }
}
if (empty($action)) {
    $action = 'list';
}

// CSRF protection (check after parsing JSON input)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF token from JSON input, POST data, or GET params
    $token = $input['csrf_token'] ?? $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    
    // Use validate_csrf_token if available, otherwise skip CSRF check
    if (function_exists('validate_csrf_token')) {
        if (!validate_csrf_token($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }
    } elseif (function_exists('verify_csrf_token')) {
        if (!verify_csrf_token($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }
    }
}

try {
    // Log for debugging (remove in production)
    if (isset($_GET['debug'])) {
        error_log("User Admin API - Action: $action, Method: $method, Input: " . json_encode($input));
    }
    
    $service = new UserService();
    $permissions = new UserPermissions();
    $storage = new UserStorage();
    
    switch ($action) {
        case 'list':
            // IMPORTANT: default to hiding soft-deleted users.
            // Delete is implemented as soft delete (deleted=true), so if we don't filter here
            // users will appear to "not delete" in the UI.
            $filters = ['deleted' => false];

            // Optional override: allow explicitly listing deleted users.
            // - ?include_deleted=1  -> includes deleted users
            // - ?deleted=1 / ?deleted=0 -> explicit filter
            if (isset($_GET['include_deleted']) && ($_GET['include_deleted'] === '1' || $_GET['include_deleted'] === 'true')) {
                unset($filters['deleted']);
            } elseif (isset($_GET['deleted'])) {
                $filters['deleted'] = ($_GET['deleted'] === '1' || $_GET['deleted'] === 'true');
            }
            if (isset($_GET['role'])) {
                $filters['role'] = $_GET['role'];
            }
            if (isset($_GET['status'])) {
                $filters['status'] = $_GET['status'];
            }
            
            try {
                if (isset($_GET['search'])) {
                    // Search will be handled by searchUsers method
                    $query = $_GET['search'];
                    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
                    $users = $service->searchUsers($query, $limit);
                    $total = count($users);
                } else {
                    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
                    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
                    
                    $users = $service->listUsers($filters, $limit, $offset);
                    $total = $service->countUsers($filters);
                }
                
                // Format for CSV export if requested
                if (isset($_GET['format']) && $_GET['format'] === 'csv') {
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="users_' . date('Y-m-d') . '.csv"');
                    
                    $output = fopen('php://output', 'w');
                    fputcsv($output, ['ID', 'Name', 'Email', 'Role', 'Status', 'Tier', 'Created']);
                    
                    foreach ($users as $user) {
                        fputcsv($output, [
                            $user['id'] ?? '',
                            $user['name'] ?? '',
                            $user['email'] ?? '',
                            $user['role'] ?? '',
                            $user['status'] ?? '',
                            $user['tier'] ?? '',
                            $user['created_at'] ?? ''
                        ]);
                    }
                    
                    fclose($output);
                    exit;
                }
                
                echo json_encode([
                    'users' => $users ?? [],
                    'total' => $total ?? 0,
                    'limit' => $limit ?? 100,
                    'offset' => $offset ?? 0
                ]);
            } catch (Exception $e) {
                http_response_code(400);
                error_log("User Admin API - List error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
                echo json_encode([
                    'error' => 'Failed to list users: ' . $e->getMessage()
                ]);
            } catch (Throwable $e) {
                http_response_code(400);
                error_log("User Admin API - List throwable: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
                echo json_encode([
                    'error' => 'Failed to list users: ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'get':
            $user_id = $_GET['id'] ?? $input['id'] ?? null;
            if (!$user_id) {
                throw new Exception('User ID required');
            }
            
            $user = $service->getUser($user_id);
            if (!$user) {
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
            } else {
                // Get permissions
                $userPermissions = $permissions->getUserPermissions($user_id);
                
                // Get activity (last 20)
                $activity = $storage->getActivity($user_id, 20);
                
                echo json_encode([
                    'user' => $user,
                    'permissions' => $userPermissions,
                    'activity' => $activity
                ]);
            }
            break;
            
        case 'create':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }
            
            // Validate required fields
            if (empty($input['email'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Email is required'
                ]);
                break;
            }
            
            if (empty($input['password'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Password is required'
                ]);
                break;
            }
            
            // Validate email before processing
            $email = trim($input['email'] ?? '');
            if (empty($email)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Email is required'
                ]);
                break;
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid email format: ' . htmlspecialchars($email)
                ]);
                break;
            }
            
            $userData = [
                'email' => $email,
                'password' => $input['password'] ?? '',
                'name' => trim($input['name'] ?? ''),
                'role' => $input['role'] ?? 'member',
                'status' => $input['status'] ?? 'active',
                'verified' => isset($input['verified']) ? (bool)$input['verified'] : true,
                'approved' => isset($input['approved']) ? (bool)$input['approved'] : true,
                'tier' => $input['tier'] ?? 'free'
            ];
            
            if (isset($input['phone'])) {
                $userData['phone'] = trim($input['phone']);
            }
            
            try {
                $user = $service->createUserByAdmin($userData);
                echo json_encode([
                    'success' => true,
                    'message' => 'User created successfully',
                    'user' => $user
                ]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            } catch (Throwable $e) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            break;
            
        case 'update':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }
            
            $user_id = $input['id'] ?? null;
            if (!$user_id) {
                throw new Exception('User ID required');
            }
            
            $updateData = [];
            
            if (isset($input['name'])) {
                $updateData['name'] = $input['name'];
            }
            if (isset($input['email'])) {
                $updateData['email'] = $input['email'];
            }
            if (isset($input['password']) && !empty($input['password'])) {
                $updateData['password'] = $input['password'];
            }
            if (isset($input['role'])) {
                $updateData['role'] = $input['role'];
            }
            if (isset($input['status'])) {
                $updateData['status'] = $input['status'];
            }
            if (isset($input['tier'])) {
                $updateData['tier'] = $input['tier'];
            }
            if (isset($input['verified'])) {
                $updateData['verified'] = (bool)$input['verified'];
            }
            if (isset($input['approved'])) {
                $updateData['approved'] = (bool)$input['approved'];
            }
            if (isset($input['phone'])) {
                $updateData['phone'] = $input['phone'];
            }
            
            try {
                $user = $service->updateUser($user_id, $updateData);
                echo json_encode([
                    'success' => true,
                    'message' => 'User updated successfully',
                    'user' => $user
                ]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            break;
            
        case 'verify':
            $user_id = $input['id'] ?? $_GET['id'] ?? '';
            if (empty($user_id)) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID is required']);
                break;
            }
            
            try {
                $user = $service->getUser($user_id);
                if (!$user) {
                    http_response_code(404);
                    echo json_encode(['error' => 'User not found']);
                    break;
                }
                
                $service->updateUser($user_id, ['verified' => true, 'approved' => true]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'User verified successfully'
                ]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            break;
            
        case 'delete_account':
            // Self-service account deletion
            $auth = new UserAuth();
            $currentUser = $auth->getCurrentUser();
            
            if (!$currentUser) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                break;
            }
            
            $user_id = $currentUser['id'];
            $confirm = $input['confirm'] ?? false;
            
            if (!$confirm) {
                http_response_code(400);
                echo json_encode(['error' => 'Confirmation required']);
                break;
            }
            
            try {
                // Soft delete user account
                $service->deleteUser($user_id);
                
                // Destroy session
                $auth->logout();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Account deleted successfully'
                ]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            break;
            
        case 'delete':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }
            
            $user_id = $input['id'] ?? null;
            if (!$user_id) {
                throw new Exception('User ID required');
            }
            
            try {
                $service->deleteUser($user_id);
                echo json_encode([
                    'success' => true,
                    'message' => 'User deleted successfully'
                ]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            break;

        case 'restore':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }

            $user_id = $input['id'] ?? null;
            if (!$user_id) {
                throw new Exception('User ID required');
            }

            try {
                if (!$storage->restoreUser($user_id)) {
                    throw new Exception('User not found');
                }
                echo json_encode([
                    'success' => true,
                    'message' => 'User restored successfully'
                ]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            break;

        case 'purge_user':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }

            $user_id = $input['id'] ?? null;
            if (!$user_id) {
                throw new Exception('User ID required');
            }

            try {
                if (!$storage->purgeUser($user_id)) {
                    throw new Exception('User not found');
                }
                echo json_encode([
                    'success' => true,
                    'message' => 'User permanently deleted'
                ]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            break;

        case 'bulk':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }

            $op = $input['op'] ?? null;
            $ids = $input['ids'] ?? null;
            if (!$op || !is_string($op)) {
                throw new Exception('Operation required');
            }
            if (!is_array($ids) || empty($ids)) {
                throw new Exception('User IDs required');
            }

            $allowed = ['delete', 'restore', 'purge', 'set_status', 'set_tier'];
            if (!in_array($op, $allowed, true)) {
                throw new Exception('Invalid bulk operation');
            }

            $value = $input['value'] ?? null;
            if ($op === 'set_status') {
                $validStatuses = ['active', 'inactive', 'pending'];
                if (!is_string($value) || !in_array($value, $validStatuses, true)) {
                    throw new Exception('Invalid status value');
                }
            } elseif ($op === 'set_tier') {
                if (!is_string($value) || trim($value) === '') {
                    throw new Exception('Invalid tier value');
                }
                $value = trim($value);
            }

            $summary = [
                'op' => $op,
                'requested' => count($ids),
                'succeeded' => 0,
                'failed' => 0,
                'failures' => []
            ];

            foreach ($ids as $id) {
                $id = is_string($id) ? trim($id) : '';
                if ($id === '') {
                    $summary['failed']++;
                    $summary['failures'][] = ['id' => $id, 'error' => 'Invalid user id'];
                    continue;
                }

                try {
                    if ($op === 'delete') {
                        $service->deleteUser($id);
                    } elseif ($op === 'restore') {
                        if (!$storage->restoreUser($id)) {
                            throw new Exception('User not found');
                        }
                    } elseif ($op === 'purge') {
                        if (!$storage->purgeUser($id)) {
                            throw new Exception('User not found');
                        }
                    } elseif ($op === 'set_status') {
                        $service->updateUser($id, ['status' => $value]);
                    } elseif ($op === 'set_tier') {
                        $service->updateUser($id, ['tier' => $value]);
                    }
                    $summary['succeeded']++;
                } catch (Exception $e) {
                    $summary['failed']++;
                    $summary['failures'][] = ['id' => $id, 'error' => $e->getMessage()];
                }
            }

            echo json_encode([
                'success' => true,
                'summary' => $summary
            ]);
            break;

        case 'bulk_query':
            // Apply a bulk operation to ALL users matching filters/search (across pages)
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }

            $op = $input['op'] ?? null;
            $filters = $input['filters'] ?? [];
            $search = $input['search'] ?? null;
            $force = (bool)($input['force'] ?? false);
            $confirm = is_string($input['confirm'] ?? null) ? $input['confirm'] : '';

            if (!$op || !is_string($op)) {
                throw new Exception('Operation required');
            }
            if (!is_array($filters)) {
                $filters = [];
            }

            $allowed = ['delete', 'restore', 'purge', 'set_status', 'set_tier'];
            if (!in_array($op, $allowed, true)) {
                throw new Exception('Invalid bulk operation');
            }

            $value = $input['value'] ?? null;
            if ($op === 'set_status') {
                $validStatuses = ['active', 'inactive', 'pending'];
                if (!is_string($value) || !in_array($value, $validStatuses, true)) {
                    throw new Exception('Invalid status value');
                }
            } elseif ($op === 'set_tier') {
                if (!is_string($value) || trim($value) === '') {
                    throw new Exception('Invalid tier value');
                }
                $value = trim($value);
            }

            // Normalize filters
            $normalized = [];
            if (isset($filters['role']) && is_string($filters['role']) && $filters['role'] !== '') {
                $normalized['role'] = $filters['role'];
            }
            if (isset($filters['status']) && is_string($filters['status']) && $filters['status'] !== '') {
                $normalized['status'] = $filters['status'];
            }
            // Default: exclude deleted (soft deleted)
            if (!isset($filters['include_deleted']) || !($filters['include_deleted'] === '1' || $filters['include_deleted'] === 'true' || $filters['include_deleted'] === true)) {
                $normalized['deleted'] = false;
            }

            // Get all matching users
            $users = $storage->listUsers($normalized);

            // Apply search (case-insensitive) if present
            if (is_string($search) && trim($search) !== '') {
                $q = strtolower(trim($search));
                $users = array_values(array_filter($users, function ($u) use ($q) {
                    $email = strtolower($u['email'] ?? '');
                    $name = strtolower($u['name'] ?? ($u['username'] ?? ''));
                    $id = strtolower($u['id'] ?? '');
                    return (strpos($email, $q) !== false) || (strpos($name, $q) !== false) || (strpos($id, $q) !== false);
                }));
            }

            // Safety cap to prevent accidental mass ops
            $max = 1000;
            $maxForce = 5000;
            $total = count($users);
            if ($total > $max) {
                // Optional force path (still capped, requires explicit confirmation phrase)
                if ($force && $confirm === 'BULK_OK' && $total <= $maxForce) {
                    // allow
                } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                        'error' => "Too many users matched ($total). Refine filters/search (limit: $max). To force up to $maxForce, set force=true and confirm=\"BULK_OK\".",
                        'matched' => $total,
                        'limit' => $max,
                        'force_limit' => $maxForce
                ]);
                break;
                }
            }

            $summary = [
                'op' => $op,
                'matched' => $total,
                'succeeded' => 0,
                'failed' => 0,
                'failures' => []
            ];

            foreach ($users as $u) {
                $id = $u['id'] ?? '';
                if (!is_string($id) || $id === '') {
                    $summary['failed']++;
                    $summary['failures'][] = ['id' => $id, 'error' => 'Invalid user id'];
                    continue;
                }

                try {
                    if ($op === 'delete') {
                        $service->deleteUser($id);
                    } elseif ($op === 'restore') {
                        if (!$storage->restoreUser($id)) throw new Exception('User not found');
                    } elseif ($op === 'purge') {
                        if (!$storage->purgeUser($id)) throw new Exception('User not found');
                    } elseif ($op === 'set_status') {
                        $service->updateUser($id, ['status' => $value]);
                    } elseif ($op === 'set_tier') {
                        $service->updateUser($id, ['tier' => $value]);
                    }
                    $summary['succeeded']++;
                } catch (Exception $e) {
                    $summary['failed']++;
                    $summary['failures'][] = ['id' => $id, 'error' => $e->getMessage()];
                }
            }

            echo json_encode(['success' => true, 'summary' => $summary]);
            break;

        case 'bulk_query_preview':
            // Preview how many users match filters/search (across pages)
            // This does not mutate anything.
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }

            $filters = $input['filters'] ?? [];
            $search = $input['search'] ?? null;
            if (!is_array($filters)) {
                $filters = [];
            }

            $normalized = [];
            if (isset($filters['role']) && is_string($filters['role']) && $filters['role'] !== '') {
                $normalized['role'] = $filters['role'];
            }
            if (isset($filters['status']) && is_string($filters['status']) && $filters['status'] !== '') {
                $normalized['status'] = $filters['status'];
            }
            // Default: exclude deleted unless include_deleted was requested
            if (!isset($filters['include_deleted']) || !($filters['include_deleted'] === '1' || $filters['include_deleted'] === 'true' || $filters['include_deleted'] === true)) {
                $normalized['deleted'] = false;
            }

            $users = $storage->listUsers($normalized);
            if (is_string($search) && trim($search) !== '') {
                $q = strtolower(trim($search));
                $users = array_values(array_filter($users, function ($u) use ($q) {
                    $email = strtolower($u['email'] ?? '');
                    $name = strtolower($u['name'] ?? ($u['username'] ?? ''));
                    $id = strtolower($u['id'] ?? '');
                    return (strpos($email, $q) !== false) || (strpos($name, $q) !== false) || (strpos($id, $q) !== false);
                }));
            }

            echo json_encode([
                'success' => true,
                'matched' => count($users),
                'limit' => 1000,
                'force_limit' => 5000
            ]);
            break;

        case 'purge_deleted':
            // Permanently purge soft-deleted users and related files
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }

            try {
                $result = $storage->purgeDeletedUsers();
                echo json_encode([
                    'success' => true,
                    'purged' => $result['purged'] ?? 0,
                    'user_ids' => $result['user_ids'] ?? [],
                ]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            break;
            
        case 'update_permissions':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }
            
            $user_id = $input['user_id'] ?? null;
            if (!$user_id) {
                throw new Exception('User ID required');
            }
            
            $permissionsData = $input['permissions'] ?? null;
            if (!$permissionsData) {
                throw new Exception('Permissions data required');
            }
            
            try {
                $permissions->setUserPermissions($user_id, $permissionsData);
                echo json_encode([
                    'success' => true,
                    'message' => 'Permissions updated successfully'
                ]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            break;
            
        case 'get_user_status':
            // Get user verification and 2FA status (admin endpoint)
            // Support both GET and POST
            // Also check REQUEST_URI for user_id parameter
            $user_id = $_GET['user_id'] ?? $input['user_id'] ?? '';
            if (empty($user_id) && isset($_SERVER['REQUEST_URI'])) {
                $queryString = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
                if ($queryString) {
                    parse_str($queryString, $queryParams);
                    if (isset($queryParams['user_id'])) {
                        $user_id = $queryParams['user_id'];
                    }
                }
            }
            if (empty($user_id)) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID required', 'debug' => [
                    'get_user_id' => $_GET['user_id'] ?? 'not set',
                    'input_user_id' => $input['user_id'] ?? 'not set',
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'not set'
                ]]);
                break;
            }
            
            $user = $service->getUser($user_id);
            if (!$user) {
                // IMPORTANT: Don't return 404 here.
                // This endpoint is called opportunistically by the admin UI for each row,
                // and missing users (e.g. soft-deleted, migrated, or inconsistent state)
                // should not spam the browser with "Failed to load resource" errors.
                // Return a 200 with success=false so the UI can show "unknown".
                echo json_encode([
                    'success' => false,
                    'error' => 'User not found',
                    'email_verified' => false,
                    '2fa_enabled' => false
                ]);
                break;
            }
            
            // Check email verification (from user object)
            $emailVerified = !empty($user['verified']) && $user['verified'] === true;
            
            // Check 2FA status
            require_once __DIR__ . '/../includes/TwoFactorAuth.php';
            $twoFactor = new TwoFactorAuth();
            $twofaEnabled = $twoFactor->is2FAEnabled($user_id);
            
            echo json_encode([
                'success' => true,
                'email_verified' => $emailVerified,
                '2fa_enabled' => $twofaEnabled
            ]);
            break;
            
        case 'stats':
            // Stats are used by the admin UI for quick overview.
            // Provide both "active" (non-deleted) and deleted counts.
            $stats = [
                // Non-deleted users
                'total' => $service->countUsers(['deleted' => false]),
                'active' => $service->countUsers(['deleted' => false, 'status' => 'active']),
                'inactive' => $service->countUsers(['deleted' => false, 'status' => 'inactive']),
                'pending' => $service->countUsers(['deleted' => false, 'status' => 'pending']),

                // Deleted users (soft deleted)
                'deleted' => $service->countUsers(['deleted' => true]),

                // Total including deleted (useful for maintenance)
                'total_including_deleted' => $service->countUsers([]),

                'by_role' => [
                    'member' => $service->countUsers(['deleted' => false, 'role' => 'member']),
                    'subscriber' => $service->countUsers(['deleted' => false, 'role' => 'subscriber']),
                    'premium' => $service->countUsers(['deleted' => false, 'role' => 'premium']),
                    'vip' => $service->countUsers(['deleted' => false, 'role' => 'vip'])
                ]
            ];
            
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(400);
    error_log("User Admin API Exception: " . $e->getMessage() . " | Action: " . ($action ?? 'unknown') . " | Trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'action' => $action ?? 'unknown',
        'method' => $method ?? 'unknown'
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    error_log("User Admin API Throwable: " . $e->getMessage() . " | Action: " . ($action ?? 'unknown') . " | Trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'action' => $action ?? 'unknown',
        'method' => $method ?? 'unknown'
    ]);
}
