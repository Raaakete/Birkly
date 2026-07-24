<?php
/**
 * Collection Permissions API
 * Handles collection-level permission configuration
 */

// Include core files
require_once __DIR__ . '/../../../api/auth.php';
require_once __DIR__ . '/../../../core/plugins.php';
require_once __DIR__ . '/../../../core/csrf.php';
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

$action = $input['action'] ?? $_GET['action'] ?? 'get';

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
    $storage = new UserStorage();
    
    switch ($action) {
        case 'get':
            // Get all collections
            $collections = [];
            $collectionsPath = __DIR__ . '/../../../content/collections';
            
            if (is_dir($collectionsPath)) {
                $collectionDirs = glob($collectionsPath . '/*', GLOB_ONLYDIR);
                foreach ($collectionDirs as $dir) {
                    $collectionFile = $dir . '/collection.json';
                    if (file_exists($collectionFile)) {
                        $collectionData = json_decode(file_get_contents($collectionFile), true);
                        if ($collectionData) {
                            $collections[] = [
                                'slug' => basename($dir),
                                'name' => $collectionData['name'] ?? $collectionData['title'] ?? basename($dir),
                                'description' => $collectionData['description'] ?? ''
                            ];
                        }
                    }
                }
            }
            
            // Get permissions configuration - ensure it's an object, not an array
            $permissions = $storage->readMetadata('collection_permissions');
            // If it's null or an empty numeric array, use empty associative array (object)
            if ($permissions === null || (is_array($permissions) && empty($permissions) && array_keys($permissions) === range(0, count($permissions) - 1))) {
                $permissions = [];
            }
            // If it's a numeric array (shouldn't happen), convert to empty object
            if (is_array($permissions) && !empty($permissions) && array_keys($permissions) === range(0, count($permissions) - 1)) {
                $permissions = [];
            }
            
            // Get available tiers instead of hardcoded roles
            $tiers = [];
            if (function_exists('get_plugin_state') && get_plugin_state('user_management')) {
                require_once __DIR__ . '/../includes/UserStorage.php';
                $userStorage = new UserStorage();
                $tiersList = $userStorage->listTiers();
                foreach ($tiersList as $tierId) {
                    $tierData = $userStorage->getTier($tierId);
                    if ($tierData) {
                        $tiers[] = [
                            'id' => $tierId,
                            'name' => $tierData['name'] ?? $tierId
                        ];
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'collections' => $collections,
                'permissions' => $permissions,
                'tiers' => $tiers
            ]);
            break;
            
        case 'save':
            // Save permissions configuration
            $permissions = $input['permissions'] ?? [];
            
            // Debug: Log what we're receiving
            error_log('Collection Permissions Save - Received: ' . json_encode($permissions));
            
            // Ensure empty objects are preserved (don't filter them out)
            // This allows collections to be saved even if no permissions are set yet
            $cleanedPermissions = [];
            
            // Check if permissions is an associative array (object) or numeric array
            if (is_array($permissions)) {
                // Check if it's an associative array (has string keys) - this is an object in JSON
                $keys = array_keys($permissions);
                $isAssociative = empty($permissions) || $keys !== range(0, count($permissions) - 1);
                
                if ($isAssociative) {
                    // It's an associative array (object) - process it
                    foreach ($permissions as $collectionSlug => $tierPerms) {
                        // Keep the collection even if it's an empty object
                        // Convert to array but preserve structure
                        if (is_array($tierPerms)) {
                            $cleanedPermissions[$collectionSlug] = $tierPerms;
                        } elseif (is_object($tierPerms)) {
                            // Convert object to array, preserving empty objects as empty arrays
                            $cleanedPermissions[$collectionSlug] = (array)$tierPerms;
                        } else {
                            // For any other type, preserve as empty array
                            $cleanedPermissions[$collectionSlug] = [];
                        }
                    }
                }
            } elseif (is_object($permissions)) {
                // If it's an object, convert to associative array
                $cleanedPermissions = (array)$permissions;
            }
            
            // Debug: Log what we're saving
            error_log('Collection Permissions Save - Saving: ' . json_encode($cleanedPermissions));
            
            // Save the permissions
            if (!$storage->saveMetadata('collection_permissions', $cleanedPermissions)) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save permissions']);
                break;
            }
            
            // Return the saved permissions exactly as they were saved
            echo json_encode([
                'success' => true,
                'message' => 'Permissions saved successfully',
                'permissions' => $cleanedPermissions
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
