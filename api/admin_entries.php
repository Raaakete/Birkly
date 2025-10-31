<?php
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

$action = $_GET['action'] ?? 'save';

switch ($action) {
    case 'save':
        $result = saveEntry();
        // Ensure clean JSON output
        if (ob_get_length()) { ob_clean(); }
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
        echo json_encode($result);
        exit;
        break;
    case 'delete':
        $result = deleteEntry();
        if (ob_get_length()) { ob_clean(); }
        echo json_encode($result);
        exit;
        break;
    case 'list':
        $result = listEntries();
        if (ob_get_length()) { ob_clean(); }
        echo json_encode($result);
        exit;
        break;
    default:
        http_response_code(400);
        if (ob_get_length()) { ob_clean(); }
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        exit;
}

function saveEntry() {
    $collectionSlug = $_POST['collection'] ?? '';
    $entryId = $_POST['entry_id'] ?? '';
    $action = $_POST['action'] ?? 'create';
    
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
    
    // Prepare entry data
    $entryOutput = [
        'id' => $entryId,
        'title' => $title,
        'slug' => $entryData['slug'] ?? '',
        'status' => $entryData['status'] ?? ($entry['status'] ?? 'draft'),
        'created_at' => $entry['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Process fieldtypes to save values properly
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
                    $ft = new $className([
                        'name' => $fieldName,
                        'label' => $field['label'] ?? $fieldName,
                        'value' => $rawValue,
                        'required' => $field['required'] ?? false,
                        'options' => $field['options'] ?? []
                    ]);
                    
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
    
    if (file_exists($entryFile) && unlink($entryFile)) {
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
?> 