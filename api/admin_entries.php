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

$action = $_GET['action'] ?? 'save';

switch ($action) {
    case 'save':
        echo json_encode(saveEntry());
        break;
    case 'delete':
        echo json_encode(deleteEntry());
        break;
    case 'list':
        echo json_encode(listEntries());
        break;
    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

function saveEntry() {
    $collectionSlug = $_POST['collection'] ?? '';
    $entryId = $_POST['entry_id'] ?? '';
    $entryData = $_POST['entry'] ?? [];
    
    // Debug logging
    error_log("=== SAVE ENTRY DEBUG ===");
    error_log("Collection slug: " . $collectionSlug);
    error_log("Entry ID: " . $entryId);
    error_log("Entry data: " . json_encode($entryData));
    error_log("POST data: " . json_encode($_POST));
    
    if (empty($collectionSlug)) {
        return ['error' => 'Collection slug is required'];
    }
    
    // Validate collection exists
    $collectionDir = __DIR__ . "/../content/collections/$collectionSlug";
    error_log("Collection directory path: " . $collectionDir);
    error_log("Directory exists: " . (is_dir($collectionDir) ? 'YES' : 'NO'));
    
    if (!is_dir($collectionDir)) {
        return ['error' => 'Collection not found'];
    }
    
    // Create entries directory if it doesn't exist
    $entriesDir = "$collectionDir/entries";
    if (!is_dir($entriesDir)) {
        mkdir($entriesDir, 0755, true);
    }
    
    // Generate entry ID if not provided (for new entries)
    if (empty($entryId)) {
        $entryId = uniqid();
    }
    
    // Prepare entry data
    $entry = [
        'id' => $entryId,
        'title' => $entryData['title'] ?? '',
        'slug' => $entryData['slug'] ?? '',
        'status' => $entryData['status'] ?? 'draft',
        'created_at' => $entryData['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Add all other fields from entry data
    foreach ($entryData as $key => $value) {
        if (!in_array($key, ['id', 'title', 'slug', 'status', 'created_at', 'updated_at'])) {
            // Special handling for relationship fields and JSON data
            if (is_string($value)) {
                // Try to decode JSON if it's a JSON string
                if (str_starts_with($value, '[') || str_starts_with($value, '{')) {
                    $decoded = json_decode($value, true);
                    if ($decoded !== null) {
                        $entry[$key] = $decoded;
                    } else {
                        $entry[$key] = $value;
                    }
                } else {
                    $entry[$key] = $value;
                }
            } else {
                $entry[$key] = $value;
            }
        }
    }
    
    // Auto-generate slug if empty
    if (empty($entry['slug']) && !empty($entry['title'])) {
        $entry['slug'] = generateSlug($entry['title']);
    }
    
    // Save entry to file
    $entryFile = "$entriesDir/$entryId.json";
    if (file_put_contents($entryFile, json_encode($entry, JSON_PRETTY_PRINT))) {
        return [
            'status' => 'ok',
            'data' => [
                'id' => $entryId,
                'message' => 'Entry saved successfully'
            ]
        ];
    } else {
        return ['error' => 'Failed to save entry'];
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