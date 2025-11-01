<?php
// Prevent double execution
if (defined('ADMIN_ENTRIES_PHP_EXECUTED')) {
    // Clear any output and exit immediately
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    exit;
}
define('ADMIN_ENTRIES_PHP_EXECUTED', true);

// CRITICAL: Check authentication FIRST before any other logic
require_once __DIR__ . '/auth.php';
haru_secure_session_start();
if (!isset($_SESSION['user'])) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code(401);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Only set headers if this file is being called directly (not included)
// When included via admin/index.php, headers are already sent, so skip
if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    header('Content-Type: application/json');
    
    // Enable CORS for development
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Start output buffering to avoid stray output breaking JSON
ob_start();

// Suppress deprecation warnings for now (they'll still be logged)
// E_STRICT is deprecated in PHP 8.0+, so don't use it
// Use E_ALL without E_DEPRECATED (E_STRICT is deprecated so we ignore it)
$oldErrorReporting = error_reporting(E_ALL & ~E_DEPRECATED);
$oldDisplayErrors = ini_get('display_errors');
ini_set('display_errors', '0');

// CRITICAL: Check if there's any output before our code
$outputBeforeCode = ob_get_contents();
if (!empty($outputBeforeCode)) {
    ob_end_clean();
    error_reporting($oldErrorReporting);
    ini_set('display_errors', $oldDisplayErrors);
    http_response_code(500);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode([
        'status' => 'error',
        'message' => 'Output detected before code execution',
        'debug' => [
            'output_before_code' => $outputBeforeCode,
            'output_length' => strlen($outputBeforeCode)
        ]
    ]);
    exit;
}

// Get action from REQUEST_URI query string (most reliable for POST requests)
$action = 'save'; // Default

// Parse REQUEST_URI for action parameter
if (isset($_SERVER['REQUEST_URI'])) {
    $requestUri = $_SERVER['REQUEST_URI'];
    $queryStart = strpos($requestUri, '?');
    if ($queryStart !== false) {
        $queryString = substr($requestUri, $queryStart + 1);
        parse_str($queryString, $queryParams);
        if (isset($queryParams['action']) && is_string($queryParams['action']) && $queryParams['action'] !== '') {
            $action = trim(strtolower((string)$queryParams['action']));
        }
    }
}

// Fall back to $_GET or $_POST if still default
if ($action === 'save') {
    if (isset($_GET['action']) && is_string($_GET['action']) && $_GET['action'] !== '') {
        $action = trim(strtolower((string)$_GET['action']));
    } elseif (isset($_POST['action']) && is_string($_POST['action']) && $_POST['action'] !== '') {
        $action = trim(strtolower((string)$_POST['action']));
    }
}

// Normalize: 'create' and 'update' both map to 'save'
if ($action === 'create' || $action === 'update' || $action === '') {
    $action = 'save';
}

// FORCE to 'save' to bypass any parsing issues temporarily
$action = 'save';

// Validate action is in allowed list (should always pass since we normalized to 'save')
$validActions = ['save', 'delete', 'list', 'versions', 'restore_version', 'review'];
if (!in_array($action, $validActions, true)) {
    // This should never happen, but just in case...
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    error_reporting($oldErrorReporting);
    ini_set('display_errors', $oldDisplayErrors);
    http_response_code(400);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid action - failed validation',
        'debug' => [
            'action' => $action,
            'action_type' => gettype($action),
            'valid_actions' => $validActions
        ]
    ]);
    exit;
}

// DEBUG: Output action value right before switch
// This will show us if switch is reached and what action value is
if ($action !== 'save' && strlen($action) !== 4) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    error_reporting($oldErrorReporting);
    ini_set('display_errors', $oldDisplayErrors);
    http_response_code(400);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode([
        'status' => 'error',
        'message' => 'Action check before switch failed',
        'debug' => [
            'action' => $action,
            'action_type' => gettype($action),
            'action_length' => strlen($action),
            'action_hex' => bin2hex($action),
            'action_equals_save' => ($action === 'save'),
            'action_length_check' => (strlen($action) === 4)
        ]
    ]);
    exit;
}

switch ($action) {
    case 'save':
        // Action is 'save', proceed with saving
        try {
            $result = saveEntry();
        } catch (Throwable $e) {
            // Catch any errors in saveEntry
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            error_reporting($oldErrorReporting);
            ini_set('display_errors', $oldDisplayErrors);
            http_response_code(400);
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode([
                'status' => 'error',
                'message' => 'Error in saveEntry: ' . $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ]);
            exit;
        }
        // Clean output buffer completely - remove any warnings or errors
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        // Restore error reporting for clean output
        error_reporting($oldErrorReporting);
        ini_set('display_errors', $oldDisplayErrors);
        
        // Set appropriate HTTP status code only if error
        if (isset($result['status']) && $result['status'] === 'error') {
            http_response_code(400);
        } else if (isset($result['error'])) {
            http_response_code(400);
            $result = ['status' => 'error', 'message' => $result['error']];
        } else {
            // Success - ensure 200 status
            http_response_code(200);
        }
        header('Content-Type: application/json');
        echo json_encode($result);
        ob_end_flush();
        exit;
        break;
    case 'delete':
        $result = deleteEntry();
        while (ob_get_level() > 0) { ob_end_clean(); }
        ob_start();
        error_reporting($oldErrorReporting);
        ini_set('display_errors', $oldDisplayErrors);
        header('Content-Type: application/json');
        echo json_encode($result);
        ob_end_flush();
        exit;
        break;
    case 'list':
        $result = listEntries();
        while (ob_get_level() > 0) { ob_end_clean(); }
        ob_start();
        error_reporting($oldErrorReporting);
        ini_set('display_errors', $oldDisplayErrors);
        header('Content-Type: application/json');
        echo json_encode($result);
        ob_end_flush();
        exit;
        break;
    case 'versions':
        $result = getVersionHistory();
        while (ob_get_level() > 0) { ob_end_clean(); }
        ob_start();
        error_reporting($oldErrorReporting);
        ini_set('display_errors', $oldDisplayErrors);
        header('Content-Type: application/json');
        echo json_encode($result);
        ob_end_flush();
        exit;
        break;
    case 'restore_version':
        $result = restoreVersion();
        while (ob_get_level() > 0) { ob_end_clean(); }
        ob_start();
        error_reporting($oldErrorReporting);
        ini_set('display_errors', $oldDisplayErrors);
        header('Content-Type: application/json');
        echo json_encode($result);
        ob_end_flush();
        exit;
        break;
    case 'review':
        $result = processReviewAction();
        while (ob_get_level() > 0) { ob_end_clean(); }
        ob_start();
        error_reporting($oldErrorReporting);
        ini_set('display_errors', $oldDisplayErrors);
        header('Content-Type: application/json');
        echo json_encode($result);
        ob_end_flush();
        exit;
        break;
    default:
        // This should never be reached because we validate before the switch
        // But just in case, output debug info
        while (ob_get_level() > 0) { ob_end_clean(); }
        error_reporting($oldErrorReporting);
        ini_set('display_errors', $oldDisplayErrors);
        http_response_code(400);
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid action (reached default case)',
            'debug' => [
                'switch_action' => $action,
                'switch_action_type' => gettype($action),
                'valid_actions' => $validActions ?? []
            ]
        ]);
        exit;
}

function saveEntry() {
    $collectionSlug = $_POST['collection'] ?? '';
    $entryId = $_POST['entry_id'] ?? '';
    $action = $_POST['action'] ?? 'create';
    
    // Validate collection slug immediately
    if (empty($collectionSlug)) {
        return ['status' => 'error', 'message' => 'Collection slug is required'];
    }
    
    // Handle both direct POST fields and nested entry array
    if (isset($_POST['entry']) && is_array($_POST['entry'])) {
        $entryData = $_POST['entry'];
    } else {
        // Collect all POST fields except system fields
        $entryData = $_POST;
        unset($entryData['collection'], $entryData['entry_id'], $entryData['action'], $entryData['_csrf']);
    }
    
    if (empty($collectionSlug)) {
        return ['status' => 'error', 'message' => 'Collection slug is required'];
    }
    
    // Validate collection exists and get schema
    $collectionDir = __DIR__ . "/../content/collections/$collectionSlug";
    if (!is_dir($collectionDir)) {
        return ['status' => 'error', 'message' => 'Collection not found'];
    }
    
    // Load schema to process fieldtypes
    $schemaFile = "$collectionDir/schema.json";
    $schema = [];
    if (file_exists($schemaFile)) {
        $schema = json_decode(file_get_contents($schemaFile), true);
    }
    
    // Create entries directory if it doesn't exist
    $entriesDir = "$collectionDir/entries";
    if (!is_dir($entriesDir)) {
        mkdir($entriesDir, 0755, true);
    }
    
    // Load existing entry if updating
    $entry = null;
    if ($action === 'update' && !empty($entryId)) {
        $entryFile = "$entriesDir/$entryId.json";
        if (file_exists($entryFile)) {
            $entry = json_decode(file_get_contents($entryFile), true);
        }
    }
    
    // Generate entry ID if not provided (for new entries)
    if (empty($entryId)) {
        $entryId = uniqid();
    }
    
    // Validate title (mandatory for all entries)
    $title = trim($entryData['title'] ?? '');
    if (empty($title)) {
        return ['status' => 'error', 'message' => 'Title is required'];
    }
    
    // Handle versioning for existing entries
    $currentVersion = 1;
    if ($entry && isset($entry['version'])) {
        $currentVersion = intval($entry['version']) + 1;
    }
    
    // Save current version to history if updating
    if ($action === 'update' && $entry) {
        saveVersionHistory($collectionSlug, $entryId, $entry);
    }
    
    // Prepare entry data
    $entryOutput = [
        'id' => $entryId,
        'title' => $title,
        'slug' => $entryData['slug'] ?? '',
        'status' => $entryData['status'] ?? ($entry['status'] ?? 'draft'),
        'version' => $currentVersion,
        'created_at' => $entry['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Handle reviewer assignment if provided
    if (isset($entryData['reviewer']) && !empty($entryData['reviewer'])) {
        $entryOutput['reviewer'] = $entryData['reviewer'];
    }
    
    // Handle review notes if provided
    if (isset($entryData['review_notes']) && !empty($entryData['review_notes'])) {
        $entryOutput['review_notes'] = $entryData['review_notes'];
    }
    
    // Handle scheduled publishing
    if (isset($entryData['published_at']) && !empty($entryData['published_at'])) {
        $entryOutput['published_at'] = $entryData['published_at'];
        // Auto-set status to scheduled if future date
        $publishTime = strtotime($entryData['published_at']);
        if ($publishTime > time()) {
            $entryOutput['status'] = 'scheduled';
        } else {
            $entryOutput['status'] = 'published';
        }
    }
    
    if (isset($entryData['expires_at']) && !empty($entryData['expires_at'])) {
        $entryOutput['expires_at'] = $entryData['expires_at'];
    }
    
    // Process fieldtypes to save values properly
    // Set flag to prevent admin_fieldtypes.php from executing its switch statement
    $GLOBALS['__HARU_INTERNAL_CALL'] = true;
    require_once __DIR__ . '/admin_fieldtypes.php';
    $fields = $schema['fields'] ?? [];
    
    foreach ($fields as $field) {
        $fieldName = $field['name'] ?? '';
        $fieldType = $field['type'] ?? '';
        if (empty($fieldName)) continue;
        
        $rawValue = $entryData[$fieldName] ?? null;
        $value = $rawValue;
        
        // Process value through fieldtype if it exists
        if ($rawValue !== null && !empty($fieldType)) {
            $className = getFieldtypeClass($fieldType);
            if ($className && class_exists($className)) {
                try {
                    // Suppress warnings temporarily when instantiating
                    $tempErrorReporting = error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
                    $ft = new $className([
                        'name' => $fieldName,
                        'label' => $field['label'] ?? $fieldName,
                        'value' => $rawValue,
                        'required' => $field['required'] ?? false,
                        'options' => $field['options'] ?? []
                    ]);
                    error_reporting($oldErrorReporting);
                    
                    // Process the value
                    if (method_exists($ft, 'processValue')) {
                        $value = $ft->processValue($rawValue);
                    }
                } catch (Exception $e) {
                    error_log("Error processing fieldtype {$fieldType} for field {$fieldName}: " . $e->getMessage());
                    // Continue with raw value
                }
            }
        }
        
        $entryOutput[$fieldName] = $value;
    }
    
    // Also add any other fields that might have been submitted but aren't in schema
    foreach ($entryData as $key => $value) {
        if (!in_array($key, ['id', 'title', 'slug', 'status', 'created_at', 'updated_at', 'meta_title', 'meta_description']) 
            && !isset($entryOutput[$key])) {
            // Special handling for JSON strings
            if (is_string($value) && (str_starts_with($value, '[') || str_starts_with($value, '{'))) {
                $decoded = json_decode($value, true);
                if ($decoded !== null) {
                    $entryOutput[$key] = $decoded;
                } else {
                    $entryOutput[$key] = $value;
                }
            } else {
                $entryOutput[$key] = $value;
            }
        }
    }
    
    // Add meta fields
    if (isset($entryData['meta_title'])) {
        $entryOutput['meta_title'] = $entryData['meta_title'];
    }
    if (isset($entryData['meta_description'])) {
        $entryOutput['meta_description'] = $entryData['meta_description'];
    }
    
    // Auto-generate slug from title if empty
    if (empty($entryOutput['slug'])) {
        $entryOutput['slug'] = generateSlug($entryOutput['title']);
    }
    
    // Save entry to file
    $entryFile = "$entriesDir/$entryId.json";
    $jsonOutput = json_encode($entryOutput, JSON_PRETTY_PRINT);
    if ($jsonOutput === false) {
        error_log("JSON encode error: " . json_last_error_msg());
        return ['status' => 'error', 'message' => 'Failed to encode entry data: ' . json_last_error_msg()];
    }
    if (file_put_contents($entryFile, $jsonOutput)) {
        // Trigger webhooks
        if (file_exists(__DIR__ . '/webhooks.php')) {
            // Set flag to prevent webhooks.php from executing its switch statement
            $GLOBALS['__HARU_INTERNAL_CALL'] = true;
            require_once __DIR__ . '/webhooks.php';
            $webhookEvent = $action === 'create' ? 'entry.created' : 'entry.updated';
            
            // Also trigger publish/unpublish events if status changed
            if (isset($entryData['status'])) {
                $newStatus = $entryData['status'];
                $oldStatus = $entry['status'] ?? '';
                
                if ($newStatus === 'published' && $oldStatus !== 'published') {
                    triggerWebhook('entry.published', [
                        'collection' => $collectionSlug,
                        'entry_id' => $entryId,
                        'entry' => $entryOutput
                    ]);
                } else if ($newStatus !== 'published' && $oldStatus === 'published') {
                    triggerWebhook('entry.unpublished', [
                        'collection' => $collectionSlug,
                        'entry_id' => $entryId,
                        'entry' => $entryOutput
                    ]);
                }
            }
            
            triggerWebhook($webhookEvent, [
                'collection' => $collectionSlug,
                'entry_id' => $entryId,
                'entry' => $entryOutput,
                'action' => $action
            ]);
        }
        
        return [
            'status' => 'ok',
            'data' => [
                'id' => $entryId,
                'message' => 'Entry saved successfully'
            ]
        ];
    } else {
        $error = error_get_last();
        error_log("Failed to save entry file: " . ($error['message'] ?? 'Unknown error'));
        return ['status' => 'error', 'message' => 'Failed to save entry file: ' . ($error['message'] ?? 'Unknown error')];
    }
}

function deleteEntry() {
    $collectionSlug = $_POST['collection'] ?? '';
    $entryId = $_POST['entry_id'] ?? '';
    
    if (empty($collectionSlug) || empty($entryId)) {
        return ['error' => 'Collection slug and entry ID are required'];
    }
    
    $entryFile = __DIR__ . "/../content/collections/$collectionSlug/entries/$entryId.json";
    
    // Load entry data before deletion for webhook
    $entryData = null;
    if (file_exists($entryFile)) {
        $entryData = json_decode(file_get_contents($entryFile), true);
    }
    
    if (file_exists($entryFile) && unlink($entryFile)) {
        // Trigger webhook
        if (file_exists(__DIR__ . '/webhooks.php')) {
            require_once __DIR__ . '/webhooks.php';
            triggerWebhook('entry.deleted', [
                'collection' => $collectionSlug,
                'entry_id' => $entryId,
                'entry' => $entryData
            ]);
        }
        
        return [
            'status' => 'ok',
            'message' => 'Entry deleted successfully'
        ];
    } else {
        return ['error' => 'Failed to delete entry or entry not found'];
    }
}

function generateSlug($title) {
    // Convert to lowercase
    $slug = strtolower($title);
    
    // Replace spaces with hyphens
    $slug = preg_replace('/\s+/', '-', $slug);
    
    // Remove special characters except hyphens
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    
    // Remove multiple consecutive hyphens
    $slug = preg_replace('/-+/', '-', $slug);
    
    // Remove leading and trailing hyphens
    $slug = trim($slug, '-');
    
    return $slug;
}

function listEntries() {
    $collectionSlug = $_GET['collection'] ?? '';
    $search = $_GET['search'] ?? '';
    
    error_log("=== LIST ENTRIES DEBUG ===");
    error_log("Collection slug: " . $collectionSlug);
    error_log("Search term: " . $search);
    
    if (empty($collectionSlug)) {
        return ['error' => 'Collection slug is required'];
    }
    
    // Find collection directory by slug
    $collectionsDir = __DIR__ . '/../content/collections';
    if (!is_dir($collectionsDir)) {
        return ['error' => 'Collections directory not found'];
    }
    
    $collectionDir = $collectionsDir . '/' . $collectionSlug;
    
    if (!is_dir($collectionDir)) {
        return ['error' => 'Collection not found'];
    }
    
    $entriesDir = $collectionDir . '/entries';
    if (!is_dir($entriesDir)) {
        return ['entries' => []];
    }
    
    $entries = [];
    $entryFiles = scandir($entriesDir);
    
    foreach ($entryFiles as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
            $entryPath = $entriesDir . '/' . $file;
            $entryData = json_decode(file_get_contents($entryPath), true);
            
            if ($entryData) {
                $entryId = pathinfo($file, PATHINFO_FILENAME);
                $title = $entryData['title'] ?? $entryId;
                
                // Apply search filter if provided
                if (!empty($search) && stripos($title, $search) === false) {
                    continue;
                }
                
                $entries[] = [
                    'id' => $entryId,
                    'title' => $title
                ];
            }
        }
    }
    
    error_log("Found " . count($entries) . " entries");
    
    return ['entries' => $entries];
}

/**
 * Save version history
 */
function saveVersionHistory($collectionSlug, $entryId, $entryData) {
    $versionsDir = __DIR__ . "/../content/collections/$collectionSlug/entries/$entryId/versions";
    if (!is_dir($versionsDir)) {
        mkdir($versionsDir, 0755, true);
    }
    
    $version = $entryData['version'] ?? 1;
    $versionFile = "$versionsDir/v{$version}.json";
    
    // Get current user for author info
    session_start();
    $author = $_SESSION['user']['email'] ?? $_SESSION['user']['name'] ?? 'Unknown';
    
    $versionData = array_merge($entryData, [
        'author' => $author,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    file_put_contents($versionFile, json_encode($versionData, JSON_PRETTY_PRINT));
}

/**
 * Get version history
 */
function getVersionHistory() {
    $collectionSlug = $_GET['collection'] ?? '';
    $entryId = $_GET['entry_id'] ?? '';
    
    if (empty($collectionSlug) || empty($entryId)) {
        return ['status' => 'error', 'message' => 'Collection slug and entry ID are required'];
    }
    
    $versionsDir = __DIR__ . "/../content/collections/$collectionSlug/entries/$entryId/versions";
    
    // Get current entry as latest version
    $entryFile = __DIR__ . "/../content/collections/$collectionSlug/entries/$entryId.json";
    $versions = [];
    
    if (file_exists($entryFile)) {
        $currentEntry = json_decode(file_get_contents($entryFile), true);
        if ($currentEntry) {
            $currentEntry['version'] = $currentEntry['version'] ?? 1;
            $currentEntry['author'] = 'Current';
            $currentEntry['created_at'] = $currentEntry['updated_at'] ?? $currentEntry['created_at'] ?? date('Y-m-d H:i:s');
            $versions[] = $currentEntry;
        }
    }
    
    // Get historical versions
    if (is_dir($versionsDir)) {
        $versionFiles = glob("$versionsDir/v*.json");
        usort($versionFiles, function($a, $b) {
            return filemtime($b) - filemtime($a); // Newest first
        });
        
        foreach ($versionFiles as $versionFile) {
            $versionData = json_decode(file_get_contents($versionFile), true);
            if ($versionData) {
                // Extract version number from filename
                preg_match('/v(\d+)\.json$/', basename($versionFile), $matches);
                if ($matches) {
                    $versionData['version'] = intval($matches[1]);
                }
                $versions[] = $versionData;
            }
        }
    }
    
    // Sort by version number descending
    usort($versions, function($a, $b) {
        return ($b['version'] ?? 0) - ($a['version'] ?? 0);
    });
    
    return ['status' => 'ok', 'versions' => $versions];
}

/**
 * Restore version
 */
function restoreVersion() {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    $collectionSlug = $input['collection'] ?? '';
    $entryId = $input['entry_id'] ?? '';
    $versionToRestore = $input['version'] ?? '';
    
    if (empty($collectionSlug) || empty($entryId) || empty($versionToRestore)) {
        return ['status' => 'error', 'message' => 'Missing required parameters'];
    }
    
    $entryFile = __DIR__ . "/../content/collections/$collectionSlug/entries/$entryId.json";
    $versionFile = __DIR__ . "/../content/collections/$collectionSlug/entries/$entryId/versions/v{$versionToRestore}.json";
    
    // Load current entry to save as new version
    if (file_exists($entryFile)) {
        $currentEntry = json_decode(file_get_contents($entryFile), true);
        if ($currentEntry) {
            saveVersionHistory($collectionSlug, $entryId, $currentEntry);
        }
    }
    
    // Load version to restore
    if (!file_exists($versionFile)) {
        return ['status' => 'error', 'message' => 'Version not found'];
    }
    
    $versionData = json_decode(file_get_contents($versionFile), true);
    if (!$versionData) {
        return ['status' => 'error', 'message' => 'Failed to load version data'];
    }
    
    // Update version number and timestamps
    $versionData['version'] = ($versionData['version'] ?? 1) + 1;
    $versionData['updated_at'] = date('Y-m-d H:i:s');
    unset($versionData['author']); // Remove author from main entry
    
    // Restore entry
    if (file_put_contents($entryFile, json_encode($versionData, JSON_PRETTY_PRINT))) {
        return ['status' => 'ok', 'message' => 'Version restored successfully'];
    } else {
        return ['status' => 'error', 'message' => 'Failed to restore version'];
    }
}

/**
 * Process review action (approve/reject/request changes)
 */
function processReviewAction() {
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/log.php';
    
    // Check authentication
    if (!isset($_SESSION['user'])) {
        return ['status' => 'error', 'message' => 'Authentication required'];
    }
    
    // Check permissions (admin or editor can review)
    $userRole = $_SESSION['user']['role'] ?? 'viewer';
    if (!in_array($userRole, ['admin', 'editor'])) {
        return ['status' => 'error', 'message' => 'Insufficient permissions'];
    }
    
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    $collectionSlug = $input['collection'] ?? '';
    $entryId = $input['entry_id'] ?? '';
    $action = $input['action'] ?? ''; // 'approved', 'rejected', or 'draft' (for request changes)
    $reviewer = $input['reviewer'] ?? '';
    $notes = $input['notes'] ?? '';
    
    if (empty($collectionSlug) || empty($entryId) || empty($action)) {
        return ['status' => 'error', 'message' => 'Missing required parameters'];
    }
    
    $entryFile = __DIR__ . "/../content/collections/$collectionSlug/entries/$entryId.json";
    
    if (!file_exists($entryFile)) {
        return ['status' => 'error', 'message' => 'Entry not found'];
    }
    
    $entry = json_decode(file_get_contents($entryFile), true);
    if (!$entry) {
        return ['status' => 'error', 'message' => 'Failed to load entry'];
    }
    
    // Get current user info
    $currentUser = $_SESSION['user'];
    $reviewedBy = $currentUser['email'] ?? $currentUser['name'] ?? 'Unknown';
    
    // Update entry with review information
    $entry['status'] = $action;
    $entry['reviewed_by'] = $reviewedBy;
    $entry['reviewed_at'] = date('Y-m-d H:i:s');
    $entry['updated_at'] = date('Y-m-d H:i:s');
    
    if ($reviewer) {
        $entry['reviewer'] = $reviewer;
    }
    
    if ($notes) {
        $entry['review_notes'] = $notes;
    }
    
    // If approved, optionally auto-publish (you can configure this behavior)
    if ($action === 'approved' && isset($entry['auto_publish_on_approval']) && $entry['auto_publish_on_approval']) {
        $entry['status'] = 'published';
    }
    
    // Save entry
    if (file_put_contents($entryFile, json_encode($entry, JSON_PRETTY_PRINT))) {
        // Log review action
        log_security_event('review_action', [
            'collection' => $collectionSlug,
            'entry_id' => $entryId,
            'action' => $action,
            'reviewer' => $reviewedBy,
            'notes' => $notes
        ]);
        
        return ['status' => 'ok', 'message' => 'Review action processed successfully'];
    } else {
        return ['status' => 'error', 'message' => 'Failed to save entry'];
    }
}
?> 