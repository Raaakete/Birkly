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
require_once __DIR__ . '/../core/cache.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/entry_slug.php';
require_once __DIR__ . '/../core/entry_validation.php';
require_once __DIR__ . '/../core/entry_multilang.php';
require_once __DIR__ . '/../core/entry_storage.php';
require_once __DIR__ . '/validate.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/../core/plugins.php';
if (function_exists('load_plugins')) {
    load_plugins();
}
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
    
    // SECURITY FIX: Replace wildcard CORS with origin whitelist
    require_once __DIR__ . '/../core/cors.php';
    set_cors_headers('admin');
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

// Debug: Log the parsed action
error_log("admin_entries.php: Parsed action = '$action' (type: " . gettype($action) . ")");
error_log("admin_entries.php: REQUEST_URI = " . ($_SERVER['REQUEST_URI'] ?? 'not set'));
error_log("admin_entries.php: _GET['action'] = " . ($_GET['action'] ?? 'not set'));
error_log("admin_entries.php: _POST['action'] = " . ($_POST['action'] ?? 'not set'));

// Normalize: 'create' and 'update' both map to 'save'
// Only normalize if action is empty, create, or update - don't override valid actions like 'bulk'
if ($action === 'create' || $action === 'update') {
    $action = 'save';
} elseif ($action === '' || $action === null) {
    $action = 'save';
}

// Validate action is in allowed list
$validActions = ['save', 'delete', 'restore', 'duplicate', 'list', 'versions', 'list_versions', 'restore_version', 'review', 'bulk', 'bulk_field_edit', 'diff'];
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
            'valid_actions' => $validActions,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'not set',
            'get_action' => $_GET['action'] ?? 'not set',
            'post_action' => $_POST['action'] ?? 'not set',
            'action_in_array' => in_array($action, $validActions, true)
        ]
    ]);
    exit;
}

// SECURITY: enforce permissions per action
// - list/versions/diff: read-only
// - save/restore_version/bulk publish/unpublish: write
// - delete/bulk delete: delete
if (in_array($action, ['save', 'restore', 'duplicate', 'restore_version', 'bulk_field_edit'], true)) {
    requireAuth('write');
} elseif ($action === 'delete') {
    requireAuth('delete');
} elseif ($action === 'bulk') {
    // bulk permission is determined inside performBulkOperation() (depends on bulk action)
    requireAuth('write');
} elseif (in_array($action, ['list', 'versions', 'list_versions', 'diff'], true)) {
    requireAuth('read');
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
        try {
            $result = deleteEntry();
        } catch (Exception $e) {
            error_log("Error in deleteEntry: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            $result = ['status' => 'error', 'message' => 'Failed to delete entry: ' . $e->getMessage()];
        }
        ob_clean();
        error_reporting($oldErrorReporting);
        ini_set('display_errors', $oldDisplayErrors);
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode($result);
        ob_end_flush();
        exit;
        break;
    case 'restore':
        $result = restoreEntry();
        while (ob_get_level() > 0) { ob_end_clean(); }
        ob_start();
        error_reporting($oldErrorReporting);
        ini_set('display_errors', $oldDisplayErrors);
        header('Content-Type: application/json');
        echo json_encode($result);
        ob_end_flush();
        exit;
        break;
    case 'duplicate':
        $result = duplicateEntry();
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
    case 'list_versions':
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
    case 'bulk':
        // Clean output buffers
        while (ob_get_level() > 0) { 
            ob_end_clean(); 
        }
        
        // Call the function
        $result = performBulkOperation();
        
        // Clean output buffers again
        while (ob_get_level() > 0) { 
            ob_end_clean(); 
        }
        
        // Restore error reporting
        error_reporting($oldErrorReporting);
        ini_set('display_errors', $oldDisplayErrors);
        
        // Ensure result is an array
        if (!is_array($result)) {
            $result = ['status' => 'error', 'message' => 'Invalid response from bulk operation'];
        }
        
        // Set HTTP status
        $statusCode = (isset($result['status']) && $result['status'] === 'error') ? 400 : 200;
        http_response_code($statusCode);
        
        // Send headers - must be before any output
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        
        // Encode and send response
        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = json_encode(['status' => 'error', 'message' => 'JSON encoding failed: ' . json_last_error_msg()]);
        }
        
        // Send response
        echo $json;
        
        // Exit immediately - no code after this
        exit(0);
        break;
    case 'bulk_field_edit':
        $result = bulkFieldEdit();
        while (ob_get_level() > 0) { ob_end_clean(); }
        ob_start();
        error_reporting($oldErrorReporting);
        ini_set('display_errors', $oldDisplayErrors);
        header('Content-Type: application/json');
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ob_end_flush();
        exit;
        break;
    case 'diff':
        $result = getVersionDiff();
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
    // Editor always uses ?action=save; entry_id decides create vs update.
    $action = birkly_resolve_entry_save_action($entryId);

    // SECURITY: saving entries requires write permission
    requireAuth('write');
    
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
    
    // SECURITY FIX: Sanitize collection slug and entry ID to prevent path traversal
    require_once __DIR__ . '/validate.php';
    $collectionSlug = sanitize_identifier($collectionSlug);
    if ($collectionSlug === false) {
        return ['status' => 'error', 'message' => 'Invalid collection identifier'];
    }
    
    if (!empty($entryId)) {
        $entryId = sanitize_identifier($entryId);
        if ($entryId === false) {
            return ['status' => 'error', 'message' => 'Invalid entry identifier'];
        }
    }
    
    // Validate collection exists and get schema
    if (!birkly_entry_collection_exists($collectionSlug)) {
        return ['status' => 'error', 'message' => 'Invalid collection'];
    }

    $schema = birkly_entry_read_schema($collectionSlug);
    
    // Load existing entry if updating
    $entry = null;
    if ($action === 'update' && !empty($entryId)) {
        $entry = birkly_entry_read_record($collectionSlug, $entryId);
        if ($entry !== null) {
            error_log("DEBUG REVIEWER: Loaded existing entry, reviewer=" . ($entry['reviewer'] ?? 'none') . ", entryId=" . $entryId);
        } else {
            error_log("DEBUG REVIEWER: Entry not found in storage: collection=" . $collectionSlug . ", entryId=" . $entryId);
        }
    } else {
        error_log("DEBUG REVIEWER: Not loading entry - action=" . ($action ?? 'none') . ", entryId=" . ($entryId ?? 'none'));
    }
    
    // Generate entry ID if not provided (for new entries)
    if (empty($entryId)) {
        $entryId = uniqid();
    }
    
    // Title is resolved after language fields are processed for multilanguage entries.
    $title = trim((string) ($entryData['title'] ?? ''));
    
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
    // Map old 'draft' status to 'unpublished' for consistency
    $status = $entryData['status'] ?? ($entry['status'] ?? 'unpublished');
    if ($status === 'draft') {
        $status = 'unpublished';
    }
    
    $entryOutput = [
        'id' => $entryId,
        'title' => $title !== '' ? $title : $entryId,
        'slug' => $entryData['slug'] ?? '',
        'status' => $status,
        'version' => $currentVersion,
        'created_at' => $entry['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Handle language and translations
    if (isset($entryData['language']) && !empty($entryData['language'])) {
        $entryOutput['language'] = $entryData['language'];
    } elseif (isset($entry['language'])) {
        $entryOutput['language'] = $entry['language'];
    }
    
    // Handle translations (links to other language versions)
    if (isset($entryData['translations'])) {
        if (is_string($entryData['translations'])) {
            // Try to decode JSON string
            $decoded = json_decode($entryData['translations'], true);
            if (is_array($decoded)) {
                $entryOutput['translations'] = $decoded;
            } elseif (is_array($entryData['translations'])) {
                $entryOutput['translations'] = $entryData['translations'];
            }
        } elseif (is_array($entryData['translations'])) {
            $entryOutput['translations'] = $entryData['translations'];
        }
    } elseif (isset($entry['translations']) && is_array($entry['translations'])) {
        $entryOutput['translations'] = $entry['translations'];
    }
    
    // Handle translation_parent (if this is a translation of another entry)
    if (isset($entryData['translation_parent']) && !empty($entryData['translation_parent'])) {
        $entryOutput['translation_parent'] = $entryData['translation_parent'];
    } elseif (isset($entry['translation_parent'])) {
        $entryOutput['translation_parent'] = $entry['translation_parent'];
    }
    
    // Handle reviewer assignment
    // Check both 'reviewer' and 'reviewer_hidden' fields
    // Prefer 'reviewer' if it exists, otherwise use 'reviewer_hidden'
    $reviewerValue = null;
    $reviewerSubmitted = false;
    
    if (isset($entryData['reviewer'])) {
        $reviewerSubmitted = true;
        if ($entryData['reviewer'] !== '') {
            $reviewerValue = $entryData['reviewer'];
        }
    } elseif (isset($entryData['reviewer_hidden'])) {
        $reviewerSubmitted = true;
        if ($entryData['reviewer_hidden'] !== '') {
            $reviewerValue = $entryData['reviewer_hidden'];
        }
    }
    
    error_log("DEBUG REVIEWER: submitted=" . ($reviewerSubmitted ? 'yes' : 'no') . ", value=" . ($reviewerValue ?? 'null') . ", status=" . $status . ", existing=" . ($entry['reviewer'] ?? 'none'));
    
    if ($reviewerSubmitted) {
        // Reviewer field was submitted (either visible or hidden)
        if (!empty($reviewerValue)) {
            // Reviewer was set - normalize it to user ID (not email)
            // If it's an email, find the user ID
            $normalizedReviewer = $reviewerValue;
            if (strpos($reviewerValue, '@') !== false) {
                // It's an email, find the user ID
                $userFiles = glob(__DIR__ . '/../users/*.json');
                foreach ($userFiles as $userFile) {
                    $userData = json_decode(file_get_contents($userFile), true);
                    if ($userData && strtolower(trim($userData['email'] ?? '')) === strtolower(trim($reviewerValue))) {
                        $normalizedReviewer = $userData['id'] ?? $reviewerValue;
                        error_log("DEBUG REVIEWER: Converted email to user ID: " . $reviewerValue . " -> " . $normalizedReviewer);
                        break;
                    }
                }
            }
            $entryOutput['reviewer'] = $normalizedReviewer;
            error_log("DEBUG REVIEWER: Setting reviewer to: " . $normalizedReviewer);
        } else {
            // Reviewer field is explicitly empty
            if ($status === 'pending_review') {
                // Status is pending_review and reviewer is empty - user explicitly cleared it
                unset($entryOutput['reviewer']);
                error_log("DEBUG REVIEWER: Clearing reviewer (status is pending_review)");
            } elseif (isset($entry['reviewer']) && !empty($entry['reviewer'])) {
                // Status is not pending_review and reviewer was empty (field was hidden) - preserve existing reviewer
                $entryOutput['reviewer'] = $entry['reviewer'];
                error_log("DEBUG REVIEWER: Preserving existing reviewer: " . $entry['reviewer']);
            } else {
                // No existing reviewer to preserve - remove it
                unset($entryOutput['reviewer']);
                error_log("DEBUG REVIEWER: No reviewer to preserve");
            }
        }
    } elseif (isset($entry['reviewer']) && !empty($entry['reviewer'])) {
        // Reviewer field not in POST at all, but exists in existing entry - preserve it
        $entryOutput['reviewer'] = $entry['reviewer'];
        error_log("DEBUG REVIEWER: Preserving reviewer from existing entry: " . $entry['reviewer']);
    }
    
    // Don't save reviewer_hidden to the entry - it's only a form helper
    // Also remove it from any existing entry data that might have it
    if (isset($entryOutput['reviewer_hidden'])) {
        unset($entryOutput['reviewer_hidden']);
    }
    if (isset($entry['reviewer_hidden'])) {
        unset($entry['reviewer_hidden']);
    }
    
    // Handle review notes if provided
    if (isset($entryData['review_notes'])) {
        if (!empty($entryData['review_notes'])) {
            $entryOutput['review_notes'] = $entryData['review_notes'];
        } else {
            // Explicitly cleared - remove review notes
            unset($entryOutput['review_notes']);
        }
    } elseif (isset($entry['review_notes']) && !empty($entry['review_notes'])) {
        // Review notes not in POST, but exists in existing entry - preserve it
        $entryOutput['review_notes'] = $entry['review_notes'];
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
    
    // Check if collection has multi-language enabled
    $collectionData = birkly_entry_read_collection_meta($collectionSlug) ?? [];
    $multilanguageEnabled = !empty($collectionData['multilanguage']['enabled']);
    $multilanguageLanguages = $collectionData['multilanguage']['languages'] ?? [];
    
    // Process fieldtypes to save values properly
    // Set flag to prevent admin_fieldtypes.php from executing its switch statement
    $GLOBALS['__HARU_INTERNAL_CALL'] = true;
    require_once __DIR__ . '/admin_fieldtypes.php';
    $fields = $schema['fields'] ?? [];
    
    // Handle multi-language content structure
    if ($multilanguageEnabled && !empty($multilanguageLanguages)) {
        foreach ($multilanguageLanguages as $langCode) {
            if (!isset($entryOutput[$langCode]) || !is_array($entryOutput[$langCode])) {
                $entryOutput[$langCode] = is_array($entry[$langCode] ?? null) ? $entry[$langCode] : [];
            }

            foreach ($fields as $field) {
                $fieldName = $field['name'] ?? '';
                if ($fieldName === '') {
                    continue;
                }

                $rawValue = birkly_entry_lang_field_raw($entryData, $langCode, $fieldName);
                if ($rawValue === null) {
                    continue;
                }

                $fieldType = $field['type'] ?? '';
                $value = $rawValue;

                if ($fieldType !== '') {
                    $className = getFieldtypeClass($fieldType);
                    if ($className && class_exists($className)) {
                        try {
                            $tempErrorReporting = error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
                            $ft = new $className([
                                'name' => $fieldName,
                                'label' => $field['label'] ?? $fieldName,
                                'value' => $rawValue,
                                'required' => $field['required'] ?? false,
                                'options' => $field['options'] ?? [],
                            ]);
                            error_reporting($tempErrorReporting);

                            if (method_exists($ft, 'processValue')) {
                                $value = $ft->processValue($rawValue);
                            }
                        } catch (Exception $e) {
                            error_log("Error processing fieldtype {$fieldType} for field {$fieldName}: " . $e->getMessage());
                        }
                    }
                }

                $entryOutput[$langCode][$fieldName] = $value;
            }

            // Title is always in the editor but may be absent from collection schema.
            $rawTitle = birkly_entry_lang_field_raw($entryData, $langCode, 'title');
            if ($rawTitle !== null) {
                $entryOutput[$langCode]['title'] = trim((string) $rawTitle);
            }
        }

        $entryOutput['title'] = birkly_entry_resolve_top_level_title(
            $entryOutput,
            $entryData,
            $multilanguageLanguages,
            $entryId,
            is_array($entry) ? $entry : null
        );
    } else {
        // Single language - process fields normally
        foreach ($fields as $field) {
            $fieldName = $field['name'] ?? '';
            $fieldType = $field['type'] ?? '';
            if (empty($fieldName)) continue;
            
            // Get raw value - check both direct key and array key (for FormData)
            $rawValue = null;
            if (isset($entryData[$fieldName])) {
                $rawValue = $entryData[$fieldName];
            } elseif (array_key_exists($fieldName, $entryData)) {
                // Field exists but might be empty string
                $rawValue = $entryData[$fieldName];
            }
            
            // Default to empty string for text-like fields if not set
            if ($rawValue === null && in_array($fieldType, ['text', 'email', 'url', 'textarea', 'wysiwyg', 'date', 'datetime', 'time'])) {
                $rawValue = '';
            }
            
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
        
            // Always set the value, even if null or empty (so fields can be cleared)
        $entryOutput[$fieldName] = $value;
    }
}
    
    // Also add any other fields that might have been submitted but aren't in schema
    foreach ($entryData as $key => $value) {
        // Skip system fields and form helper fields
        // Also skip password field (used for user creation only, not stored in entry)
        if (!in_array($key, ['id', 'title', 'slug', 'status', 'created_at', 'updated_at', 'meta_title', 'meta_description', 'meta_keywords', 'og_title', 'og_description', 'og_image', 'canonical_url', 'robots_noindex', 'ai_summary', 'structured_data_json', 'reviewer_hidden', 'password']) 
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
    $metaKeys = [
        'meta_title', 'meta_description', 'meta_keywords',
        'og_title', 'og_description', 'og_image',
        'canonical_url', 'ai_summary', 'structured_data_json',
    ];
    foreach ($metaKeys as $metaKey) {
        if (isset($entryData[$metaKey])) {
            $entryOutput[$metaKey] = $entryData[$metaKey];
        }
    }
    if (isset($entryData['robots_noindex'])) {
        $entryOutput['robots_noindex'] = !empty($entryData['robots_noindex']);
    } elseif (array_key_exists('robots_noindex', $entryData)) {
        $entryOutput['robots_noindex'] = false;
    }
    
    // Auto-generate slug from title only when missing (preserve slug on title-only updates)
    $entryOutput['slug'] = birkly_resolve_entry_slug(
        $action,
        $entryData,
        $entry ?: null,
        $entryOutput['title'],
        $entryId
    );

    if ($action === 'create') {
        try {
            if (function_exists('apply_filters_strict')) {
                $entryOutput = apply_filters_strict('before_entry_create', $entryOutput, $collectionSlug);
            }
            if (function_exists('do_action_strict')) {
                do_action_strict('before_entry_create', $collectionSlug, $entryOutput);
            }
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    // Allow plugins to modify entry before save (e.g., auto-create users from profile entries)
    if (function_exists('apply_filters')) {
        $entryOutput = apply_filters('before_entry_save', $entryOutput, $collectionSlug);
    }

    $fieldErrors = birkly_validate_entry_required_fields($fields, $entryOutput, $entryData, $multilanguageEnabled, $multilanguageLanguages);
    if (!empty($fieldErrors)) {
        return [
            'status' => 'error',
            'message' => 'Please fix the highlighted fields.',
            'field_errors' => $fieldErrors,
        ];
    }

    foreach (['author_id', 'author_user_id', 'created_by', 'submitted_by', 'submitted_at'] as $metaKey) {
        if (!isset($entryOutput[$metaKey]) && is_array($entry) && isset($entry[$metaKey])) {
            $entryOutput[$metaKey] = $entry[$metaKey];
        }
    }

    $currentUserId = (string) ($_SESSION['user']['id'] ?? '');
    $previousStatus = is_array($entry) ? (string) ($entry['status'] ?? '') : '';
    if ($currentUserId !== '') {
        if ($action === 'create' && empty($entryOutput['author_id']) && empty($entryOutput['author_user_id'])) {
            $entryOutput['author_id'] = $currentUserId;
        }
        if ($action === 'create' && empty($entryOutput['created_by'])) {
            $entryOutput['created_by'] = $currentUserId;
        }
        $entryOutput['updated_by'] = $currentUserId;
        if ($status === 'pending_review') {
            if (empty($entryOutput['author_id']) && empty($entryOutput['author_user_id'])) {
                $entryOutput['author_id'] = $entry['author_id'] ?? $entry['author_user_id'] ?? $currentUserId;
            }
            if ($previousStatus !== 'pending_review') {
                $entryOutput['submitted_by'] = $currentUserId;
                $entryOutput['submitted_at'] = date('c');
            }
        }
    }
    if (($entryOutput['status'] ?? '') !== 'trashed') {
        unset($entryOutput['deleted_at']);
    }
    
    // Save entry through the configured storage driver
    $isNewEntry = ($action === 'create') || !birkly_entry_record_exists($collectionSlug, $entryId);
    if (!birkly_entry_write_record($collectionSlug, $entryId, $entryOutput)) {
        error_log("Failed to save entry for collection={$collectionSlug}, entryId={$entryId}");
        return ['status' => 'error', 'message' => 'Failed to save entry'];
    }

    // Allow plugins to react to entry save (e.g., sync status with user)
    if (function_exists('do_action')) {
        do_action('after_entry_save', $entryOutput, $collectionSlug, $entry);
    }
    
    // Trigger automation system (when plugin active)
    try {
        require_once __DIR__ . '/../core/automation/bootstrap.php';
        if (automation_bootstrap()) {
            $oldStatus = $entry['status'] ?? '';
            $newStatus = $entryOutput['status'] ?? '';
            
            // Determine trigger type from create/update resolution (not URL action=save).
            if ($isNewEntry) {
                enqueue_automation('entry_created', [
                    'entry' => $entryOutput,
                    'collection' => $collectionSlug
                ]);
            } else {
                enqueue_automation('entry_updated', [
                    'entry' => $entryOutput,
                    'collection' => $collectionSlug,
                    'old_entry' => $entry
                ]);
                
                if ($oldStatus !== $newStatus) {
                    enqueue_automation('status_changed', [
                        'entry' => $entryOutput,
                        'collection' => $collectionSlug,
                        'from_status' => $oldStatus,
                        'to_status' => $newStatus
                    ]);
                }
            }
        }
    } catch (Throwable $automationError) {
        error_log('saveEntry: automation trigger skipped: ' . $automationError->getMessage());
    }
    
    // Trigger webhooks
    if (file_exists(__DIR__ . '/webhooks.php')) {
            // Set flag to prevent webhooks.php from executing its switch statement
            $GLOBALS['__HARU_INTERNAL_CALL'] = true;
            require_once __DIR__ . '/webhooks.php';
            $webhookEvent = $isNewEntry ? 'entry.created' : 'entry.updated';
            
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
        
        // Send notification to reviewer if entry is set to pending_review with a reviewer assigned
        $entryStatus = $entryOutput['status'] ?? '';
        $reviewerId = $entryOutput['reviewer'] ?? '';
        
        error_log("DEBUG: Checking notification - status=" . $entryStatus . ", reviewer=" . $reviewerId);
        
        if ($entryStatus === 'pending_review' && !empty($reviewerId)) {
            // Get reviewer's email
            $reviewerEmail = null;
            $reviewerName = null;
            
            // Try to get reviewer info from user files
            $userFiles = glob(__DIR__ . '/../users/*.json');
            error_log("DEBUG: Looking for reviewer with ID=" . $reviewerId . ", found " . count($userFiles) . " user files");
            
            foreach ($userFiles as $userFile) {
                $userData = json_decode(file_get_contents($userFile), true);
                if ($userData) {
                    // Check both ID and email match
                    $userId = $userData['id'] ?? '';
                    $userEmail = $userData['email'] ?? '';
                    if ($userId === $reviewerId || $userEmail === $reviewerId) {
                        $reviewerEmail = $userEmail ?: $userId; // Use email if available, otherwise use ID
                        $reviewerName = $userData['name'] ?? $reviewerEmail;
                        error_log("DEBUG: Found reviewer - email=" . $reviewerEmail . ", name=" . $reviewerName);
                        break;
                    }
                }
            }
            
            if (!$reviewerEmail) {
                error_log("DEBUG: Reviewer not found for ID=" . $reviewerId);
            }
            
            // If we found the reviewer, create a notification log entry for them
            if ($reviewerEmail) {
                require_once __DIR__ . '/log.php';
                
                // Use log_security_event but we need to set the user to the reviewer
                // Since log_security_event uses $_SESSION['user']['email'], we'll write directly
                $logDir = dirname(__DIR__) . '/logs';
                if (!file_exists($logDir)) {
                    mkdir($logDir, 0700, true);
                }
                $logFile = $logDir . '/security.log';
                
                $notificationEntry = [
                    'timestamp' => date('c'),
                    'user' => strtolower(trim($reviewerEmail)), // Normalize email: lowercase and trim
                    'event' => 'review_action',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'details' => [
                        'collection' => $collectionSlug,
                        'id' => $entryId,
                        'title' => $entryOutput['title'] ?? 'Untitled',
                        'action' => 'pending_review',
                        'reviewer' => strtolower(trim($reviewerEmail)), // Normalize email
                        'assigned_by' => strtolower(trim($_SESSION['user']['email'] ?? 'unknown'))
                    ]
                ];
                
                $entryJson = json_encode($notificationEntry) . "\n";
                $result = @file_put_contents($logFile, $entryJson, FILE_APPEND | LOCK_EX);
                
                // Force flush
                if ($result !== false) {
                    clearstatcache(false, $logFile);
                    usleep(100000); // 0.1 second delay to ensure write
                }
                
                // Log for debugging
                error_log("Review notification: reviewer=" . $reviewerEmail . " (normalized: " . strtolower(trim($reviewerEmail)) . "), entry=" . $entryId . ", result=" . ($result !== false ? 'success (' . $result . ' bytes)' : 'failed') . ", logFile=" . $logFile);
            } else {
                error_log("Review notification NOT created: reviewer not found for ID=" . $reviewerId);
            }
        }
        
        return [
            'status' => 'ok',
            'data' => [
                'id' => $entryId,
                'message' => 'Entry saved successfully'
            ]
        ];
}

function deleteEntry() {
    // SECURITY: deleting entries requires delete permission
    requireAuth('delete');

    $input = readJsonRequestBody();
    $collectionSlug = (string) ($input['collection'] ?? $_POST['collection'] ?? '');
    $entryId = (string) ($input['entry_id'] ?? $_POST['entry_id'] ?? '');
    $csrf = (string) ($input['_csrf'] ?? $_POST['_csrf'] ?? '');

    if (!validateStateChangeCsrfToken($csrf, 'trash_entry')) {
        return ['status' => 'error', 'message' => 'Invalid CSRF token'];
    }
    
    if (empty($collectionSlug) || empty($entryId)) {
        return ['status' => 'error', 'message' => 'Collection slug and entry ID are required'];
    }

    $entryData = loadEntryRecord($collectionSlug, $entryId);
    if ($entryData === null) {
        return ['status' => 'error', 'message' => 'Entry not found'];
    }

    try {
        if (function_exists('do_action_strict')) {
            do_action_strict('before_entry_delete', $collectionSlug, $entryId, $entryData);
        }
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
    
    saveVersionHistory($collectionSlug, $entryId, $entryData);
    $entryData['status'] = 'trashed';
    $entryData['version'] = intval($entryData['version'] ?? 1) + 1;
    $entryData['updated_at'] = date('Y-m-d H:i:s');
    $entryData['deleted_at'] = date('c');
    $currentUserId = (string) ($_SESSION['user']['id'] ?? '');
    if ($currentUserId !== '') {
        $entryData['updated_by'] = $currentUserId;
    }

    if (!persistEntryRecord($collectionSlug, $entryId, $entryData)) {
        return ['status' => 'error', 'message' => 'Failed to move entry to trash'];
    }

    log_security_event('trash_entry', ['collection' => $collectionSlug, 'entry_id' => $entryId]);

    return [
        'status' => 'ok',
        'message' => 'Entry moved to trash',
        'data' => [
            'id' => $entryId,
            'status' => 'trashed',
        ],
    ];
}

function restoreEntry() {
    requireAuth('write');

    $input = readJsonRequestBody();
    $collectionSlug = (string) ($input['collection'] ?? $_POST['collection'] ?? '');
    $entryId = (string) ($input['entry_id'] ?? $_POST['entry_id'] ?? '');
    $csrf = (string) ($input['_csrf'] ?? $_POST['_csrf'] ?? '');

    if (!validateStateChangeCsrfToken($csrf, 'restore_entry')) {
        return ['status' => 'error', 'message' => 'Invalid CSRF token'];
    }

    if ($collectionSlug === '' || $entryId === '') {
        return ['status' => 'error', 'message' => 'Collection slug and entry ID are required'];
    }

    $entryData = loadEntryRecord($collectionSlug, $entryId);
    if ($entryData === null) {
        return ['status' => 'error', 'message' => 'Entry not found'];
    }

    saveVersionHistory($collectionSlug, $entryId, $entryData);
    $entryData['status'] = 'unpublished';
    $entryData['version'] = intval($entryData['version'] ?? 1) + 1;
    $entryData['updated_at'] = date('Y-m-d H:i:s');
    unset($entryData['deleted_at']);
    $currentUserId = (string) ($_SESSION['user']['id'] ?? '');
    if ($currentUserId !== '') {
        $entryData['updated_by'] = $currentUserId;
    }

    if (!persistEntryRecord($collectionSlug, $entryId, $entryData)) {
        return ['status' => 'error', 'message' => 'Failed to restore entry'];
    }

    log_security_event('restore_entry', ['collection' => $collectionSlug, 'entry_id' => $entryId]);

    return [
        'status' => 'ok',
        'message' => 'Entry restored successfully',
        'data' => [
            'id' => $entryId,
            'status' => 'unpublished',
        ],
    ];
}

function generateSlug($title) {
    return birkly_generate_slug((string)$title);
}

function readJsonRequestBody(): array {
    $rawInput = file_get_contents('php://input');
    if (!is_string($rawInput) || trim($rawInput) === '') {
        return [];
    }

    $input = json_decode($rawInput, true);
    return is_array($input) ? $input : [];
}

function validateStateChangeCsrfToken(string $csrf, string $actionName): bool {
    if (validate_csrf_token($csrf)) {
        return true;
    }

    log_security_event('permission_denied', ['action' => $actionName, 'reason' => 'invalid CSRF']);
    return false;
}

function getCollectionPaths(string $collectionSlug): ?array {
    $collectionSlug = sanitize_identifier($collectionSlug);
    if ($collectionSlug === false || $collectionSlug === '') {
        return null;
    }

    if (!birkly_entry_collection_exists($collectionSlug)) {
        return null;
    }

    return [
        'slug' => $collectionSlug,
        'collection_dir' => birkly_project_root() . '/content/collections/' . $collectionSlug,
        'entries_dir' => birkly_project_root() . '/content/collections/' . $collectionSlug . '/entries',
        'storage_key' => birkly_entry_collection_storage_key($collectionSlug),
    ];
}

function getEntryFilePath(string $collectionSlug, string $entryId): ?string {
    $paths = getCollectionPaths($collectionSlug);
    if ($paths === null) {
        return null;
    }

    $entryId = sanitize_identifier($entryId);
    if ($entryId === false || $entryId === '') {
        return null;
    }

    return $paths['entries_dir'] . '/' . $entryId . '.json';
}

function loadEntryRecord(string $collectionSlug, string $entryId): ?array {
    $collectionSlug = sanitize_identifier($collectionSlug);
    $entryId = sanitize_identifier($entryId);
    if ($collectionSlug === false || $entryId === false || $collectionSlug === '' || $entryId === '') {
        return null;
    }

    return birkly_entry_read_record($collectionSlug, $entryId);
}

function persistEntryRecord(string $collectionSlug, string $entryId, array $entryData): bool {
    $collectionSlug = sanitize_identifier($collectionSlug);
    $entryId = sanitize_identifier($entryId);
    if ($collectionSlug === false || $entryId === false || $collectionSlug === '' || $entryId === '') {
        return false;
    }

    return birkly_entry_write_record($collectionSlug, $entryId, $entryData);
}

function ensureUniqueEntrySlug(string $collectionSlug, string $baseSlug, ?string $ignoreEntryId = null): string {
    $paths = getCollectionPaths($collectionSlug);
    if ($paths === null) {
        return $baseSlug;
    }

    $baseSlug = birkly_normalize_slug($baseSlug);
    if ($baseSlug === '') {
        $baseSlug = uniqid();
    }

    $candidate = $baseSlug;
    $suffix = 2;
    $existingSlugs = [];
    foreach (birkly_entry_list_records($collectionSlug) as $entry) {
        $entryId = (string) ($entry['id'] ?? '');
        if ($ignoreEntryId !== null && $entryId === $ignoreEntryId) {
            continue;
        }

        $existingSlug = birkly_normalize_slug((string) ($entry['slug'] ?? ''));
        if ($existingSlug !== '') {
            $existingSlugs[$existingSlug] = true;
        }
    }

    while (isset($existingSlugs[$candidate])) {
        $candidate = $baseSlug . '-' . $suffix;
        $suffix++;
    }

    return $candidate;
}

function duplicateEntry(): array {
    requireAuth('write');

    $input = readJsonRequestBody();
    $collectionSlug = (string) ($input['collection'] ?? $_POST['collection'] ?? '');
    $entryId = (string) ($input['entry_id'] ?? $_POST['entry_id'] ?? '');
    $csrf = (string) ($input['_csrf'] ?? $_POST['_csrf'] ?? '');

    if (!validateStateChangeCsrfToken($csrf, 'duplicate_entry')) {
        return ['status' => 'error', 'message' => 'Invalid CSRF token'];
    }

    if ($collectionSlug === '' || $entryId === '') {
        return ['status' => 'error', 'message' => 'Collection slug and entry ID are required'];
    }

    $sourceEntry = loadEntryRecord($collectionSlug, $entryId);
    if ($sourceEntry === null) {
        return ['status' => 'error', 'message' => 'Entry not found'];
    }

    $newEntryId = uniqid();
    $baseTitle = trim((string) ($sourceEntry['title'] ?? $entryId));
    $copyTitle = $baseTitle !== '' ? $baseTitle . ' (copy)' : $newEntryId . ' (copy)';
    $baseSlug = trim((string) ($sourceEntry['slug'] ?? ''));
    if ($baseSlug === '') {
        $baseSlug = birkly_generate_slug($copyTitle);
    }
    $copySlug = ensureUniqueEntrySlug($collectionSlug, $baseSlug . '-copy');

    $duplicatedEntry = $sourceEntry;
    $duplicatedEntry['id'] = $newEntryId;
    $duplicatedEntry['title'] = $copyTitle;
    $duplicatedEntry['slug'] = $copySlug;
    $duplicatedEntry['status'] = 'unpublished';
    $duplicatedEntry['version'] = 1;
    $duplicatedEntry['created_at'] = date('Y-m-d H:i:s');
    $duplicatedEntry['updated_at'] = date('Y-m-d H:i:s');

    foreach (['reviewed_at', 'reviewed_by', 'submitted_at', 'review_notes', 'deleted_at'] as $key) {
        unset($duplicatedEntry[$key]);
    }

    $currentUserId = (string) ($_SESSION['user']['id'] ?? '');
    if ($currentUserId !== '') {
        $duplicatedEntry['created_by'] = $currentUserId;
        $duplicatedEntry['updated_by'] = $currentUserId;
        if (empty($duplicatedEntry['author_id']) && empty($duplicatedEntry['author_user_id'])) {
            $duplicatedEntry['author_id'] = $currentUserId;
        }
    }

    if (!persistEntryRecord($collectionSlug, $newEntryId, $duplicatedEntry)) {
        return ['status' => 'error', 'message' => 'Failed to duplicate entry'];
    }

    log_security_event('duplicate_entry', [
        'collection' => $collectionSlug,
        'entry_id' => $entryId,
        'new_entry_id' => $newEntryId,
    ]);

    return [
        'status' => 'ok',
        'message' => 'Entry duplicated successfully',
        'data' => [
            'id' => $newEntryId,
            'slug' => $copySlug,
            'title' => $copyTitle,
        ],
    ];
}

function listEntries() {
    $collectionSlug = trim((string) ($_GET['collection'] ?? ''));
    if ($collectionSlug === '') {
        return ['status' => 'error', 'message' => 'Collection slug is required'];
    }

    $GLOBALS['__HARU_INTERNAL_CALL'] = true;
    require_once __DIR__ . '/admin_templates.php';

    if (!function_exists('getEntriesData')) {
        return ['status' => 'error', 'message' => 'Entries list loader is unavailable'];
    }

    $data = getEntriesData();
    if (isset($data['error'])) {
        return ['status' => 'error', 'message' => (string) $data['error']];
    }

    return [
        'status' => 'ok',
        'data' => [
            'entries' => $data['entries'] ?? [],
            'total' => (int) ($data['entries_total'] ?? 0),
            'page' => (int) ($data['entries_page'] ?? 1),
            'per_page' => (int) ($data['entries_per_page'] ?? 20),
            'total_pages' => (int) ($data['entries_total_pages'] ?? 1),
        ],
    ];
}

/**
 * Perform bulk operation on entries
 */
function performBulkOperation() {
    try {
        // SECURITY: bulk operations are state-changing
        // Permission depends on action:
        // - publish/unpublish: write
        // - delete: delete
        requireAuth('write');

        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        
        if (!$input) {
            error_log("performBulkOperation: Failed to decode JSON. Raw input: " . substr($rawInput, 0, 200));
            return ['status' => 'error', 'message' => 'Invalid request data'];
        }
        
        $collectionSlug = $input['collection'] ?? '';
        $action = $input['action'] ?? '';
        $entryIds = $input['entry_ids'] ?? [];
        
        error_log("performBulkOperation: collection=$collectionSlug, action=$action, entryIds=" . count($entryIds));
    } catch (Exception $e) {
        error_log("performBulkOperation: Exception at start: " . $e->getMessage());
        return ['status' => 'error', 'message' => 'Error reading request: ' . $e->getMessage()];
    }
    
    if (empty($collectionSlug)) {
        return ['status' => 'error', 'message' => 'Collection slug is required'];
    }
    
    if (empty($action)) {
        return ['status' => 'error', 'message' => 'Action is required'];
    }
    
    // Now that we know the bulk action, enforce the correct permission
    if ($action === 'delete') {
        requireAuth('delete');
    } else {
        requireAuth('write');
    }

    if (empty($entryIds) || !is_array($entryIds)) {
        return ['status' => 'error', 'message' => 'Entry IDs are required'];
    }
    
    // Validate CSRF token
    error_log("performBulkOperation: Validating CSRF token");
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        error_log("performBulkOperation: CSRF token validation failed");
        log_security_event('permission_denied', ['action' => 'bulk_operation', 'reason' => 'invalid CSRF']);
        return ['status' => 'error', 'message' => 'Invalid CSRF token'];
    }
    error_log("performBulkOperation: CSRF token validated");
    
    // Validate collection exists
    error_log("performBulkOperation: Validating collection");
    if (!birkly_entry_collection_exists($collectionSlug)) {
        error_log("performBulkOperation: Collection not found: $collectionSlug");
        return ['status' => 'error', 'message' => 'Invalid collection'];
    }
    error_log("performBulkOperation: Collection validated");
    
    // Initialize cache for invalidation
    error_log("performBulkOperation: Initializing cache");
    $cache = new Cache();
    error_log("performBulkOperation: Cache initialized");
    
    error_log("performBulkOperation: Starting to process entries");
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    error_log("performBulkOperation: Processing " . count($entryIds) . " entries");
    foreach ($entryIds as $entryId) {
        error_log("performBulkOperation: Processing entry $entryId");
        
        $entryData = loadEntryRecord($collectionSlug, $entryId);
        if ($entryData === null) {
            $errorCount++;
            $errors[] = "Entry $entryId not found";
            continue;
        }
        
        try {
            error_log("performBulkOperation: Processing action '$action' for entry $entryId");
            switch ($action) {
                case 'publish':
                    error_log("performBulkOperation: Setting status to published");
                    saveVersionHistory($collectionSlug, $entryId, $entryData);
                    $entryData['status'] = 'published';
                    $entryData['version'] = intval($entryData['version'] ?? 1) + 1;
                    $entryData['updated_at'] = date('Y-m-d H:i:s');
                    break;
                    
                case 'unpublish':
                    error_log("performBulkOperation: Setting status to unpublished");
                    saveVersionHistory($collectionSlug, $entryId, $entryData);
                    $entryData['status'] = 'unpublished';
                    $entryData['version'] = intval($entryData['version'] ?? 1) + 1;
                    $entryData['updated_at'] = date('Y-m-d H:i:s');
                    break;
                    
                case 'delete':
                    error_log("performBulkOperation: Moving entry to trash");
                    saveVersionHistory($collectionSlug, $entryId, $entryData);
                    $entryData['status'] = 'trashed';
                    $entryData['version'] = intval($entryData['version'] ?? 1) + 1;
                    $entryData['deleted_at'] = date('c');
                    $entryData['updated_at'] = date('Y-m-d H:i:s');
                    break;
                case 'restore':
                    error_log("performBulkOperation: Restoring entry from trash");
                    saveVersionHistory($collectionSlug, $entryId, $entryData);
                    $entryData['status'] = 'unpublished';
                    $entryData['version'] = intval($entryData['version'] ?? 1) + 1;
                    unset($entryData['deleted_at']);
                    $entryData['updated_at'] = date('Y-m-d H:i:s');
                    break;
                    
                default:
                    $errorCount++;
                    $errors[] = "Unknown action: $action";
                    error_log("performBulkOperation: Unknown action '$action' for entry $entryId");
                    continue 2;
            }
            
            error_log("performBulkOperation: Saving entry file");
            if (persistEntryRecord($collectionSlug, $entryId, $entryData)) {
                $successCount++;
            } else {
                $errorCount++;
                $errors[] = "Failed to save entry $entryId";
            }
        } catch (Exception $e) {
            $errorCount++;
            $errors[] = "Error processing entry $entryId: " . $e->getMessage();
        } catch (Throwable $e) {
            $errorCount++;
            $errors[] = "Fatal error processing entry $entryId: " . $e->getMessage();
        }
    }
    
    // Invalidate cache after bulk operation (non-blocking)
    try {
        $cache->invalidateCollection($collectionSlug);
    } catch (Exception $e) {
        // Don't fail the operation if cache invalidation fails
    } catch (Throwable $e) {
        // Don't fail the operation if cache invalidation fails
    }
    
    // Build and return result
    $result = [
        'status' => ($errorCount > 0 && $successCount === 0) ? 'error' : 'ok',
        'message' => $errorCount > 0 && $successCount === 0 
            ? 'All operations failed' 
            : "Successfully processed $successCount entr" . ($successCount === 1 ? 'y' : 'ies'),
        'count' => $successCount,
        'errors' => $errors,
        'error_count' => $errorCount
    ];
    
    return $result;
}

function bulkFieldEdit(): array {
    requireAuth('write');

    $input = readJsonRequestBody();
    $collectionSlug = (string) ($input['collection'] ?? '');
    $entryIds = $input['entry_ids'] ?? [];
    $fieldName = trim((string) ($input['field_name'] ?? ''));
    $value = $input['value'] ?? '';
    $csrf = (string) ($input['_csrf'] ?? '');

    if (!validateStateChangeCsrfToken($csrf, 'bulk_field_edit')) {
        return ['status' => 'error', 'message' => 'Invalid CSRF token'];
    }

    if ($collectionSlug === '' || !is_array($entryIds) || $entryIds === [] || $fieldName === '') {
        return ['status' => 'error', 'message' => 'Collection, entry IDs, and field name are required'];
    }

    $paths = getCollectionPaths($collectionSlug);
    if ($paths === null) {
        return ['status' => 'error', 'message' => 'Invalid collection'];
    }

    $schema = birkly_entry_read_schema($collectionSlug);

    $fieldDefinition = null;
    foreach (($schema['fields'] ?? []) as $field) {
        if (($field['name'] ?? '') === $fieldName) {
            $fieldDefinition = $field;
            break;
        }
    }

    $processedValue = $value;
    if (is_string($processedValue)) {
        $trimmed = trim($processedValue);
        if (($trimmed !== '') && (($trimmed[0] ?? '') === '[' || ($trimmed[0] ?? '') === '{')) {
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $processedValue = $decoded;
            }
        }
    }

    if ($fieldDefinition !== null) {
        $fieldType = (string) ($fieldDefinition['type'] ?? '');
        if ($fieldType !== '') {
            $className = getFieldtypeClass($fieldType);
            if ($className && class_exists($className)) {
                try {
                    $ft = new $className([
                        'name' => $fieldName,
                        'label' => $fieldDefinition['label'] ?? $fieldName,
                        'value' => $processedValue,
                        'required' => $fieldDefinition['required'] ?? false,
                        'options' => $fieldDefinition['options'] ?? [],
                    ]);
                    if (method_exists($ft, 'processValue') && !is_array($processedValue)) {
                        $processedValue = $ft->processValue($processedValue);
                    }
                } catch (Throwable $e) {
                    error_log('bulkFieldEdit fieldtype processing failed: ' . $e->getMessage());
                }
            }
        }
    }

    $successCount = 0;
    $errors = [];
    $currentUserId = (string) ($_SESSION['user']['id'] ?? '');

    foreach ($entryIds as $rawEntryId) {
        $entryId = sanitize_identifier((string) $rawEntryId);
        if ($entryId === false || $entryId === '') {
            $errors[] = 'Invalid entry identifier';
            continue;
        }

        $entryData = loadEntryRecord($collectionSlug, $entryId);
        if ($entryData === null) {
            $errors[] = "Entry {$entryId} not found";
            continue;
        }

        saveVersionHistory($collectionSlug, $entryId, $entryData);
        $entryData[$fieldName] = $processedValue;
        $entryData['version'] = intval($entryData['version'] ?? 1) + 1;
        $entryData['updated_at'] = date('Y-m-d H:i:s');
        if ($currentUserId !== '') {
            $entryData['updated_by'] = $currentUserId;
        }

        if (!persistEntryRecord($collectionSlug, $entryId, $entryData)) {
            $errors[] = "Failed to save entry {$entryId}";
            continue;
        }

        $successCount++;
    }

    log_security_event('bulk_field_edit', [
        'collection' => $collectionSlug,
        'field_name' => $fieldName,
        'count' => $successCount,
    ]);

    return [
        'status' => ($successCount > 0 || $errors === []) ? 'ok' : 'error',
        'message' => $successCount > 0
            ? "Updated {$successCount} entr" . ($successCount === 1 ? 'y' : 'ies')
            : 'No entries were updated',
        'count' => $successCount,
        'errors' => $errors,
    ];
}

/**
 * Save version history
 */
function saveVersionHistory($collectionSlug, $entryId, $entryData) {
    $version = (int) ($entryData['version'] ?? 1);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $author = $_SESSION['user']['email'] ?? $_SESSION['user']['name'] ?? 'Unknown';

    $versionData = array_merge($entryData, [
        'author' => $author,
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    birkly_entry_write_version($collectionSlug, $entryId, $version, $versionData);
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
 * Get diff between two versions
 */
function getVersionDiff() {
    $collectionSlug = $_GET['collection'] ?? '';
    $entryId = $_GET['entry_id'] ?? '';
    $version1 = $_GET['version1'] ?? '';
    $version2 = $_GET['version2'] ?? '';
    
    if (empty($collectionSlug) || empty($entryId)) {
        return ['status' => 'error', 'message' => 'Collection slug and entry ID are required'];
    }
    
    if (empty($version1) || empty($version2)) {
        return ['status' => 'error', 'message' => 'Both versions are required'];
    }
    
    $entryFile = __DIR__ . "/../content/collections/$collectionSlug/entries/$entryId.json";
    $versionsDir = __DIR__ . "/../content/collections/$collectionSlug/entries/$entryId/versions";
    
    // Load version 1
    $version1Data = null;
    if ($version1 === 'current') {
        if (file_exists($entryFile)) {
            $version1Data = json_decode(file_get_contents($entryFile), true);
        }
    } else {
        $version1File = "$versionsDir/v{$version1}.json";
        if (file_exists($version1File)) {
            $version1Data = json_decode(file_get_contents($version1File), true);
        }
    }
    
    // Load version 2
    $version2Data = null;
    if ($version2 === 'current') {
        if (file_exists($entryFile)) {
            $version2Data = json_decode(file_get_contents($entryFile), true);
        }
    } else {
        $version2File = "$versionsDir/v{$version2}.json";
        if (file_exists($version2File)) {
            $version2Data = json_decode(file_get_contents($version2File), true);
        }
    }
    
    if (!$version1Data || !$version2Data) {
        return ['status' => 'error', 'message' => 'One or both versions not found'];
    }
    
    // Calculate diff
    $diff = calculateDiff($version1Data, $version2Data);
    
    return [
        'status' => 'ok',
        'version1' => $version1Data,
        'version2' => $version2Data,
        'diff' => $diff
    ];
}

/**
 * Calculate diff between two data structures
 */
function calculateDiff($old, $new, $path = '') {
    $diff = [];
    
    // Get all keys from both versions
    $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));
    
    // Skip internal fields
    $skipFields = ['author', 'created_at', 'updated_at', 'version'];
    
    foreach ($allKeys as $key) {
        if (in_array($key, $skipFields)) {
            continue;
        }
        
        $currentPath = $path ? "$path.$key" : $key;
        $oldValue = $old[$key] ?? null;
        $newValue = $new[$key] ?? null;
        
        // Check if values are different
        if ($oldValue !== $newValue) {
            if (!isset($old[$key])) {
                // Added
                $diff[] = [
                    'path' => $currentPath,
                    'type' => 'added',
                    'old' => null,
                    'new' => formatValue($newValue)
                ];
            } elseif (!isset($new[$key])) {
                // Removed
                $diff[] = [
                    'path' => $currentPath,
                    'type' => 'removed',
                    'old' => formatValue($oldValue),
                    'new' => null
                ];
            } elseif (is_array($oldValue) && is_array($newValue)) {
                // Recursively diff arrays
                $nestedDiff = calculateDiff($oldValue, $newValue, $currentPath);
                $diff = array_merge($diff, $nestedDiff);
            } else {
                // Modified
                $diff[] = [
                    'path' => $currentPath,
                    'type' => 'modified',
                    'old' => formatValue($oldValue),
                    'new' => formatValue($newValue)
                ];
            }
        }
    }
    
    return $diff;
}

/**
 * Format value for display
 */
function formatValue($value) {
    if (is_array($value)) {
        return json_encode($value, JSON_PRETTY_PRINT);
    } elseif (is_bool($value)) {
        return $value ? 'true' : 'false';
    } elseif (is_null($value)) {
        return 'null';
    } else {
        return (string)$value;
    }
}

/**
 * Restore version
 */
function restoreVersion() {
    // SECURITY: restoring a version changes content
    requireAuth('write');

    $input = readJsonRequestBody();
    
    $collectionSlug = $input['collection'] ?? '';
    $entryId = $input['entry_id'] ?? '';
    $versionToRestore = $input['version'] ?? '';
    $csrf = (string) ($input['_csrf'] ?? '');

    if (!validateStateChangeCsrfToken($csrf, 'restore_entry_version')) {
        return ['status' => 'error', 'message' => 'Invalid CSRF token'];
    }
    
    if (empty($collectionSlug) || empty($entryId) || empty($versionToRestore)) {
        return ['status' => 'error', 'message' => 'Missing required parameters'];
    }
    
    $entryFile = __DIR__ . "/../content/collections/$collectionSlug/entries/$entryId.json";
    $versionFile = __DIR__ . "/../content/collections/$collectionSlug/entries/$entryId/versions/v{$versionToRestore}.json";
    
    // Load current entry to save as new version
    $currentEntryVersion = 1;
    if (file_exists($entryFile)) {
        $currentEntry = json_decode(file_get_contents($entryFile), true);
        if ($currentEntry) {
            saveVersionHistory($collectionSlug, $entryId, $currentEntry);
            $currentEntryVersion = intval($currentEntry['version'] ?? 1) + 1;
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
    $versionData['version'] = $currentEntryVersion;
    $versionData['updated_at'] = date('Y-m-d H:i:s');
    $currentUserId = (string) ($_SESSION['user']['id'] ?? '');
    if ($currentUserId !== '') {
        $versionData['updated_by'] = $currentUserId;
    }
    unset($versionData['author']); // Remove author from main entry
    
    // Restore entry
    if (persistEntryRecord($collectionSlug, $entryId, $versionData)) {
        log_security_event('restore_entry_version', [
            'collection' => $collectionSlug,
            'entry_id' => $entryId,
            'version' => $versionToRestore,
        ]);
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