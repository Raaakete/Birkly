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

// Parse action - prioritize $_GET since it's in the query string
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$action = strtolower(trim($action));

$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Check authentication FIRST (before switch, but after parsing action)
requireAuth('manage_content');

// If action is still empty after normalization, return error
if (empty($action)) {
    error_log("Demo API called without action. GET: " . json_encode($_GET) . ", POST: " . json_encode($_POST) . ", REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'none'));
    http_response_code(400);
    echo json_encode([
        'status' => 'error', 
        'message' => 'No action specified',
        'debug' => [
            'get' => $_GET,
            'post' => $_POST,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null
        ]
    ]);
    exit;
}

// Helper to load onboarding helpers without triggering the onboarding API router
function birkly_demo_load_onboarding_helpers(): void
{
    $GLOBALS['__HARU_INTERNAL_CALL'] = true;
    require_once __DIR__ . '/onboarding.php';
}

// Helper to delete demo content
function deleteDemoContent() {
    require_once __DIR__ . '/../core/demo_starter_templates.php';
    $collectionsPath = __DIR__ . '/../content/collections';
    $demoCollections = birkly_demo_all_starter_collection_slugs();
    
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
    $demoBlogPath = __DIR__ . '/../project';
    
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
    $demoBlogPath = __DIR__ . '/../project';
    $hasDemoWebsite = is_dir($demoBlogPath);
    
    return [
        'collections' => $exists,
        'has_demo_website' => $hasDemoWebsite,
        'demo_website_path' => $hasDemoWebsite ? 'project' : null
    ];
}

// Debug: Log before switch
error_log("Demo API - About to switch on action: [" . $action . "], type: " . gettype($action) . ", length: " . strlen($action));

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
            birkly_demo_load_onboarding_helpers();
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

    case 'install_starter':
        require_once __DIR__ . '/../core/demo_starter_templates.php';
        $templateId = strtolower(trim((string) ($input['template'] ?? $input['starter_template'] ?? '')));
        $projectRoot = dirname(__DIR__);
        $result = birkly_demo_install_starter_template($templateId, $projectRoot);
        if (!$result['success']) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $result['message'] ?? 'Install failed']);
            exit;
        }
        birkly_demo_load_onboarding_helpers();
        $userId = $_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown';
        saveOnboardingProgress($userId, [
            'starter_template' => $templateId,
            'demo_content_installed' => $templateId !== 'blank',
            'demo_content_kept' => $templateId !== 'blank',
        ]);
        echo json_encode([
            'status' => 'ok',
            'message' => $result['message'],
            'data' => $result,
        ]);
        exit;
        
    case 'keep':
        // Keep demo content (just update onboarding progress)
        birkly_demo_load_onboarding_helpers();
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
        error_log("Demo API - GET: " . json_encode($_GET) . ", POST: " . json_encode($_POST));
        error_log("Demo API - REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'none'));
        error_log("Demo API - Comparing 'keep': " . ($action === 'keep' ? 'MATCH' : 'NO MATCH (' . var_export($action, true) . ')'));
        error_log("Demo API - Comparing 'delete': " . ($action === 'delete' ? 'MATCH' : 'NO MATCH'));
        http_response_code(400);
        echo json_encode([
            'status' => 'error', 
            'message' => 'Invalid action: ' . var_export($action, true),
            'debug' => [
                'action' => $action,
                'action_type' => gettype($action),
                'action_length' => strlen($action),
                'action_hex' => bin2hex($action),
                'action_equals_keep' => ($action === 'keep'),
                'action_equals_delete' => ($action === 'delete'),
                'get_action' => $_GET['action'] ?? null,
                'post_action' => $_POST['action'] ?? null,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
                'get_raw' => $_GET,
                'post_raw' => $_POST
            ]
        ]);
        exit;
}
