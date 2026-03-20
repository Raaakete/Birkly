<?php
/**
 * Sub-Collections API for Birkly
 * 
 * Handles sub-collection configuration and management.
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../core/sub_entries.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/storage/StorageFactory.php';

// Require admin authentication
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

requireAuth('manage_content');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

// Get sub-collections for a collection
if ($action === 'list') {
    $collection_slug = $input['collection_slug'] ?? $_GET['collection_slug'] ?? '';
    
    if (empty($collection_slug)) {
        echo json_encode(['status' => 'error', 'message' => 'Collection slug required']);
        exit;
    }
    
    $subCollections = getSubCollections($collection_slug);
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'sub_collections' => $subCollections
        ]
    ]);
    exit;
}

// Get single sub-collection config
if ($action === 'get') {
    $collection_slug = $input['collection_slug'] ?? $_GET['collection_slug'] ?? '';
    $sub_collection_slug = $input['sub_collection_slug'] ?? $_GET['sub_collection_slug'] ?? '';
    
    if (empty($collection_slug) || empty($sub_collection_slug)) {
        echo json_encode(['status' => 'error', 'message' => 'Collection slug and sub-collection slug required']);
        exit;
    }
    
    $config = getSubCollectionConfig($collection_slug, $sub_collection_slug);
    
    if ($config) {
        echo json_encode([
            'status' => 'ok',
            'data' => ['sub_collection' => $config]
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Sub-collection not found']);
    }
    exit;
}

// Validate sub-collection configuration
if ($action === 'validate') {
    $collection_slug = $input['collection_slug'] ?? '';
    $subCollectionData = $input['sub_collection'] ?? [];
    
    $errors = [];
    
    if (empty($subCollectionData['slug'])) {
        $errors[] = 'Sub-collection slug is required';
    } else {
        // Validate slug format
        if (!preg_match('/^[a-z0-9-]+$/', $subCollectionData['slug'])) {
            $errors[] = 'Sub-collection slug can only contain lowercase letters, numbers, and hyphens';
        }
    }
    
    if (empty($subCollectionData['name'])) {
        $errors[] = 'Sub-collection name is required';
    }
    
    // Check for duplicate slugs within the same collection
    if (!empty($collection_slug) && !empty($subCollectionData['slug'])) {
        $storage = get_storage();
        $collectionPath = "collections/{$collection_slug}/collection.json";
        $collection = $storage->read($collectionPath);
        
        if ($collection) {
            $existingSubCollections = $collection['sub_collections'] ?? [];
            foreach ($existingSubCollections as $existing) {
                if (($existing['slug'] ?? '') === $subCollectionData['slug'] && 
                    ($existing['slug'] ?? '') !== ($input['existing_slug'] ?? '')) {
                    $errors[] = 'Sub-collection slug must be unique within the collection';
                    break;
                }
            }
        }
    }
    
    if (empty($errors)) {
        echo json_encode(['status' => 'ok', 'message' => 'Valid']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Validation failed', 'errors' => $errors]);
    }
    exit;
}

// Default response
http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
