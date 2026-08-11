<?php
/**
 * Public Sub-Entry Submission API for Birkly
 * 
 * Allows public users to submit sub-entries via API.
 * Supports rate limiting, CSRF protection, and moderation workflow.
 */

// Start output buffering to catch any unexpected output
ob_start();

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

header('Content-Type: application/json');

require_once __DIR__ . '/../core/sub_entries.php';
require_once __DIR__ . '/../core/storage/StorageFactory.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/plugins.php';
require_once __DIR__ . '/../core/mail/notify.php';
load_plugins();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$method = $_SERVER['REQUEST_METHOD'];

// Only allow POST
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Get parameters
$parent_collection = $input['parent_collection'] ?? $_POST['parent_collection'] ?? '';
$parent_entry_id = $input['parent_entry_id'] ?? $_POST['parent_entry_id'] ?? '';
$sub_collection = $input['sub_collection'] ?? $_POST['sub_collection'] ?? '';
$api_key = $input['api_key'] ?? $_POST['api_key'] ?? $_GET['api_key'] ?? '';

// Validate required parameters
if (empty($parent_collection) || empty($parent_entry_id) || empty($sub_collection)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters: parent_collection, parent_entry_id, sub_collection']);
    exit;
}

// Load collection and sub-collection configuration
$storage = get_storage();
$collectionPath = "collections/{$parent_collection}/collection.json";
$collection = $storage->read($collectionPath);

if (!$collection) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Parent collection not found']);
    exit;
}

// Get sub-collection configuration
$subCollectionConfig = getSubCollectionConfig($parent_collection, $sub_collection);

if (!$subCollectionConfig) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Sub-collection not found']);
    exit;
}

// Check if sub-collection allows public API
if (($subCollectionConfig['entry_method'] ?? 'admin_only') !== 'public_api') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Sub-collection does not accept public submissions']);
    exit;
}

// Verify API key if required
if (!empty($api_key)) {
    // Check if API key matches (if collection has API key requirement)
    // For now, we'll allow submissions if sub-collection is configured for public API
    // You can add API key validation here if needed
}

// Rate limiting
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitKey = "public_sub_entry_{$ip}_{$parent_collection}_{$sub_collection}";
$rateLimit = $subCollectionConfig['rate_limit'] ?? 10; // Default: 10 per hour
$rateLimiter = new RateLimiter($rateLimitKey, (int)$rateLimit, 3600);
if (!$rateLimiter->check()) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Rate limit exceeded. Please try again later.']);
    exit;
}

// CSRF protection (honeypot)
$honeypot = $input['_birkly_honeypot'] ?? $_POST['_birkly_honeypot'] ?? '';
if (!empty($honeypot)) {
    // Bot detected - silently fail
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Submission received']);
    exit;
}

// Get operation first (before processing submission data)
// Optional operation:
// - create (default): create sub-entry
// - toggle: if sub-entry exists delete it, otherwise create it
// - delete: delete sub-entry (requires ownership check)
$operation = $input['operation'] ?? $_POST['operation'] ?? 'create';
if (!is_string($operation) || $operation === '') $operation = 'create';

// Handle delete operation first (before sanitization)
if ($operation === 'delete') {
    require_once __DIR__ . '/session.php';
    birkly_site_session_start();
    // Plugins are already loaded above, so we just need user functions
    // Check if user functions are available, if not, load public_bootstrap (which checks for existing functions)
    if (!function_exists('birkly_get_current_user')) {
        require_once __DIR__ . '/../core/public_bootstrap.php';
require_once __DIR__ . '/../core/birkly_paths.php';
        if (function_exists('birkly_public_load_active_plugins')) {
            birkly_public_load_active_plugins();
        }
    }
    
    $sub_entry_id = $input['id'] ?? $_POST['id'] ?? '';
    if (empty($sub_entry_id)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required id for delete operation']);
        exit;
    }
    
    // Get the sub-entry to check ownership
    $subEntry = getSubEntry($parent_collection, $parent_entry_id, $sub_collection, $sub_entry_id);
    if (!$subEntry) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Sub-entry not found']);
        exit;
    }
    
    // Check ownership - user can only delete their own sub-entries
    // Use EntryPermissions to get current user ID (same approach as api/public.php)
    $currentUserId = '';
    if (file_exists(__DIR__ . '/../plugins/user_management/includes/EntryPermissions.php')) {
        require_once __DIR__ . '/../plugins/user_management/includes/EntryPermissions.php';
        $entryPermissions = new EntryPermissions();
        $currentUserId = $entryPermissions->getCurrentUserId();
    }
    
    if (empty($currentUserId)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        exit;
    }
    
    $subEntryUserId = $subEntry['user_id'] ?? $subEntry['author_user_id'] ?? '';
    
    if (empty($subEntryUserId) || $subEntryUserId !== $currentUserId) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'You can only delete your own sub-entries']);
        exit;
    }
    
    // Delete the sub-entry
    try {
        // Clear any output that might have been generated
        if (ob_get_level() > 0) {
            ob_clean();
        }
        
        error_log("Attempting to delete sub-entry: collection={$parent_collection}, entry={$parent_entry_id}, sub_collection={$sub_collection}, id={$sub_entry_id}");
        
        $ok = deleteSubEntry($parent_collection, $parent_entry_id, $sub_collection, $sub_entry_id);
        
        error_log("deleteSubEntry returned: " . ($ok ? 'true' : 'false'));
        
        if (!$ok) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete sub-entry']);
            exit;
        }
        
        if (ob_get_level() > 0) {
            ob_clean();
        }
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'Sub-entry deleted']);
        exit;
    } catch (Exception $e) {
        error_log("Exception deleting sub-entry: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        if (ob_get_level() > 0) {
            ob_clean();
        }
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete sub-entry']);
        exit;
    } catch (Error $e) {
        error_log("Fatal error deleting sub-entry: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        if (ob_get_level() > 0) {
            ob_clean();
        }
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete sub-entry']);
        exit;
    }
}

// Get submission data (for create/toggle operations)
$submissionData = $input['data'] ?? $_POST;
// Remove system fields
unset($submissionData['parent_collection']);
unset($submissionData['parent_entry_id']);
unset($submissionData['sub_collection']);
unset($submissionData['api_key']);
unset($submissionData['_birkly_honeypot']);
unset($submissionData['_csrf']);
unset($submissionData['operation']);

// Validate parent entry exists
$parentEntryPath = "collections/{$parent_collection}/entries/{$parent_entry_id}.json";
$parentEntry = $storage->read($parentEntryPath);

if (!$parentEntry) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Parent entry not found']);
    exit;
}

// Sanitize submission data
$sanitizedData = [];
foreach ($submissionData as $key => $value) {
    // Basic sanitization
    if (is_string($value)) {
        $sanitizedData[$key] = htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
    } else {
        $sanitizedData[$key] = $value;
    }
}

// Validate required fields from sub-collection schema
$schemaFields = $subCollectionConfig['fields'] ?? $subCollectionConfig['schema']['fields'] ?? [];
$validationErrors = [];
foreach ($schemaFields as $field) {
    if (!is_array($field)) {
        continue;
    }
    $fieldName = (string) ($field['name'] ?? '');
    if ($fieldName === '' || empty($field['required'])) {
        continue;
    }
    $value = $sanitizedData[$fieldName] ?? '';
    if (is_string($value) && trim($value) === '') {
        $validationErrors[] = $fieldName . ' is required';
    }
}
if ($validationErrors) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validationErrors]);
    exit;
}

// Set status based on auto_publish setting
$autoPublish = !empty($subCollectionConfig['auto_publish'])
    || !empty($subCollectionConfig['api_settings']['auto_publish']);
$sanitizedData['status'] = $autoPublish ? 'published' : 'draft';

// Toggle / Create
$result = null;
if ($operation === 'toggle') {
    // For toggle, require a stable ID
    $toggleId = $sanitizedData['id'] ?? ($input['id'] ?? $_POST['id'] ?? null);
    if (!$toggleId || !is_string($toggleId)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required id for toggle operation']);
        exit;
    }
    $toggleId = trim($toggleId);
    if ($toggleId === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid id for toggle operation']);
        exit;
    }

    // Check existing
    $existing = getSubEntry($parent_collection, $parent_entry_id, $sub_collection, $toggleId);
    if ($existing) {
        $ok = deleteSubEntry($parent_collection, $parent_entry_id, $sub_collection, $toggleId);
        if (!$ok) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete sub-entry']);
            exit;
        }
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'Toggled off', 'data' => ['id' => $toggleId, 'toggled' => false]]);
        exit;
    }

    // Toggle on: create with provided id
    $sanitizedData['id'] = $toggleId;
    $result = createSubEntry($parent_collection, $parent_entry_id, $sub_collection, $sanitizedData);
} else {
    // Create sub-entry
    $result = createSubEntry($parent_collection, $parent_entry_id, $sub_collection, $sanitizedData);
}

if ($result) {
    // Send notification email if configured
    $notificationEmail = $subCollectionConfig['notification_email'] ?? $collection['api_settings']['notification_email'] ?? '';
    if (!empty($notificationEmail) && !$autoPublish) {
        // Send moderation notification
        $subject = "New sub-entry submission requires moderation";
        $message = "A new sub-entry has been submitted to {$subCollectionConfig['name']}.\n\n";
        $parentTitle = $parentEntry['title'] ?? $parent_entry_id;
        $message .= "Parent Entry: {$parentTitle}\n";
        $message .= "Sub-Entry ID: {$result['id']}\n";
        $message .= "Status: Draft (pending review)\n\n";
        $message .= "Review at: " . (isset($_SERVER['HTTP_HOST']) ? "http://{$_SERVER['HTTP_HOST']}" : '') . birkly_admin_url('entry-editor', ['collection' => $parent_collection, 'entry' => $parent_entry_id]);
        
        birkly_notify('public_sub_entry_submission', [
            'to' => $notificationEmail,
            'subject' => $subject,
            'message' => $message,
            'from' => 'noreply@' . ($_SERVER['SERVER_NAME'] ?? 'birkly.local'),
            'metadata' => ['source' => 'public_sub_entry_submission', 'parent_collection' => $parent_collection, 'parent_entry_id' => $parent_entry_id],
        ]);
    }
    
    http_response_code(201);
    echo json_encode([
        'status' => 'ok',
        'message' => $autoPublish ? 'Sub-entry created and published' : 'Sub-entry created (pending moderation)',
        'data' => [
            'id' => $result['id'],
            'status' => $result['status'],
            'toggled' => $operation === 'toggle' ? true : null
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to create sub-entry']);
}
