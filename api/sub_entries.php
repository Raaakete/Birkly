<?php
/**
 * Sub-Entries API for Birkly
 * 
 * Handles sub-entry CRUD operations and queries.
 */

// CRITICAL: Start output buffering FIRST, before anything else
ob_start();

// Suppress all error display
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

header('Content-Type: application/json');

// IMPORTANT:
// - Public read-only actions should not start a session at all.
// - Admin actions must use the admin session cookie (not PHPSESSID),
//   otherwise admin users appear "logged out" here.
require_once __DIR__ . '/session.php';

// Ensure storage system is loaded before sub_entries
if (!function_exists('get_storage')) {
    require_once __DIR__ . '/../core/storage/StorageFactory.php';
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../core/sub_entries.php';

// Import new functions
if (!function_exists('getSubEntrySiblings')) {
    require_once __DIR__ . '/../core/sub_entries.php';
}
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/plugins.php';

// Basic auth check for admin operations
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

// Public actions (no auth required)
$publicActions = ['list', 'get', 'get_children', 'get_descendants', 'get_ancestors', 'aggregations'];

// Only start an ADMIN session for non-public actions (or any POST mutation)
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isPublic = in_array($action, $publicActions, true) && $method === 'GET';
if (!$isPublic) {
    haru_secure_session_start();
}

if (!in_array($action, $publicActions) && !isset($_SESSION['user'])) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    ob_end_flush();
    exit;
}

// List sub-entries
if ($action === 'list') {
    $parent_collection = $input['parent_collection'] ?? $_GET['parent_collection'] ?? '';
    $parent_entry_id = $input['parent_entry_id'] ?? $_GET['parent_entry_id'] ?? '';
    $sub_collection = $input['sub_collection'] ?? $_GET['sub_collection'] ?? '';
    $parent_sub_collection = $input['parent_sub_collection'] ?? $_GET['parent_sub_collection'] ?? null;
    $parent_sub_entry_id = $input['parent_sub_entry_id'] ?? $_GET['parent_sub_entry_id'] ?? null;
    
    
    if (empty($parent_collection) || empty($parent_entry_id) || empty($sub_collection)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
        ob_end_flush();
        exit;
    }
    
    $filters = $input['filters'] ?? $_GET['filters'] ?? [];
    $options = $input['options'] ?? $_GET['options'] ?? [];
    
    if (is_string($filters)) {
        $filters = json_decode($filters, true) ?? [];
    }
    if (is_string($options)) {
        $options = json_decode($options, true) ?? [];
    }
    
    try {
        
        // Check if this is a nested sub-entry list request
        if (!empty($parent_sub_collection) && !empty($parent_sub_entry_id)) {
            // Load nested sub-entries directly from storage
            if (!function_exists('get_storage')) {
                require_once __DIR__ . '/../core/storage/StorageFactory.php';
            }
            $storage = get_storage();
            $nestedSubEntryPath = "collections/{$parent_collection}/entries/{$parent_entry_id}/sub_entries/{$parent_sub_collection}/{$parent_sub_entry_id}/sub_entries/{$sub_collection}";
            
            
            try {
                $files = $storage->list($nestedSubEntryPath);
                $entries = [];
                foreach ($files as $file) {
                    if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                        // Construct full path for reading
                        $fullFilePath = "{$nestedSubEntryPath}/{$file}";
                        $entryData = $storage->read($fullFilePath);
                        if ($entryData) {
                            $entryData['id'] = basename($file, '.json');
                            $entries[] = $entryData;
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("sub_entries API (list): Error loading nested sub-entries: " . $e->getMessage());
                $entries = [];
            }
        } else {
            // First-level sub-entries
            $entries = getSubEntries($parent_collection, $parent_entry_id, $sub_collection, $filters, $options);
        }
        
        ob_clean();
        echo json_encode([
            'status' => 'ok',
            'data' => [
                'entries' => $entries,
                'count' => count($entries)
            ]
        ]);
        ob_end_flush();
        exit;
    } catch (Exception $e) {
        error_log("Error in sub_entries API (list): " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to retrieve sub-entries: ' . $e->getMessage()
        ]);
        ob_end_flush();
        exit;
    }
}

// Get single sub-entry
if ($action === 'get') {
    $parent_collection = $input['parent_collection'] ?? $_GET['parent_collection'] ?? '';
    $parent_entry_id = $input['parent_entry_id'] ?? $_GET['parent_entry_id'] ?? '';
    $sub_collection = $input['sub_collection'] ?? $_GET['sub_collection'] ?? '';
    $sub_entry_id = $input['sub_entry_id'] ?? $_GET['sub_entry_id'] ?? '';
    
    if (empty($parent_collection) || empty($parent_entry_id) || empty($sub_collection) || empty($sub_entry_id)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
        ob_end_flush();
        exit;
    }
    
    try {
        $entry = getSubEntry($parent_collection, $parent_entry_id, $sub_collection, $sub_entry_id);
        
        ob_clean();
        if ($entry) {
            echo json_encode([
                'status' => 'ok',
                'data' => ['entry' => $entry]
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Sub-entry not found']);
        }
        ob_end_flush();
        exit;
    } catch (Exception $e) {
        error_log("Error in sub_entries API (get): " . $e->getMessage());
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to retrieve sub-entry: ' . $e->getMessage()
        ]);
        ob_end_flush();
        exit;
    }
}

// Create sub-entry (admin only)
if ($action === 'create') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        ob_clean();
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        ob_end_flush();
        exit;
    }
    
    requireAuth('manage_content');
    
    $parent_collection = $input['parent_collection'] ?? '';
    $parent_entry_id = $input['parent_entry_id'] ?? '';
    $sub_collection = $input['sub_collection'] ?? '';
    $data = $input['data'] ?? [];
    
    if (empty($parent_collection) || empty($parent_entry_id) || empty($sub_collection)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
        ob_end_flush();
        exit;
    }
    
    try {
        error_log("sub_entries API (create) called with:");
        error_log("  parent_collection: {$parent_collection}");
        error_log("  parent_entry_id: {$parent_entry_id}");
        error_log("  sub_collection: {$sub_collection}");
        error_log("  data keys: " . implode(', ', array_keys($data)));
        error_log("  parent_sub_collection: " . ($data['parent_sub_collection'] ?? 'not set'));
        error_log("  parent_sub_entry_id: " . ($data['parent_sub_entry_id'] ?? 'not set'));
        
        $result = createSubEntry($parent_collection, $parent_entry_id, $sub_collection, $data);
        
        ob_clean();
        if ($result) {
            echo json_encode([
                'status' => 'ok',
                'data' => ['entry' => $result]
            ]);
        } else {
            error_log("createSubEntry returned false - check error logs above for details");
            http_response_code(400);
            echo json_encode([
                'status' => 'error', 
                'message' => 'Failed to create sub-entry. Check server logs for details.',
                'debug' => [
                    'parent_collection' => $parent_collection,
                    'parent_entry_id' => $parent_entry_id,
                    'sub_collection' => $sub_collection,
                    'has_parent_sub_collection' => !empty($data['parent_sub_collection']),
                    'has_parent_sub_entry_id' => !empty($data['parent_sub_entry_id'])
                ]
            ]);
        }
        ob_end_flush();
        exit;
    } catch (Exception $e) {
        error_log("Error in sub_entries API (create): " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to create sub-entry: ' . $e->getMessage()
        ]);
        ob_end_flush();
        exit;
    }
}

// Update sub-entry (admin only)
if ($action === 'update') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        ob_clean();
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        ob_end_flush();
        exit;
    }
    
    requireAuth('manage_content');
    
    $parent_collection = $input['parent_collection'] ?? '';
    $parent_entry_id = $input['parent_entry_id'] ?? '';
    $sub_collection = $input['sub_collection'] ?? '';
    $sub_entry_id = $input['sub_entry_id'] ?? '';
    $data = $input['data'] ?? [];
    
    if (empty($parent_collection) || empty($parent_entry_id) || empty($sub_collection) || empty($sub_entry_id)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
        ob_end_flush();
        exit;
    }
    
    try {
        $result = updateSubEntry($parent_collection, $parent_entry_id, $sub_collection, $sub_entry_id, $data);
        
        ob_clean();
        if ($result) {
            echo json_encode([
                'status' => 'ok',
                'data' => ['entry' => $result]
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Failed to update sub-entry']);
        }
        ob_end_flush();
        exit;
    } catch (Exception $e) {
        error_log("Error in sub_entries API (update): " . $e->getMessage());
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to update sub-entry: ' . $e->getMessage()
        ]);
        ob_end_flush();
        exit;
    }
}

// Delete sub-entry (admin only)
if ($action === 'delete') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        ob_clean();
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        ob_end_flush();
        exit;
    }
    
    requireAuth('manage_content');
    
    $parent_collection = $input['parent_collection'] ?? '';
    $parent_entry_id = $input['parent_entry_id'] ?? '';
    $sub_collection = $input['sub_collection'] ?? '';
    $sub_entry_id = $input['sub_entry_id'] ?? '';
    $parent_sub_collection = $input['parent_sub_collection'] ?? null;
    $parent_sub_entry_id = $input['parent_sub_entry_id'] ?? null;
    
    if (empty($parent_collection) || empty($parent_entry_id) || empty($sub_collection) || empty($sub_entry_id)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
        ob_end_flush();
        exit;
    }
    
    try {
        $result = deleteSubEntry($parent_collection, $parent_entry_id, $sub_collection, $sub_entry_id, $parent_sub_collection, $parent_sub_entry_id);
        
        ob_clean();
        if ($result) {
            echo json_encode(['status' => 'ok', 'message' => 'Sub-entry deleted']);
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete sub-entry']);
        }
        ob_end_flush();
        exit;
    } catch (Exception $e) {
        error_log("Error in sub_entries API (delete): " . $e->getMessage());
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to delete sub-entry: ' . $e->getMessage()
        ]);
        ob_end_flush();
        exit;
    }
}

// Count sub-entries
if ($action === 'count') {
    $parent_collection = $input['parent_collection'] ?? $_GET['parent_collection'] ?? '';
    $parent_entry_id = $input['parent_entry_id'] ?? $_GET['parent_entry_id'] ?? '';
    $sub_collection = $input['sub_collection'] ?? $_GET['sub_collection'] ?? '';
    $filters = $input['filters'] ?? $_GET['filters'] ?? [];
    
    if (empty($parent_collection) || empty($parent_entry_id) || empty($sub_collection)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
        ob_end_flush();
        exit;
    }
    
    if (is_string($filters)) {
        $filters = json_decode($filters, true) ?? [];
    }
    
    try {
        $count = countSubEntries($parent_collection, $parent_entry_id, $sub_collection, $filters);
        
        ob_clean();
        echo json_encode([
            'status' => 'ok',
            'data' => ['count' => $count]
        ]);
        ob_end_flush();
        exit;
    } catch (Exception $e) {
        error_log("Error in sub_entries API (count): " . $e->getMessage());
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to count sub-entries: ' . $e->getMessage()
        ]);
        ob_end_flush();
        exit;
    }
}

// Set sub-entry order
if ($action === 'set_order') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        ob_clean();
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        ob_end_flush();
        exit;
    }
    
    $parent_collection = $input['parent_collection'] ?? '';
    $parent_entry_id = $input['parent_entry_id'] ?? '';
    $sub_collection = $input['sub_collection'] ?? '';
    $order = $input['order'] ?? [];
    $parent_sub_collection = $input['parent_sub_collection'] ?? null;
    $parent_sub_entry_id = $input['parent_sub_entry_id'] ?? null;
    
    if (empty($parent_collection) || empty($parent_entry_id) || empty($sub_collection) || !is_array($order)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
        ob_end_flush();
        exit;
    }
    
    try {
        if (!function_exists('get_storage')) {
            require_once __DIR__ . '/../core/storage/StorageFactory.php';
        }
        $storage = get_storage();
        
        // Determine path based on whether it's nested or first-level
        if ($parent_sub_collection && $parent_sub_entry_id) {
            $subEntryPath = "collections/{$parent_collection}/entries/{$parent_entry_id}/sub_entries/{$parent_sub_collection}/{$parent_sub_entry_id}/sub_entries/{$sub_collection}";
        } else {
            $subEntryPath = "collections/{$parent_collection}/entries/{$parent_entry_id}/sub_entries/{$sub_collection}";
        }
        
        // Get all sub-entry files
        $files = $storage->list($subEntryPath);
        $entries = [];
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $entryId = basename($file, '.json');
                $entryData = $storage->read("{$subEntryPath}/{$file}");
                if ($entryData) {
                    $entries[$entryId] = $entryData;
                }
            }
        }
        
        // Reorder entries based on provided order
        $orderedEntries = [];
        foreach ($order as $entryId) {
            if (isset($entries[$entryId])) {
                $orderedEntries[] = $entryId;
            }
        }
        
        // Add any entries not in the order list (shouldn't happen, but safety check)
        foreach ($entries as $entryId => $entryData) {
            if (!in_array($entryId, $orderedEntries)) {
                $orderedEntries[] = $entryId;
            }
        }
        
        // Write entries back in new order (this maintains the order in the file system)
        // Note: File systems don't guarantee order, so we store order in a metadata file
        $orderFile = "{$subEntryPath}/.order.json";
        $storage->write($orderFile, ['order' => $orderedEntries]);
        
        ob_clean();
        echo json_encode([
            'status' => 'ok',
            'message' => 'Sub-entries reordered successfully'
        ]);
        ob_end_flush();
        exit;
    } catch (Exception $e) {
        error_log("Error in sub_entries API (set_order): " . $e->getMessage());
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to reorder sub-entries: ' . $e->getMessage()
        ]);
        ob_end_flush();
        exit;
    }
}

// Reorder a single sub-entry (for drag and drop)
if ($action === 'reorder') {
    $csrf = $input['_csrf'] ?? get_csrf_token();
    if (!validate_csrf_token($csrf)) {
        ob_clean();
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        ob_end_flush();
        exit;
    }
    
    $collection = $input['collection'] ?? '';
    $parent_entry_id = $input['parent_entry_id'] ?? '';
    $sub_collection = $input['sub_collection'] ?? '';
    $entry_id = $input['entry_id'] ?? '';
    $target_entry_id = $input['target_entry_id'] ?? null;
    $insert_before = $input['insert_before'] ?? true;
    
    // For nested sub-entries (sub-entries of sub-entries)
    $parent_sub_collection = $input['parent_sub_collection'] ?? null;
    $parent_sub_entry_id = $input['parent_sub_entry_id'] ?? null;
    
    if (empty($collection) || empty($parent_entry_id) || empty($sub_collection) || empty($entry_id)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
        ob_end_flush();
        exit;
    }
    
    try {
        if (!function_exists('get_storage')) {
            require_once __DIR__ . '/../core/storage/StorageFactory.php';
        }
        $storage = get_storage();
        
        // Find collection directory (match by ID or slug, like content.php does)
        $collectionDir = '';
        $collections = $storage->list('collections');
        foreach ($collections as $d) {
            $m = $storage->read("collections/$d/collection.json");
            if ($m && ($m['id'] === $collection || $m['slug'] === $collection)) {
                $collectionDir = $d;
                break;
            }
        }
        
        if (!$collectionDir) {
            ob_clean();
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Collection not found']);
            ob_end_flush();
            exit;
        }
        
        // Build correct path for nested sub-entries using the resolved directory name
        if (!empty($parent_sub_collection) && !empty($parent_sub_entry_id)) {
            // This is a nested sub-entry (sub-entry of a sub-entry)
            $subEntryPath = "collections/{$collectionDir}/entries/{$parent_entry_id}/sub_entries/{$parent_sub_collection}/{$parent_sub_entry_id}/sub_entries/{$sub_collection}";
        } else {
            // This is a first-level sub-entry
            $subEntryPath = "collections/{$collectionDir}/entries/{$parent_entry_id}/sub_entries/{$sub_collection}";
        }
        
        // Get current order
        $orderFile = "{$subEntryPath}/.order.json";
        $orderData = $storage->read($orderFile);
        $currentOrder = $orderData['order'] ?? [];
        
        // Filter out any non-entry items (like '.order' which shouldn't be there)
        $currentOrder = array_filter($currentOrder, function($id) {
            return !empty($id) && $id !== '.order' && !str_starts_with($id, '.');
        });
        $currentOrder = array_values($currentOrder); // Re-index array
        
        // If no order exists, create from files
        if (empty($currentOrder)) {
            $files = $storage->list($subEntryPath);
            foreach ($files as $file) {
                // Only include actual entry files (skip .order.json and other hidden files)
                if (pathinfo($file, PATHINFO_EXTENSION) === 'json' && !str_starts_with($file, '.')) {
                    $currentOrder[] = basename($file, '.json');
                }
            }
        }
        
        // Remove the entry being moved from its current position
        $currentOrder = array_values(array_filter($currentOrder, function($id) use ($entry_id) {
            return $id !== $entry_id;
        }));
        
        // Find the target position
        if ($target_entry_id === null) {
            // Move to end
            $currentOrder[] = $entry_id;
        } else {
            $targetIndex = array_search($target_entry_id, $currentOrder);
            if ($targetIndex === false) {
                // Target not found, move to end
                $currentOrder[] = $entry_id;
            } else {
                // Insert before or after target
                if ($insert_before) {
                    array_splice($currentOrder, $targetIndex, 0, [$entry_id]);
                } else {
                    array_splice($currentOrder, $targetIndex + 1, 0, [$entry_id]);
                }
            }
        }
        
        // Save new order
        $storage->write($orderFile, ['order' => $currentOrder]);
        
        ob_clean();
        echo json_encode([
            'status' => 'ok',
            'success' => true,
            'message' => 'Sub-entry reordered successfully',
            'new_order' => $currentOrder
        ]);
        ob_end_flush();
        exit;
    } catch (Exception $e) {
        error_log("Error in sub_entries API (reorder): " . $e->getMessage());
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to reorder sub-entry: ' . $e->getMessage()
        ]);
        ob_end_flush();
        exit;
    }
}

// Bulk operations for sub-entries
if ($action === 'bulk') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        ob_clean();
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        ob_end_flush();
        exit;
    }
    
    $parent_collection = $input['parent_collection'] ?? '';
    $parent_entry_id = $input['parent_entry_id'] ?? '';
    $sub_collection = $input['sub_collection'] ?? '';
    $operation = $input['operation'] ?? '';
    $sub_entry_ids = $input['sub_entry_ids'] ?? [];
    $parent_sub_collection = $input['parent_sub_collection'] ?? null;
    $parent_sub_entry_id = $input['parent_sub_entry_id'] ?? null;
    
    if (empty($parent_collection) || empty($parent_entry_id) || empty($sub_collection) || empty($operation) || !is_array($sub_entry_ids) || empty($sub_entry_ids)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
        ob_end_flush();
        exit;
    }
    
    try {
        if (!function_exists('get_storage')) {
            require_once __DIR__ . '/../core/storage/StorageFactory.php';
        }
        $storage = get_storage();
        
        // Determine path based on whether it's nested or first-level
        $isNested = !empty($parent_sub_collection) && !empty($parent_sub_entry_id);
        if ($isNested) {
            $basePath = "collections/{$parent_collection}/entries/{$parent_entry_id}/sub_entries/{$parent_sub_collection}/{$parent_sub_entry_id}/sub_entries/{$sub_collection}";
        } else {
            $basePath = "collections/{$parent_collection}/entries/{$parent_entry_id}/sub_entries/{$sub_collection}";
        }
        
        $results = [];
        foreach ($sub_entry_ids as $sub_entry_id) {
            if ($operation === 'delete') {
                error_log("Bulk delete: Attempting to delete sub-entry {$sub_entry_id} from collection {$sub_collection}, parent entry {$parent_entry_id}");
                error_log("Bulk delete: parent_sub_collection = " . ($parent_sub_collection ?? 'null') . ", parent_sub_entry_id = " . ($parent_sub_entry_id ?? 'null'));
                $result = deleteSubEntry($parent_collection, $parent_entry_id, $sub_collection, $sub_entry_id, $parent_sub_collection, $parent_sub_entry_id);
                error_log("Bulk delete: Result for {$sub_entry_id} = " . ($result ? 'true' : 'false'));
                $results[$sub_entry_id] = $result ? 'ok' : 'error';
            } elseif ($operation === 'publish' || $operation === 'unpublish') {
                // Update status directly
                $entryPath = "{$basePath}/{$sub_entry_id}.json";
                $subEntry = $storage->read($entryPath);
                if ($subEntry) {
                    $subEntry['status'] = $operation === 'publish' ? 'published' : 'draft';
                    $subEntry['updated_at'] = date('Y-m-d H:i:s');
                    $result = $storage->write($entryPath, $subEntry);
                    $results[$sub_entry_id] = $result ? 'ok' : 'error';
                } else {
                    $results[$sub_entry_id] = 'error';
                }
            } else {
                $results[$sub_entry_id] = 'error';
            }
        }
        
        $successCount = count(array_filter($results, function($r) { return $r === 'ok'; }));
        
        ob_clean();
        echo json_encode([
            'status' => 'ok',
            'message' => "Bulk operation completed: {$successCount} of " . count($sub_entry_ids) . " sub-entries processed",
            'results' => $results
        ]);
        ob_end_flush();
        exit;
    } catch (Exception $e) {
        error_log("Error in sub_entries API (bulk): " . $e->getMessage());
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to perform bulk operation: ' . $e->getMessage()
        ]);
        ob_end_flush();
        exit;
    }
}

// Default response
ob_clean();
http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
ob_end_flush();
exit;
