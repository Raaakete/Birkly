<?php
/**
 * AI Approval Queue API
 * Manages pending AI actions that require human approval
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
ini_set('max_execution_time', 300);
ini_set('memory_limit', '256M');

if (!isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    require_once __DIR__ . '/session.php';
    haru_secure_session_start();
}

if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    header('Content-Type: application/json');
}

require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validate.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/ai_helpers.php';
require_once __DIR__ . '/../core/entry_slug.php';
require_once __DIR__ . '/../core/connections/mcp_tools.php';

$storage = new Storage(__DIR__ . '/../data/ai_approvals');

if (!isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    $input = sanitize_input(json_decode(file_get_contents('php://input'), true));
    $action = $input['action'] ?? $_GET['action'] ?? '';

    // All actions require authentication
    requireAuth();

// Create approval request (called by AI system)
if ($action === 'create') {
    $aiUserId = $input['ai_user_id'] ?? '';
    $actionType = $input['action_type'] ?? '';
    $actionData = $input['action_data'] ?? [];
    $description = $input['description'] ?? '';
    
    if (empty($aiUserId) || empty($actionType) || empty($actionData)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        exit;
    }
    
    $approvalId = 'approval_' . bin2hex(random_bytes(8));
    $approval = [
        'id' => $approvalId,
        'ai_user_id' => $aiUserId,
        'action_type' => $actionType, // 'create_entry', 'update_entry', 'delete_entry', etc.
        'action_data' => $actionData,
        'description' => $description,
        'status' => 'pending',
        'created_at' => date('c'),
        'reviewed_at' => null,
        'reviewed_by' => null
    ];
    
    $result = $storage->write($approvalId . '.json', $approval);
    
    log_security_event('ai_approval_created', [
        'approval_id' => $approvalId,
        'ai_user_id' => $aiUserId,
        'action_type' => $actionType
    ]);
    
    echo json_encode([
        'status' => $result ? 'ok' : 'error',
        'message' => $result ? 'Approval request created' : 'Failed to create approval',
        'data' => ['approval' => $approval]
    ]);
    exit;
}

// List approvals
if ($action === 'list') {
    $status = $_GET['status'] ?? 'all';
    $approvalList = [];
    $currentUserId = $_SESSION['user']['id'] ?? '';
    $currentUser = $_SESSION['user'] ?? [];
    
    // Check if storage directory exists
    if (is_dir($storage->basePath)) {
        $approvals = $storage->list('');
        
        foreach ($approvals as $approvalFile) {
            $approval = $storage->read($approvalFile);
            if ($approval) {
                if (!birkly_ai_user_can_review_approval($approval, $currentUserId, $currentUser)) {
                    continue;
                }
                if ($status === 'all' || ($approval['status'] ?? '') === $status) {
                    $approvalList[] = $approval;
                }
            }
        }
        
        // Sort by created_at descending
        usort($approvalList, function($a, $b) {
            $timeA = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
            $timeB = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
            return $timeB - $timeA;
        });
    }
    
    echo json_encode([
        'status' => 'ok',
        'data' => ['approvals' => $approvalList]
    ]);
    exit;
}

// Get single approval
if ($action === 'get') {
    $approvalId = $_GET['id'] ?? '';
    if (empty($approvalId)) {
        echo json_encode(['status' => 'error', 'message' => 'Approval ID required']);
        exit;
    }
    
    $approval = $storage->read($approvalId . '.json');
    if (!$approval) {
        echo json_encode(['status' => 'error', 'message' => 'Approval not found']);
        exit;
    }

    $currentUserId = $_SESSION['user']['id'] ?? '';
    if (!birkly_ai_user_can_review_approval($approval, $currentUserId, $_SESSION['user'] ?? [])) {
        echo json_encode(['status' => 'error', 'message' => 'You do not have permission to view this approval']);
        exit;
    }
    
    echo json_encode([
        'status' => 'ok',
        'data' => ['approval' => $approval]
    ]);
    exit;
}

// Approve action
if ($action === 'approve') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $approvalId = $input['id'] ?? '';
    if (empty($approvalId)) {
        echo json_encode(['status' => 'error', 'message' => 'Approval ID required']);
        exit;
    }
    
    $approval = $storage->read($approvalId . '.json');
    if (!$approval) {
        echo json_encode(['status' => 'error', 'message' => 'Approval not found']);
        exit;
    }
    
    if ($approval['status'] !== 'pending') {
        echo json_encode(['status' => 'error', 'message' => 'Approval already processed']);
        exit;
    }

    $currentUserId = $_SESSION['user']['id'] ?? '';
    if (!birkly_ai_user_can_review_approval($approval, $currentUserId, $_SESSION['user'] ?? [])) {
        echo json_encode(['status' => 'error', 'message' => 'You do not have permission to approve this action']);
        exit;
    }
    
    // Execute the approved action
    $result = executeAiAction($approval);
    
    if ($result['success']) {
        $approval['status'] = 'approved';
        $approval['reviewed_at'] = date('c');
        $approval['reviewed_by'] = $_SESSION['user']['id'];
        $approval['result'] = $result['data'];
        
        $storage->write($approvalId . '.json', $approval);
        
        log_security_event('ai_approval_approved', [
            'approval_id' => $approvalId,
            'reviewed_by' => $_SESSION['user']['id'],
            'action_type' => $approval['action_type'],
            'path' => $approval['action_data']['path'] ?? null,
        ]);
        
        echo json_encode([
            'status' => 'ok',
            'message' => 'Action approved and executed',
            'data' => ['approval' => $approval, 'result' => $result['data']]
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to execute action: ' . $result['message']
        ]);
    }
    exit;
}

// Reject action
if ($action === 'reject') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $approvalId = $input['id'] ?? '';
    $reason = $input['reason'] ?? 'No reason provided';
    
    if (empty($approvalId)) {
        echo json_encode(['status' => 'error', 'message' => 'Approval ID required']);
        exit;
    }
    
    $approval = $storage->read($approvalId . '.json');
    if (!$approval) {
        echo json_encode(['status' => 'error', 'message' => 'Approval not found']);
        exit;
    }
    
    if ($approval['status'] !== 'pending') {
        echo json_encode(['status' => 'error', 'message' => 'Approval already processed']);
        exit;
    }

    $currentUserId = $_SESSION['user']['id'] ?? '';
    if (!birkly_ai_user_can_review_approval($approval, $currentUserId, $_SESSION['user'] ?? [])) {
        echo json_encode(['status' => 'error', 'message' => 'You do not have permission to reject this action']);
        exit;
    }
    
    $approval['status'] = 'rejected';
    $approval['reviewed_at'] = date('c');
    $approval['reviewed_by'] = $_SESSION['user']['id'];
    $approval['rejection_reason'] = $reason;
    
    $storage->write($approvalId . '.json', $approval);
    
    log_security_event('ai_approval_rejected', [
        'approval_id' => $approvalId,
        'reviewed_by' => $_SESSION['user']['id'],
        'reason' => $reason
    ]);
    
    echo json_encode([
        'status' => 'ok',
        'message' => 'Action rejected',
        'data' => ['approval' => $approval]
    ]);
    exit;
}
}

// Execute AI action (internal function)
function executeAiAction($approval) {
    require_once __DIR__ . '/../core/ai_permissions.php';

    $actionType = $approval['action_type'];
    $actionData = $approval['action_data'] ?? [];
    if (!is_array($actionData)) {
        $actionData = birkly_ai_normalize_collection_action_data($actionData);
    }
    
    // Get AI user but DO NOT modify session - pass it as context instead
    require_once __DIR__ . '/../core/birkly_paths.php';
    require_once __DIR__ . '/../core/storage/FlatFileStorage.php';
    $usersStorage = new FlatFileStorage(birkly_project_root() . '/users');
    $aiUser = $approval['ai_user'] ?? null;
    if (!is_array($aiUser)) {
        $aiUser = $usersStorage->read($approval['ai_user_id'] . '.json');
    }
    if (!$aiUser) {
        return ['success' => false, 'message' => 'AI user not found'];
    }

    $denied = birkly_ai_enforce_action_permission($aiUser, $actionType);
    if ($denied !== null) {
        return $denied;
    }
    
    // Store original user ID for logging/attribution
    $originalUserId = $_SESSION['user']['id'] ?? null;
    
    // Log what we're about to execute
    error_log("executeAiAction: Action type: " . $actionType);
    error_log("executeAiAction: Action data: " . json_encode($actionData));
    
    // Execute action with AI user context (but don't modify session)
    switch ($actionType) {
        case 'create_collection':
            return executeCreateCollection($actionData, $aiUser, $originalUserId);
            
        case 'update_collection':
            return executeUpdateCollection($actionData, $aiUser, $originalUserId);
            
        case 'delete_collection':
            return executeDeleteCollection($actionData, $aiUser, $originalUserId);
            
        case 'create_entry':
            error_log("executeAiAction: Calling executeCreateEntry with actionData: " . json_encode($actionData));
            return executeCreateEntry($actionData, $aiUser, $originalUserId);
            
        case 'update_entry':
            return executeUpdateEntry($actionData, $aiUser, $originalUserId);
            
        case 'delete_entry':
            return executeDeleteEntry($actionData, $aiUser, $originalUserId);
            
        case 'upload_media':
            return executeUploadMedia($actionData, $aiUser, $originalUserId);
            
        case 'delete_media':
            return executeDeleteMedia($actionData, $aiUser, $originalUserId);
            
        case 'update_media':
            return executeUpdateMedia($actionData, $aiUser, $originalUserId);
            
        case 'create_media_folder':
            return executeCreateMediaFolder($actionData, $aiUser, $originalUserId);
            
        case 'delete_media_folder':
            return executeDeleteMediaFolder($actionData, $aiUser, $originalUserId);
            
        case 'move_media':
            return executeMoveMedia($actionData, $aiUser, $originalUserId);
            
        case 'update_settings':
            return executeUpdateSettings($actionData, $aiUser, $originalUserId);
            
        case 'create_page':
            return executeCreatePage($actionData, $aiUser, $originalUserId);
            
        case 'update_page':
            return executeUpdatePage($actionData, $aiUser, $originalUserId);
            
        case 'delete_page':
            return executeDeletePage($actionData, $aiUser, $originalUserId);
            
        case 'update_user':
            return executeUpdateUser($actionData, $aiUser, $originalUserId);
            
        case 'delete_user':
            return executeDeleteUser($actionData, $aiUser, $originalUserId);
            
        case 'create_role':
            return executeCreateRole($actionData, $aiUser, $originalUserId);
            
        case 'update_role':
            return executeUpdateRole($actionData, $aiUser, $originalUserId);
            
        case 'delete_role':
            return executeDeleteRole($actionData, $aiUser, $originalUserId);
            
        case 'activate_plugin':
            return executeActivatePlugin($actionData, $aiUser, $originalUserId);
            
        case 'deactivate_plugin':
            return executeDeactivatePlugin($actionData, $aiUser, $originalUserId);

        case 'create_sub_entry':
            return executeCreateSubEntry($actionData, $aiUser, $originalUserId);

        case 'update_sub_entry':
            return executeUpdateSubEntry($actionData, $aiUser, $originalUserId);

        case 'delete_sub_entry':
            return executeDeleteSubEntry($actionData, $aiUser, $originalUserId);

        case 'write_project_file':
            return executeWriteProjectFile($actionData, $aiUser, $originalUserId);

        case 'create_project_file':
            return executeCreateProjectFile($actionData, $aiUser, $originalUserId);

        case 'delete_project_file':
            return executeDeleteProjectFile($actionData, $aiUser, $originalUserId);

        case 'save_collection_groups':
            return executeSaveCollectionGroups($actionData, $aiUser, $originalUserId);

        case 'connections_health':
            return array_merge(['success' => true], birkly_connections_mcp_health($actionData));

        case 'connections_configure':
            $result = birkly_connections_mcp_configure($actionData);
            if (($result['status'] ?? '') !== 'ok') {
                return ['success' => false, 'message' => json_encode($result, JSON_UNESCAPED_SLASHES)];
            }
            return array_merge(['success' => true], $result);

        case 'connections_test':
            $result = birkly_connections_mcp_test($actionData);
            if (($result['status'] ?? '') !== 'ok') {
                return ['success' => false, 'message' => json_encode($result, JSON_UNESCAPED_SLASHES)];
            }
            return array_merge(['success' => true], $result);

        default:
            return ['success' => false, 'message' => 'Unknown action type: ' . $actionType];
    }
}

// Execute create collection
function executeCreateCollection($actionData, $aiUser = null, $originalUserId = null) {
    $actionData = birkly_ai_normalize_collection_action_data($actionData);
    $name = $actionData['name'] ?? '';
    $slug = $actionData['slug'] ?? '';
    $fields = $actionData['fields'] ?? [];
    $description = $actionData['description'] ?? '';
    
    if (empty($name)) {
        return ['success' => false, 'message' => 'Collection name is required'];
    }
    
    // Generate slug if not provided
    if (empty($slug)) {
        $slug = birkly_generate_slug($name);
    }

    $slug = birkly_normalize_slug($slug);

    // Validate slug format
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        return ['success' => false, 'message' => 'Invalid slug format'];
    }
    
    $collectionsPath = __DIR__ . '/../content/collections';
    $collectionDir = $collectionsPath . '/' . $slug;
    
    // Check if collection already exists
    if (is_dir($collectionDir)) {
        return ['success' => false, 'message' => 'Collection already exists'];
    }
    
    // Create collection directory
    if (!is_dir($collectionsPath)) {
        mkdir($collectionsPath, 0755, true);
    }
    
    if (!mkdir($collectionDir, 0755, true)) {
        return ['success' => false, 'message' => 'Failed to create collection directory'];
    }
    
    // Create entries directory
    $entriesDir = $collectionDir . '/entries';
    if (!mkdir($entriesDir, 0755, true)) {
        return ['success' => false, 'message' => 'Failed to create entries directory'];
    }
    
    // Prepare collection data
    $collectionData = [
        'name' => $name,
        'slug' => $slug,
        'description' => $description,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'entry_method' => 'admin_only',
    ];

    require_once __DIR__ . '/../core/ai_collection_config.php';
    $mergeError = birkly_ai_merge_collection_config($collectionData, $actionData, null);
    if ($mergeError !== null) {
        return ['success' => false, 'message' => $mergeError];
    }
    
    // Save collection.json
    $collectionFile = $collectionDir . '/collection.json';
    if (file_put_contents($collectionFile, json_encode($collectionData, JSON_PRETTY_PRINT)) === false) {
        return ['success' => false, 'message' => 'Failed to save collection.json'];
    }
    
    // Save schema.json with fields
    if (!empty($fields)) {
        $schemaData = ['fields' => $fields];
        $schemaFile = $collectionDir . '/schema.json';
        if (file_put_contents($schemaFile, json_encode($schemaData, JSON_PRETTY_PRINT)) === false) {
            return ['success' => false, 'message' => 'Failed to save schema.json'];
        }
    }
    
    // Use AI user ID or original user ID for logging
    $createdBy = $aiUser['id'] ?? $originalUserId ?? 'ai';
    
    log_security_event('ai_collection_created', [
        'collection_slug' => $slug,
        'collection_name' => $name,
        'created_by' => $createdBy,
        'ai_user_id' => $aiUser['id'] ?? null,
        'original_user_id' => $originalUserId
    ]);
    
    return ['success' => true, 'data' => ['collection_slug' => $slug, 'collection_name' => $name]];
}

// Execute create entry
/**
 * Ensure plugin hooks are available for AI/MCP entry writes (Commerce catalog, etc.).
 * Admin entry saves load plugins; MCP/AI approval path historically did not.
 */
function birkly_ai_ensure_plugins_loaded(): void
{
    require_once __DIR__ . '/../core/plugins.php';
    if (function_exists('load_plugins')) {
        load_plugins();
    }
}

/**
 * Mirror admin_entries save side-effects for AI/MCP entry writes.
 */
function birkly_ai_after_entry_write(array $entry, string $collectionSlug, ?array $oldEntry = null): void
{
    require_once __DIR__ . '/../core/cache.php';
    $cache = new Cache();
    $cache->invalidateCollection($collectionSlug);

    require_once __DIR__ . '/../core/project_ssr.php';
    if (function_exists('birkly_project_ssr_invalidate_all')) {
        birkly_project_ssr_invalidate_all();
    }

    birkly_ai_ensure_plugins_loaded();
    if (function_exists('do_action')) {
        do_action('after_entry_save', $entry, $collectionSlug, $oldEntry);
    }
}

/**
 * Mirror admin delete side-effects for AI/MCP entry deletes.
 */
function birkly_ai_after_entry_delete(array $entry, string $collectionSlug): void
{
    require_once __DIR__ . '/../core/cache.php';
    $cache = new Cache();
    $cache->invalidateCollection($collectionSlug);

    require_once __DIR__ . '/../core/project_ssr.php';
    if (function_exists('birkly_project_ssr_invalidate_all')) {
        birkly_project_ssr_invalidate_all();
    }

    birkly_ai_ensure_plugins_loaded();
    if (function_exists('do_action')) {
        do_action('after_entry_delete', $entry, $collectionSlug);
    }
}

function executeCreateEntry($actionData, $aiUser = null, $originalUserId = null) {
    error_log("executeCreateEntry: FUNCTION CALLED");
    error_log("executeCreateEntry: Received action data: " . json_encode($actionData));
    error_log("executeCreateEntry: Action data type: " . gettype($actionData));
    error_log("executeCreateEntry: Action data keys: " . (is_array($actionData) ? implode(', ', array_keys($actionData)) : 'not an array'));
    
    // Extract collection BEFORE require_once in case it modifies anything
    $collection = '';
    $entryData = [];
    
    // Handle different data structures from AI
    // Format 1: {collection: "slug", title: "...", content: "..."} - most common from AI
    // Format 2: {collection: "slug", data: {title: "...", content: "..."}}
    // Format 3: {data: {collection: "slug", title: "...", content: "..."}}
    
    // Try to get collection from various locations
    if (isset($actionData['collection'])) {
        error_log("executeCreateEntry: Found collection at top level: " . json_encode($actionData['collection']));
        $collection = $actionData['collection'];
        if (is_array($collection)) {
            $collection = $collection['slug'] ?? $collection['name'] ?? '';
        }
        // Check if there's nested data
        if (isset($actionData['data']) && is_array($actionData['data'])) {
            error_log("executeCreateEntry: Found nested data field");
            $entryData = $actionData['data'];
        } else {
            // Collection and fields are at the same level - this is the most common format
            error_log("executeCreateEntry: Collection and fields at same level");
            $entryData = $actionData;
            unset($entryData['collection']);
        }
    } elseif (isset($actionData['data']['collection'])) {
        error_log("executeCreateEntry: Found collection nested in data: " . json_encode($actionData['data']['collection']));
        // Collection is nested in data
        $collection = $actionData['data']['collection'];
        if (is_array($collection)) {
            $collection = $collection['slug'] ?? $collection['name'] ?? '';
        }
        $entryData = $actionData['data'];
        unset($entryData['collection']);
    } else {
        require_once __DIR__ . '/storage.php';
        require_once __DIR__ . '/../core/content_resolve.php';
        $contentStorage = birkly_ai_content_storage();
        $collection = birkly_ai_resolve_collection_slug_from_payload($actionData, $contentStorage);
        if ($collection === '') {
            error_log("executeCreateEntry: Collection not found in action data. Action data: " . json_encode($actionData));
            return ['success' => false, 'message' => 'Collection slug is required. Action data: ' . json_encode($actionData)];
        }
        $entryData = $actionData;
        foreach (['collection', 'slug', 'name', 'collection_slug', 'collectionSlug'] as $reserved) {
            unset($entryData[$reserved]);
        }
    }
    
    error_log("executeCreateEntry: Extracted collection: " . $collection);
    error_log("executeCreateEntry: Extracted entryData keys: " . implode(', ', array_keys($entryData)));
    
    try {
        $phaseStarted = microtime(true);
        require_once __DIR__ . '/storage.php';
        require_once __DIR__ . '/../core/content_resolve.php';

        error_log('executeCreateEntry: opening content storage');
        $contentStorage = birkly_ai_content_storage();
        error_log('executeCreateEntry: storage ready dt='
            . (int) round((microtime(true) - $phaseStarted) * 1000) . 'ms');

        $resolveStarted = microtime(true);
        $collectionDir = birkly_content_resolve_collection_dir_name($contentStorage, (string) $collection);
        error_log('executeCreateEntry: resolve collection dt='
            . (int) round((microtime(true) - $resolveStarted) * 1000) . 'ms');
        if ($collectionDir === null) {
            error_log("executeCreateEntry: Collection not found: " . $collection);
            return ['success' => false, 'message' => 'Collection not found: ' . $collection];
        }
        $collection = $collectionDir;

        require_once __DIR__ . '/../core/ai_write_validation.php';
        $unknownKeys = birkly_ai_reject_unknown_keys(
            $actionData,
            birkly_ai_entry_write_allowed_keys_for_collection($collection),
            'create_entry'
        );
        if ($unknownKeys !== null) {
            return ['success' => false, 'message' => $unknownKeys];
        }
        
        error_log("executeCreateEntry: Resolved collection dir: " . $collection);
        
        $collectionPath = birkly_ai_content_collections_dir() . '/' . $collection;
        
        // Generate entry ID
        $entryId = bin2hex(random_bytes(8));
        $entryFile = $collectionPath . '/entries/' . $entryId . '.json';
        
        // Ensure entries directory exists
        $entriesDir = dirname($entryFile);
        if (!is_dir($entriesDir)) {
            if (!mkdir($entriesDir, 0755, true)) {
                error_log("executeCreateEntry: Failed to create entries directory: " . $entriesDir);
                return ['success' => false, 'message' => 'Failed to create entries directory'];
            }
        }
        
        // Auto-fill title with entry ID if not provided
        if (empty(trim($entryData['title'] ?? ''))) {
            $entryData['title'] = $entryId;
        }

        require_once __DIR__ . '/../core/entry_slug.php';
        $title = trim((string) ($entryData['title'] ?? ''));
        $resolvedSlug = birkly_resolve_entry_slug('create', $entryData, null, $title, $entryId);
        if ($resolvedSlug !== '') {
            $entryData['slug'] = $resolvedSlug;
        } elseif (!empty($entryData['slug'])) {
            $entryData['slug'] = birkly_normalize_slug((string) $entryData['slug']);
        }
        
        // Use AI user ID or original user ID for attribution
        $createdBy = $aiUser['id'] ?? $originalUserId ?? 'ai';
        
        $entry = array_merge([
            'id' => $entryId,
            'created_at' => date('c'),
            'created_by' => $createdBy,
            'status' => 'draft',
        ], $entryData);
        
        require_once __DIR__ . '/../core/entry_storage.php';
        error_log("executeCreateEntry: Attempting to write entry: " . $entryId);
        $writeStarted = microtime(true);
        $result = birkly_entry_write_record($collection, $entryId, $entry);
        error_log('executeCreateEntry: write_record total dt='
            . (int) round((microtime(true) - $writeStarted) * 1000) . 'ms ok=' . ($result ? '1' : '0'));
        
        if ($result) {
            error_log("executeCreateEntry: Successfully created entry: " . $entryId);
            $hookStarted = microtime(true);
            birkly_ai_ensure_plugins_loaded();
            if (function_exists('do_action')) {
                do_action('after_entry_save', $entry, $collection, null);
            }
            error_log('executeCreateEntry: plugin hooks dt='
                . (int) round((microtime(true) - $hookStarted) * 1000) . 'ms');
            return ['success' => true, 'data' => ['entry_id' => $entryId, 'entry' => $entry]];
        } else {
            error_log("executeCreateEntry: Failed to write entry file for collection: " . $collection);
            return [
                'success' => false,
                'message' => 'Failed to write entry file (storage lock timeout, I/O error, or database unreachable). Check server logs.',
            ];
        }
    } catch (Throwable $e) {
        error_log("executeCreateEntry Throwable: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return ['success' => false, 'message' => 'Error creating entry: ' . $e->getMessage()];
    }
}

// Execute update entry
function executeUpdateEntry($actionData, $aiUser = null, $originalUserId = null) {
    error_log("executeUpdateEntry: FUNCTION CALLED");
    error_log("executeUpdateEntry: Received action data: " . json_encode($actionData));
    
    require_once __DIR__ . '/storage.php';
    
    // Handle different data structures from AI (same as create_entry)
    // Format 1: {collection: "slug", slug: "entry-slug", text: "..."} - most common from AI
    // Format 2: {collection: "slug", id: "entry-id", data: {text: "..."}}
    // Format 3: {data: {collection: "slug", id: "entry-id", text: "..."}}
    
    $collection = '';
    $entryId = '';
    $entrySlug = '';
    $updateData = [];
    
    // Try to get collection and entry identifier from various locations
    if (isset($actionData['collection'])) {
        $collection = $actionData['collection'];
        if (is_array($collection)) {
            $collection = $collection['slug'] ?? $collection['name'] ?? '';
        }
        // Check if there's nested data
        if (isset($actionData['data']) && is_array($actionData['data'])) {
            $updateData = $actionData['data'];
            $entryId = (string) ($actionData['id'] ?? $updateData['id'] ?? '');
            $entrySlug = (string) ($actionData['slug'] ?? $updateData['slug'] ?? '');
        } else {
            // Collection and fields are at the same level - this is the most common format
            $updateData = $actionData;
            unset($updateData['collection']);
            $entryId = $updateData['id'] ?? '';
            $entrySlug = $updateData['slug'] ?? '';
            // Remove id and slug from updateData as they're identifiers, not fields to update
            unset($updateData['id'], $updateData['slug']);
        }
    } elseif (isset($actionData['data']['collection'])) {
        $collection = $actionData['data']['collection'];
        if (is_array($collection)) {
            $collection = $collection['slug'] ?? $collection['name'] ?? '';
        }
        $updateData = $actionData['data'];
        unset($updateData['collection']);
        $entryId = (string) ($actionData['id'] ?? $updateData['id'] ?? '');
        $entrySlug = (string) ($actionData['slug'] ?? $updateData['slug'] ?? '');
        unset($updateData['id'], $updateData['slug']);
    } else {
        error_log("executeUpdateEntry: Collection not found in action data");
        return ['success' => false, 'message' => 'Collection is required'];
    }
    
    // Resolve collection directory (preserve legacy slugs like site_elements)
    require_once __DIR__ . '/../core/content_resolve.php';
    $contentStorage = birkly_ai_content_storage();
    $collectionDir = birkly_content_resolve_collection_dir_name($contentStorage, (string) $collection);
    if ($collectionDir === null) {
        return ['success' => false, 'message' => 'Collection not found: ' . $collection];
    }
    $collection = $collectionDir;

    require_once __DIR__ . '/../core/ai_write_validation.php';
    $unknownKeys = birkly_ai_reject_unknown_keys(
        $actionData,
        birkly_ai_entry_write_allowed_keys_for_collection($collection),
        'update_entry'
    );
    if ($unknownKeys !== null) {
        return ['success' => false, 'message' => $unknownKeys];
    }

    if (!empty($entrySlug)) {
        $entrySlug = birkly_normalize_slug($entrySlug);
    }
    
    if (empty($entryId) && empty($entrySlug)) {
        return ['success' => false, 'message' => 'Entry ID or slug is required'];
    }
    
    // Find entry by ID or slug
    $entry = null;
    $entryFile = null;
    
    if (!empty($entryId)) {
        // Try by ID first
        $entryFile = 'collections/' . $collection . '/entries/' . $entryId . '.json';
        $entry = $contentStorage->read($entryFile);
    }
    
    if (!$entry && !empty($entrySlug)) {
        // Try by slug
        $entries = $contentStorage->list('collections/' . $collection . '/entries');
        foreach ($entries as $file) {
            $entryData = $contentStorage->read('collections/' . $collection . '/entries/' . $file);
            if ($entryData && isset($entryData['slug']) && $entryData['slug'] === $entrySlug) {
                $entry = $entryData;
                $entryFile = 'collections/' . $collection . '/entries/' . $file;
                $entryId = $entryData['id'] ?? pathinfo($file, PATHINFO_FILENAME);
                break;
            }
        }
    }
    
    if (!$entry) {
        error_log("executeUpdateEntry: Entry not found. Collection: $collection, ID: $entryId, Slug: $entrySlug");
        return ['success' => false, 'message' => 'Entry not found'];
    }
    
    // Use AI user ID or original user ID for attribution
    $updatedBy = $aiUser['id'] ?? $originalUserId ?? 'ai';
    $oldEntry = $entry;
    
    // Merge updates (only update fields that are provided)
    foreach ($updateData as $key => $value) {
        if ($key === 'slug' && is_string($value)) {
            $entry[$key] = birkly_normalize_slug($value);
        } else {
            $entry[$key] = $value;
        }
    }
    
    // Auto-fill title with entry ID if not provided or empty
    if (empty(trim($entry['title'] ?? ''))) {
        $entry['title'] = $entryId;
    }
    
    $entry['updated_at'] = date('c');
    $entry['updated_by'] = $updatedBy;
    
    // Ensure we have the correct file path
    if (!$entryFile) {
        $entryFile = 'collections/' . $collection . '/entries/' . $entryId . '.json';
    }
    
    error_log("executeUpdateEntry: Updating entry file: " . $entryFile);
    $result = $contentStorage->write($entryFile, $entry);
    
    if ($result) {
        error_log("executeUpdateEntry: Successfully updated entry");
        birkly_ai_after_entry_write($entry, $collection, $oldEntry);
        return ['success' => true, 'data' => ['entry_id' => $entryId, 'entry' => $entry]];
    } else {
        error_log("executeUpdateEntry: Failed to write entry file");
        return ['success' => false, 'message' => 'Failed to update entry'];
    }
}

// Execute delete entry
function executeDeleteEntry($actionData, $aiUser = null, $originalUserId = null) {
    require_once __DIR__ . '/storage.php';
    require_once __DIR__ . '/../core/content_resolve.php';
    
    $collection = $actionData['collection'] ?? '';
    $entryId = $actionData['id'] ?? '';
    
    if (empty($collection) || empty($entryId)) {
        return ['success' => false, 'message' => 'Collection and entry ID are required'];
    }
    
    $contentStorage = birkly_ai_content_storage();
    $collectionDir = birkly_content_resolve_collection_dir_name($contentStorage, (string) $collection);
    if ($collectionDir === null) {
        return ['success' => false, 'message' => 'Collection not found: ' . $collection];
    }

    $entryFile = 'collections/' . $collectionDir . '/entries/' . $entryId . '.json';
    $existing = $contentStorage->read($entryFile);
    
    $result = $contentStorage->delete($entryFile);
    
    if ($result) {
        if (is_array($existing)) {
            birkly_ai_after_entry_delete($existing, $collectionDir);
        } else {
            birkly_ai_after_entry_delete(['id' => $entryId], $collectionDir);
        }
        return ['success' => true, 'data' => []];
    } else {
        return ['success' => false, 'message' => 'Failed to delete entry'];
    }
}

// Execute update collection
function executeUpdateCollection($actionData, $aiUser = null, $originalUserId = null) {
    try {
        return birkly_ai_execute_update_collection($actionData, $aiUser, $originalUserId);
    } catch (Throwable $e) {
        error_log('executeUpdateCollection: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update collection: ' . $e->getMessage()];
    }
}

function birkly_ai_execute_update_collection($actionData, $aiUser = null, $originalUserId = null) {
    require_once __DIR__ . '/storage.php';
    require_once __DIR__ . '/../core/content_resolve.php';

    $actionData = birkly_ai_normalize_collection_action_data($actionData);
    $contentStorage = birkly_ai_content_storage();
    $slugRef = birkly_ai_resolve_collection_slug_from_payload($actionData, $contentStorage);
    if ($slugRef === '') {
        return ['success' => false, 'message' => 'Collection slug is required. Action data: ' . json_encode($actionData)];
    }

    $slug = birkly_content_resolve_collection_dir_name($contentStorage, $slugRef);
    if ($slug === null) {
        return ['success' => false, 'message' => 'Collection not found'];
    }
    
    $collectionFile = birkly_ai_content_collections_dir() . '/' . $slug . '/collection.json';
    
    $collection = json_decode(file_get_contents($collectionFile), true);
    if (!$collection) {
        return ['success' => false, 'message' => 'Failed to read collection'];
    }
    
    // Update metadata
    if (isset($actionData['name'])) $collection['name'] = $actionData['name'];
    if (isset($actionData['description'])) $collection['description'] = $actionData['description'];
    $collection['updated_at'] = date('c');

    if (isset($actionData['fields']) && is_array($actionData['fields'])) {
        require_once __DIR__ . '/../core/collection_schema.php';
        require_once __DIR__ . '/../core/ai_write_validation.php';
        $fieldTypeError = birkly_ai_validate_collection_field_types($actionData['fields']);
        if ($fieldTypeError !== null) {
            return ['success' => false, 'message' => $fieldTypeError];
        }
        $schemaFile = birkly_ai_content_collections_dir() . '/' . $slug . '/schema.json';
        $schema = birkly_normalize_collection_schema($actionData);
        if (file_put_contents($schemaFile, json_encode($schema, JSON_PRETTY_PRINT)) === false) {
            return ['success' => false, 'message' => 'Failed to update collection schema'];
        }
    }

    require_once __DIR__ . '/../core/ai_collection_config.php';
    $mergeError = birkly_ai_merge_collection_config($collection, $actionData, $collection);
    if ($mergeError !== null) {
        return ['success' => false, 'message' => $mergeError];
    }
    
    $result = file_put_contents($collectionFile, json_encode($collection, JSON_PRETTY_PRINT));
    if (!$result) {
        return ['success' => false, 'message' => 'Failed to update collection'];
    }

    $stored = birkly_ai_load_collection_record($slug);

    return ['success' => true, 'data' => ['collection' => $stored ?? $collection]];
}

function executeSaveCollectionGroups($actionData, $aiUser = null, $originalUserId = null) {
    require_once __DIR__ . '/../core/collection_groups.php';

    $groups = $actionData['groups'] ?? [];
    if (!is_array($groups)) {
        return ['success' => false, 'message' => 'groups must be an array'];
    }

    $normalized = birkly_normalize_collection_groups($groups);
    $validationError = birkly_validate_collection_groups($normalized);
    if ($validationError !== null) {
        return ['success' => false, 'message' => $validationError];
    }

    if (!birkly_save_collection_groups($normalized)) {
        return ['success' => false, 'message' => 'Failed to save collection groups'];
    }

    return ['success' => true, 'data' => ['collection_groups' => $normalized]];
}

// Recursively delete a directory (shared by AI delete actions).
function birkly_ai_delete_directory(string $dir): bool
{
    if (!is_dir($dir)) {
        return false;
    }
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? birkly_ai_delete_directory($path) : unlink($path);
    }
    return rmdir($dir);
}

// Execute delete collection
function executeDeleteCollection($actionData, $aiUser = null, $originalUserId = null) {
    try {
        return birkly_ai_execute_delete_collection($actionData, $aiUser, $originalUserId);
    } catch (Throwable $e) {
        error_log('executeDeleteCollection: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to delete collection: ' . $e->getMessage()];
    }
}

function birkly_ai_execute_delete_collection($actionData, $aiUser = null, $originalUserId = null) {
    require_once __DIR__ . '/storage.php';
    require_once __DIR__ . '/../core/content_resolve.php';

    $actionData = birkly_ai_normalize_collection_action_data($actionData);
    $contentStorage = birkly_ai_content_storage();
    $slug = birkly_ai_resolve_collection_slug_from_payload($actionData, $contentStorage);
    if ($slug === '') {
        return ['success' => false, 'message' => 'Collection slug is required. Action data: ' . json_encode($actionData)];
    }

    $slug = birkly_content_resolve_collection_dir_name($contentStorage, $slug) ?? $slug;
    
    $collectionDir = birkly_ai_content_collections_dir() . '/' . $slug;
    if (!is_dir($collectionDir)) {
        return ['success' => false, 'message' => 'Collection not found'];
    }
    
    $result = birkly_ai_delete_directory($collectionDir);
    return $result ? ['success' => true, 'data' => []] : ['success' => false, 'message' => 'Failed to delete collection'];
}

// Execute upload media
function executeUploadMedia($actionData, $aiUser = null, $originalUserId = null) {
    $url = $actionData['url'] ?? '';
    $base64 = $actionData['base64'] ?? '';
    $name = $actionData['name'] ?? 'uploaded-file';
    $folder = $actionData['folder'] ?? '';
    
    if (empty($url) && empty($base64)) {
        return ['success' => false, 'message' => 'URL or base64 data is required'];
    }
    
    $mediaDir = __DIR__ . '/../media';
    if ($folder) {
        $mediaDir .= '/' . $folder;
    }
    if (!is_dir($mediaDir)) {
        mkdir($mediaDir, 0755, true);
    }
    
    // Handle URL download
    if ($url) {
        $fileContent = @file_get_contents($url);
        if ($fileContent === false) {
            return ['success' => false, 'message' => 'Failed to download file from URL'];
        }
        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
    } else {
        // Handle base64
        $fileContent = base64_decode($base64);
        if ($fileContent === false) {
            return ['success' => false, 'message' => 'Invalid base64 data'];
        }
        $extension = pathinfo($name, PATHINFO_EXTENSION) ?: 'jpg';
    }
    
    $mediaId = uniqid();
    $filename = $mediaId . '.' . $extension;
    $filepath = $mediaDir . '/' . $filename;
    
    if (file_put_contents($filepath, $fileContent) === false) {
        return ['success' => false, 'message' => 'Failed to save file'];
    }
    
    // Update metadata
    $metadataFile = __DIR__ . '/../media/.metadata.json';
    $metadata = file_exists($metadataFile) ? json_decode(file_get_contents($metadataFile), true) : [];
    $metadata[$mediaId] = [
        'name' => $name,
        'type' => mime_content_type($filepath),
        'size' => filesize($filepath),
        'ext' => $extension,
        'folder' => $folder,
        'created_at' => date('c')
    ];
    file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT));
    
    return ['success' => true, 'data' => ['media_id' => $mediaId, 'url' => '/media/' . ($folder ? $folder . '/' : '') . $filename]];
}

// Execute delete media
function executeDeleteMedia($actionData, $aiUser = null, $originalUserId = null) {
    $mediaId = $actionData['id'] ?? '';
    if (empty($mediaId)) {
        return ['success' => false, 'message' => 'Media ID is required'];
    }
    
    $metadataFile = __DIR__ . '/../media/.metadata.json';
    $metadata = file_exists($metadataFile) ? json_decode(file_get_contents($metadataFile), true) : [];
    
    if (!isset($metadata[$mediaId])) {
        return ['success' => false, 'message' => 'Media not found'];
    }
    
    $media = $metadata[$mediaId];
    $folder = $media['folder'] ?? '';
    $ext = $media['ext'] ?? '';
    $filepath = __DIR__ . '/../media/' . ($folder ? $folder . '/' : '') . $mediaId . '.' . $ext;
    
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    
    unset($metadata[$mediaId]);
    file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT));
    
    return ['success' => true, 'data' => []];
}

// Execute update media
function executeUpdateMedia($actionData, $aiUser = null, $originalUserId = null) {
    $mediaId = $actionData['id'] ?? '';
    if (empty($mediaId)) {
        return ['success' => false, 'message' => 'Media ID is required'];
    }
    
    $metadataFile = __DIR__ . '/../media/.metadata.json';
    $metadata = file_exists($metadataFile) ? json_decode(file_get_contents($metadataFile), true) : [];
    
    if (!isset($metadata[$mediaId])) {
        return ['success' => false, 'message' => 'Media not found'];
    }
    
    if (isset($actionData['name'])) $metadata[$mediaId]['name'] = $actionData['name'];
    if (isset($actionData['alt'])) $metadata[$mediaId]['alt'] = $actionData['alt'];
    
    file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT));
    return ['success' => true, 'data' => ['media' => $metadata[$mediaId]]];
}

// Execute create media folder
function executeCreateMediaFolder($actionData, $aiUser = null, $originalUserId = null) {
    $folderName = $actionData['name'] ?? '';
    if (empty($folderName)) {
        return ['success' => false, 'message' => 'Folder name is required'];
    }
    
    $folderPath = __DIR__ . '/../media/' . $folderName;
    if (is_dir($folderPath)) {
        return ['success' => false, 'message' => 'Folder already exists'];
    }
    
    $result = mkdir($folderPath, 0755, true);
    return $result ? ['success' => true, 'data' => ['folder' => $folderName]] : ['success' => false, 'message' => 'Failed to create folder'];
}

// Execute delete media folder
function executeDeleteMediaFolder($actionData, $aiUser = null, $originalUserId = null) {
    $folderName = $actionData['name'] ?? '';
    if (empty($folderName)) {
        return ['success' => false, 'message' => 'Folder name is required'];
    }
    
    $folderPath = __DIR__ . '/../media/' . $folderName;
    if (!is_dir($folderPath)) {
        return ['success' => false, 'message' => 'Folder not found'];
    }
    
    function deleteMediaFolder($dir) {
        if (!is_dir($dir)) return false;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? deleteMediaFolder($path) : unlink($path);
        }
        return rmdir($dir);
    }
    
    $result = deleteMediaFolder($folderPath);
    return $result ? ['success' => true, 'data' => []] : ['success' => false, 'message' => 'Failed to delete folder'];
}

// Execute move media
function executeMoveMedia($actionData, $aiUser = null, $originalUserId = null) {
    $mediaId = $actionData['id'] ?? '';
    $folder = $actionData['folder'] ?? '';
    
    if (empty($mediaId)) {
        return ['success' => false, 'message' => 'Media ID is required'];
    }
    
    $metadataFile = __DIR__ . '/../media/.metadata.json';
    $metadata = file_exists($metadataFile) ? json_decode(file_get_contents($metadataFile), true) : [];
    
    if (!isset($metadata[$mediaId])) {
        return ['success' => false, 'message' => 'Media not found'];
    }
    
    $media = $metadata[$mediaId];
    $oldFolder = $media['folder'] ?? '';
    $ext = $media['ext'] ?? '';
    
    $oldPath = __DIR__ . '/../media/' . ($oldFolder ? $oldFolder . '/' : '') . $mediaId . '.' . $ext;
    $newPath = __DIR__ . '/../media/' . ($folder ? $folder . '/' : '') . $mediaId . '.' . $ext;
    
    if ($folder && !is_dir(dirname($newPath))) {
        mkdir(dirname($newPath), 0755, true);
    }
    
    if (file_exists($oldPath)) {
        rename($oldPath, $newPath);
    }
    
    $metadata[$mediaId]['folder'] = $folder;
    file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT));
    
    return ['success' => true, 'data' => ['media' => $metadata[$mediaId]]];
}

// Execute update settings
function executeUpdateSettings($actionData, $aiUser = null, $originalUserId = null) {
    require_once __DIR__ . '/storage.php';
    require_once __DIR__ . '/../core/ai_write_validation.php';

    $unknownKeys = birkly_ai_reject_unknown_keys($actionData, birkly_ai_update_settings_allowed_keys(), 'update_settings');
    if ($unknownKeys !== null) {
        return ['success' => false, 'message' => $unknownKeys];
    }

    $settingsStorage = new Storage('../settings');
    $category = $actionData['category'] ?? '';
    $settings = $actionData['settings'] ?? $actionData['data'] ?? [];
    
    if (empty($category) || empty($settings)) {
        return ['success' => false, 'message' => 'Category and settings are required'];
    }
    
    $current = $settingsStorage->read("$category.json") ?: [];
    $updated = array_merge($current, $settings);
    
    $result = $settingsStorage->write("$category.json", $updated);
    return $result ? ['success' => true, 'data' => ['settings' => $updated]] : ['success' => false, 'message' => 'Failed to update settings'];
}

// Execute create page
function executeCreatePage($actionData, $aiUser = null, $originalUserId = null) {
    require_once __DIR__ . '/storage.php';
    $contentStorage = birkly_ai_content_storage();
    
    $title = $actionData['title'] ?? '';
    $slug = $actionData['slug'] ?? '';
    $content = $actionData['content'] ?? '';
    
    if (empty($title)) {
        return ['success' => false, 'message' => 'Page title is required'];
    }
    
    if (empty($slug)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    }
    
    $pageId = uniqid();
    $page = [
        'id' => $pageId,
        'title' => $title,
        'slug' => $slug,
        'content' => $content,
        'created_at' => date('c'),
        'updated_at' => date('c')
    ];
    
    $result = $contentStorage->write("pages/$slug.json", $page);
    return $result ? ['success' => true, 'data' => ['page' => $page]] : ['success' => false, 'message' => 'Failed to create page'];
}

// Execute update page
function executeUpdatePage($actionData, $aiUser = null, $originalUserId = null) {
    require_once __DIR__ . '/storage.php';
    require_once __DIR__ . '/../core/ai_write_validation.php';

    $unknownKeys = birkly_ai_reject_unknown_keys($actionData, birkly_ai_update_page_allowed_keys(), 'update_page');
    if ($unknownKeys !== null) {
        return ['success' => false, 'message' => $unknownKeys];
    }

    $contentStorage = birkly_ai_content_storage();
    
    $slug = $actionData['slug'] ?? $actionData['id'] ?? '';
    if (empty($slug)) {
        return ['success' => false, 'message' => 'Page slug or ID is required'];
    }
    
    $pageFile = "pages/$slug.json";
    $page = $contentStorage->read($pageFile);
    if (!$page) {
        return ['success' => false, 'message' => 'Page not found'];
    }
    
    if (isset($actionData['title'])) $page['title'] = $actionData['title'];
    if (isset($actionData['content'])) $page['content'] = $actionData['content'];
    if (isset($actionData['slug'])) $page['slug'] = $actionData['slug'];
    $page['updated_at'] = date('c');
    
    $result = $contentStorage->write($pageFile, $page);
    return $result ? ['success' => true, 'data' => ['page' => $page]] : ['success' => false, 'message' => 'Failed to update page'];
}

// Execute delete page
function executeDeletePage($actionData, $aiUser = null, $originalUserId = null) {
    require_once __DIR__ . '/storage.php';
    $contentStorage = birkly_ai_content_storage();
    
    $slug = $actionData['slug'] ?? $actionData['id'] ?? '';
    if (empty($slug)) {
        return ['success' => false, 'message' => 'Page slug or ID is required'];
    }
    
    $result = $contentStorage->delete("pages/$slug.json");
    return $result ? ['success' => true, 'data' => []] : ['success' => false, 'message' => 'Failed to delete page'];
}

// Execute update user
function executeUpdateUser($actionData, $aiUser = null, $originalUserId = null) {
    require_once __DIR__ . '/storage.php';
    require_once __DIR__ . '/../core/ai_write_validation.php';

    $unknownKeys = birkly_ai_reject_unknown_keys($actionData, birkly_ai_update_user_allowed_keys(), 'update_user');
    if ($unknownKeys !== null) {
        return ['success' => false, 'message' => $unknownKeys];
    }

    $usersStorage = new Storage('../users');
    
    $userId = $actionData['id'] ?? $actionData['email'] ?? '';
    if (empty($userId)) {
        return ['success' => false, 'message' => 'User ID or email is required'];
    }
    
    $userFile = $userId . '.json';
    $user = $usersStorage->read($userFile);
    if (!$user) {
        // Try to find by email
        $userFiles = glob('../users/*.json');
        foreach ($userFiles as $file) {
            $userData = json_decode(file_get_contents($file), true);
            if ($userData && $userData['email'] === $userId) {
                $user = $userData;
                $userFile = basename($file);
                break;
            }
        }
    }
    
    if (!$user) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    if (isset($actionData['email'])) $user['email'] = $actionData['email'];
    if (isset($actionData['role'])) $user['role'] = $actionData['role'];
    if (isset($actionData['name'])) $user['name'] = $actionData['name'];
    // Never update password through AI
    
    $result = $usersStorage->write($userFile, $user);
    return $result ? ['success' => true, 'data' => ['user' => $user]] : ['success' => false, 'message' => 'Failed to update user'];
}

// Execute delete user
function executeDeleteUser($actionData, $aiUser = null, $originalUserId = null) {
    require_once __DIR__ . '/storage.php';
    $usersStorage = new Storage('../users');
    
    $userId = $actionData['id'] ?? $actionData['email'] ?? '';
    if (empty($userId)) {
        return ['success' => false, 'message' => 'User ID or email is required'];
    }
    
    // Don't allow deleting yourself
    if ($userId === ($aiUser['id'] ?? '') || $userId === ($originalUserId ?? '')) {
        return ['success' => false, 'message' => 'Cannot delete your own user account'];
    }
    
    $userFile = $userId . '.json';
    $result = $usersStorage->delete($userFile);
    return $result ? ['success' => true, 'data' => []] : ['success' => false, 'message' => 'Failed to delete user'];
}

// Execute create role
function executeCreateRole($actionData, $aiUser = null, $originalUserId = null) {
    require_once __DIR__ . '/storage.php';
    $settingsStorage = new Storage('../settings');
    
    $name = $actionData['name'] ?? '';
    $permissions = $actionData['permissions'] ?? [];
    
    if (empty($name)) {
        return ['success' => false, 'message' => 'Role name is required'];
    }
    
    $roles = $settingsStorage->read('roles.json') ?: [];
    $roleId = strtolower(trim(preg_replace('/[^a-z0-9-]+/', '-', $name)));
    
    // Check if role already exists
    foreach ($roles as $role) {
        if (($role['id'] ?? '') === $roleId || ($role['name'] ?? '') === $name) {
            return ['success' => false, 'message' => 'Role already exists'];
        }
    }
    
    $role = [
        'id' => $roleId,
        'name' => $name,
        'description' => $actionData['description'] ?? '',
        'permissions' => $permissions
    ];
    
    $roles[] = $role;
    $result = $settingsStorage->write('roles.json', $roles);
    return $result ? ['success' => true, 'data' => ['role' => $role]] : ['success' => false, 'message' => 'Failed to create role'];
}

// Execute update role
function executeUpdateRole($actionData, $aiUser = null, $originalUserId = null) {
    require_once __DIR__ . '/storage.php';
    $settingsStorage = new Storage('../settings');
    
    $roleId = $actionData['id'] ?? '';
    if (empty($roleId)) {
        return ['success' => false, 'message' => 'Role ID is required'];
    }
    
    $roles = $settingsStorage->read('roles.json') ?: [];
    $roleFound = false;
    
    foreach ($roles as &$role) {
        if (($role['id'] ?? '') === $roleId) {
            if (isset($actionData['name'])) $role['name'] = $actionData['name'];
            if (isset($actionData['description'])) $role['description'] = $actionData['description'];
            if (isset($actionData['permissions'])) $role['permissions'] = $actionData['permissions'];
            $roleFound = true;
            break;
        }
    }
    
    if (!$roleFound) {
        return ['success' => false, 'message' => 'Role not found'];
    }
    
    $result = $settingsStorage->write('roles.json', $roles);
    return $result ? ['success' => true, 'data' => ['role' => $role]] : ['success' => false, 'message' => 'Failed to update role'];
}

// Execute delete role
function executeDeleteRole($actionData, $aiUser = null, $originalUserId = null) {
    require_once __DIR__ . '/storage.php';
    $settingsStorage = new Storage('../settings');
    
    $roleId = $actionData['id'] ?? '';
    if (empty($roleId)) {
        return ['success' => false, 'message' => 'Role ID is required'];
    }
    
    $roles = $settingsStorage->read('roles.json') ?: [];
    $roles = array_filter($roles, function($role) use ($roleId) {
        return ($role['id'] ?? '') !== $roleId;
    });
    
    $result = $settingsStorage->write('roles.json', array_values($roles));
    return $result ? ['success' => true, 'data' => []] : ['success' => false, 'message' => 'Failed to delete role'];
}

// Execute activate plugin
function executeActivatePlugin($actionData, $aiUser = null, $originalUserId = null) {
    require_once __DIR__ . '/storage.php';
    $settingsStorage = new Storage('../settings');
    
    $pluginId = $actionData['id'] ?? '';
    if (empty($pluginId)) {
        return ['success' => false, 'message' => 'Plugin ID is required'];
    }
    
    $plugins = $settingsStorage->read('plugins.json') ?: [];
    $plugins[$pluginId] = ['active' => true, 'activated_at' => date('c')];
    
    $result = $settingsStorage->write('plugins.json', $plugins);
    return $result ? ['success' => true, 'data' => []] : ['success' => false, 'message' => 'Failed to activate plugin'];
}

// Execute deactivate plugin
function executeDeactivatePlugin($actionData, $aiUser = null, $originalUserId = null) {
    require_once __DIR__ . '/storage.php';
    $settingsStorage = new Storage('../settings');
    
    $pluginId = $actionData['id'] ?? '';
    if (empty($pluginId)) {
        return ['success' => false, 'message' => 'Plugin ID is required'];
    }
    
    $plugins = $settingsStorage->read('plugins.json') ?: [];
    if (isset($plugins[$pluginId])) {
        $plugins[$pluginId]['active'] = false;
    }
    
    $result = $settingsStorage->write('plugins.json', $plugins);
    return $result ? ['success' => true, 'data' => []] : ['success' => false, 'message' => 'Failed to deactivate plugin'];
}

// Execute create sub-entry
function executeCreateSubEntry($actionData, $aiUser = null, $originalUserId = null) {
    require_once __DIR__ . '/../core/sub_entries.php';

    $parentCollection = $actionData['collection'] ?? '';
    $parentEntryId = $actionData['entry_id'] ?? '';
    $subCollection = $actionData['sub_collection'] ?? '';
    $data = $actionData['data'] ?? $actionData;

    // Remove routing keys from data payload when merged at top level
    foreach (['collection', 'entry_id', 'sub_collection'] as $k) {
        unset($data[$k]);
    }

    if (empty($parentCollection) || empty($parentEntryId) || empty($subCollection)) {
        return ['success' => false, 'message' => 'collection, entry_id, and sub_collection are required'];
    }

    $result = createSubEntry($parentCollection, $parentEntryId, $subCollection, $data);
    if (!$result) {
        return ['success' => false, 'message' => 'Failed to create sub-entry'];
    }

    return ['success' => true, 'data' => ['sub_entry' => $result]];
}

// Execute update sub-entry
function executeUpdateSubEntry($actionData, $aiUser = null, $originalUserId = null) {
    require_once __DIR__ . '/../core/sub_entries.php';

    $parentCollection = $actionData['collection'] ?? '';
    $parentEntryId = $actionData['entry_id'] ?? '';
    $subCollection = $actionData['sub_collection'] ?? '';
    $subEntryId = $actionData['sub_entry_id'] ?? '';
    $data = $actionData['data'] ?? [];

    if (empty($parentCollection) || empty($parentEntryId) || empty($subCollection) || empty($subEntryId)) {
        return ['success' => false, 'message' => 'collection, entry_id, sub_collection, and sub_entry_id are required'];
    }

    $result = updateSubEntry($parentCollection, $parentEntryId, $subCollection, $subEntryId, $data);
    if (!$result) {
        return ['success' => false, 'message' => 'Failed to update sub-entry (not found or write error)'];
    }

    return ['success' => true, 'data' => ['sub_entry' => $result]];
}

// Execute delete sub-entry
function executeDeleteSubEntry($actionData, $aiUser = null, $originalUserId = null) {
    require_once __DIR__ . '/../core/sub_entries.php';

    $parentCollection = $actionData['collection'] ?? '';
    $parentEntryId = $actionData['entry_id'] ?? '';
    $subCollection = $actionData['sub_collection'] ?? '';
    $subEntryId = $actionData['sub_entry_id'] ?? '';

    if (empty($parentCollection) || empty($parentEntryId) || empty($subCollection) || empty($subEntryId)) {
        return ['success' => false, 'message' => 'collection, entry_id, sub_collection, and sub_entry_id are required'];
    }

    $result = deleteSubEntry($parentCollection, $parentEntryId, $subCollection, $subEntryId);
    if (!$result) {
        return ['success' => false, 'message' => 'Failed to delete sub-entry (not found or write error)'];
    }

    return ['success' => true, 'data' => []];
}

function executeWriteProjectFile($actionData, $aiUser = null, $originalUserId = null) {
    require_once __DIR__ . '/../core/project_site_filesystem.php';
    require_once __DIR__ . '/ai_helpers.php';

    $path = birkly_ai_normalize_project_file_path((string) ($actionData['path'] ?? ''));
    $content = (string) ($actionData['content'] ?? '');
    if ($path === '') {
        return ['success' => false, 'message' => 'path is required'];
    }

    $fs = new ProjectSiteFileSystem();
    if (!$fs->writeFile($path, $content)) {
        return ['success' => false, 'message' => 'Failed to write file (path blocked or invalid): ' . $path];
    }

    log_security_event('project_file_written', [
        'path' => $path,
        'source' => 'ai',
        'ai_user_id' => is_array($aiUser) ? ($aiUser['id'] ?? null) : null,
    ]);

    return ['success' => true, 'data' => ['path' => $path, 'written' => true]];
}

function executeCreateProjectFile($actionData, $aiUser = null, $originalUserId = null) {
    require_once __DIR__ . '/../core/project_site_filesystem.php';
    require_once __DIR__ . '/ai_helpers.php';

    $path = birkly_ai_normalize_project_file_path((string) ($actionData['path'] ?? ''));
    $content = (string) ($actionData['content'] ?? '');
    if ($path === '') {
        return ['success' => false, 'message' => 'path is required'];
    }

    $fs = new ProjectSiteFileSystem();
    if (!$fs->createFile($path, $content)) {
        return ['success' => false, 'message' => 'Failed to create file (exists, blocked, or invalid): ' . $path];
    }

    log_security_event('project_file_created', [
        'path' => $path,
        'source' => 'ai',
        'ai_user_id' => is_array($aiUser) ? ($aiUser['id'] ?? null) : null,
    ]);

    return ['success' => true, 'data' => ['path' => $path, 'created' => true]];
}

function executeDeleteProjectFile($actionData, $aiUser = null, $originalUserId = null) {
    require_once __DIR__ . '/../core/project_site_filesystem.php';
    require_once __DIR__ . '/ai_helpers.php';

    $path = birkly_ai_normalize_project_file_path_for_delete((string) ($actionData['path'] ?? ''));
    if ($path === '') {
        return ['success' => false, 'message' => 'path is required'];
    }

    $blockReason = birkly_ai_validate_delete_project_file_path($path);
    if ($blockReason !== null) {
        return ['success' => false, 'message' => $blockReason];
    }

    $fs = new ProjectSiteFileSystem();
    if (!$fs->deleteFile($path)) {
        return ['success' => false, 'message' => 'Failed to delete file (not found or blocked): ' . $path];
    }

    log_security_event('project_file_deleted', [
        'path' => $path,
        'source' => 'ai',
        'ai_user_id' => is_array($aiUser) ? ($aiUser['id'] ?? null) : null,
    ]);

    return ['success' => true, 'data' => ['path' => $path, 'deleted' => true]];
}

if (!isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
}

