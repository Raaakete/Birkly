<?php
/**
 * Demo Content Management API
 * Handles deletion of demo content during onboarding
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';
haru_secure_session_start();

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Parse action from various sources
$action = '';
if (isset($_GET['action'])) {
    $action = trim($_GET['action']);
} elseif (isset($_POST['action'])) {
    $action = trim($_POST['action']);
} else {
    // Try to parse from REQUEST_URI query string
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
    if ($uri) {
        parse_str($uri, $queryParams);
        $action = trim($queryParams['action'] ?? '');
    }
}

// Normalize action: lowercase and remove any whitespace
$action = strtolower(trim($action));

$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Debug logging
error_log("Demo API - Action: [" . $action . "], GET: " . json_encode($_GET) . ", POST action: " . ($_POST['action'] ?? 'none') . ", REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'none'));

if (empty($action)) {
    error_log("Demo API called without action. GET: " . json_encode($_GET) . ", POST: " . json_encode($_POST) . ", REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'none'));
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No action specified']);
    exit;
}

// Check authentication AFTER parsing action (so we can log what action was requested)
requireAuth('manage_content');

// Helper to delete demo content
function deleteDemoContent() {
    $collectionsPath = __DIR__ . '/../content/collections';
    $demoCollections = ['blog']; // Collections that are demo content
    
    $deleted = [];
    $errors = [];
    
    foreach ($demoCollections as $collectionSlug) {
        $collectionPath = "$collectionsPath/$collectionSlug";
        if (is_dir($collectionPath)) {
            // Recursively delete the collection directory
            $result = deleteDirectory($collectionPath);
            if ($result) {
                $deleted[] = $collectionSlug;
            } else {
                $errors[] = "Failed to delete collection: $collectionSlug";
            }
        }
    }
    
    return [
        'success' => count($errors) === 0,
        'deleted' => $deleted,
        'errors' => $errors,
        'message' => count($errors) === 0 
            ? 'Demo content deleted successfully' 
            : 'Some demo content could not be deleted'
    ];
}

// Helper to delete demo website folder
function deleteDemoWebsite() {
    $demoBlogPath = __DIR__ . '/../demo-blog';
    
    if (is_dir($demoBlogPath)) {
        $result = deleteDirectory($demoBlogPath);
        if ($result) {
            return [
                'success' => true,
                'message' => 'Demo website deleted successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to delete demo website'
            ];
        }
    }
    
    return [
        'success' => true,
        'message' => 'Demo website does not exist'
    ];
}

// Helper function to recursively delete a directory
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = "$dir/$file";
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}

// Helper to list available demo content (for info purposes)
function listDemoContent() {
    $collectionsPath = __DIR__ . '/../content/collections';
    $demoCollections = ['blog'];
    
    $exists = [];
    foreach ($demoCollections as $collectionSlug) {
        $collectionPath = "$collectionsPath/$collectionSlug";
        if (is_dir($collectionPath)) {
            $collectionFile = "$collectionPath/collection.json";
            if (file_exists($collectionFile)) {
                $collectionData = json_decode(file_get_contents($collectionFile), true);
                if ($collectionData) {
                    // Count entries
                    $entriesPath = "$collectionPath/entries";
                    $entryCount = 0;
                    if (is_dir($entriesPath)) {
                        $entryFiles = glob("$entriesPath/*.json");
                        $entryCount = count($entryFiles);
                    }
                    
                    $exists[] = [
                        'slug' => $collectionSlug,
                        'name' => $collectionData['title'] ?? $collectionSlug,
                        'entries_count' => $entryCount
                    ];
                }
            }
        }
    }
    
    // Check if demo website exists
    $demoBlogPath = __DIR__ . '/../demo-blog';
    $hasDemoWebsite = is_dir($demoBlogPath);
    
    return [
        'collections' => $exists,
        'has_demo_website' => $hasDemoWebsite,
        'demo_website_path' => $hasDemoWebsite ? 'demo-blog' : null
    ];
}

// Debug: Log normalized action
error_log("Demo API - Normalized action: [" . $action . "]");

switch ($action) {
    case 'list':
        // List available demo content (for info)
        $demoContent = listDemoContent();
        echo json_encode([
            'status' => 'ok',
            'data' => $demoContent
        ]);
        exit;
        
    case 'delete':
        // Delete demo content and website
        $deleteContent = $input['delete_content'] ?? true;
        $deleteWebsite = $input['delete_website'] ?? true;
        
        $results = [];
        
        if ($deleteContent) {
            $contentResult = deleteDemoContent();
            $results['content'] = $contentResult;
        }
        
        if ($deleteWebsite) {
            $websiteResult = deleteDemoWebsite();
            $results['website'] = $websiteResult;
        }
        
        $allSuccess = true;
        foreach ($results as $result) {
            if (!$result['success']) {
                $allSuccess = false;
                break;
            }
        }
        
        if ($allSuccess) {
            // Update onboarding progress
            require_once __DIR__ . '/onboarding.php';
            $userId = $_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown';
            saveOnboardingProgress($userId, [
                'demo_content_deleted' => true,
                'demo_content_kept' => false
            ]);
            
            echo json_encode([
                'status' => 'ok',
                'message' => 'Demo content deleted successfully',
                'data' => $results
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Some demo content could not be deleted',
                'data' => $results
            ]);
        }
        exit;
        
    case 'keep':
        // Keep demo content (just update onboarding progress)
        require_once __DIR__ . '/onboarding.php';
        $userId = $_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown';
        saveOnboardingProgress($userId, [
            'demo_content_deleted' => false,
            'demo_content_kept' => true
        ]);
        
        echo json_encode([
            'status' => 'ok',
            'message' => 'Demo content will be kept'
        ]);
        exit;
        
    default:
        // Debug: Log what action we got that didn't match
        error_log("Demo API - Default case hit! Action was: [" . $action . "], type: " . gettype($action) . ", length: " . strlen($action));
        error_log("Demo API - Action hex: " . bin2hex($action));
        error_log("Demo API - Available cases: list, delete, keep");
        http_response_code(400);
        echo json_encode([
            'status' => 'error', 
            'message' => 'Invalid action: ' . $action,
            'debug' => [
                'action' => $action,
                'action_type' => gettype($action),
                'action_length' => strlen($action),
                'action_hex' => bin2hex($action),
                'get_action' => $_GET['action'] ?? null,
                'post_action' => $_POST['action'] ?? null
            ]
        ]);
        exit;
}
