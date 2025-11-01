<?php
/**
 * Demo Content and Template Installer API
 * Handles installation of demo content packs and templates
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

requireAuth('manage_content');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Helper to list available demo content packs
function listDemoContentPacks() {
    $demoContentPath = __DIR__ . '/../demo-content';
    $packs = [];
    
    if (!is_dir($demoContentPath)) {
        return $packs;
    }
    
    $dirs = glob($demoContentPath . '/*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $packName = basename($dir);
        $manifestFile = $dir . '/manifest.json';
        
        if (file_exists($manifestFile)) {
            $manifest = json_decode(file_get_contents($manifestFile), true);
            if ($manifest) {
                $packs[] = [
                    'id' => $packName,
                    'name' => $manifest['name'] ?? $packName,
                    'description' => $manifest['description'] ?? '',
                    'preview_image' => $manifest['preview_image'] ?? null,
                    'collections_count' => $manifest['collections_count'] ?? 0,
                    'entries_count' => $manifest['entries_count'] ?? 0,
                    'version' => $manifest['version'] ?? '1.0.0'
                ];
            }
        }
    }
    
    return $packs;
}

// Helper to list available demo templates
function listDemoTemplates() {
    $demoTemplatesPath = __DIR__ . '/../demo-templates';
    $templates = [];
    
    if (!is_dir($demoTemplatesPath)) {
        return $templates;
    }
    
    $dirs = glob($demoTemplatesPath . '/*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $templateName = basename($dir);
        $manifestFile = $dir . '/manifest.json';
        
        if (file_exists($manifestFile)) {
            $manifest = json_decode(file_get_contents($manifestFile), true);
            if ($manifest) {
                $templates[] = [
                    'id' => $templateName,
                    'name' => $manifest['name'] ?? $templateName,
                    'description' => $manifest['description'] ?? '',
                    'preview_image' => $manifest['preview_image'] ?? null,
                    'style' => $manifest['style'] ?? 'minimal',
                    'version' => $manifest['version'] ?? '1.0.0'
                ];
            }
        }
    }
    
    return $templates;
}

// Install demo content pack
function installDemoContentPack($packId) {
    $demoContentPath = __DIR__ . "/../demo-content/$packId";
    
    if (!is_dir($demoContentPath)) {
        return ['success' => false, 'message' => 'Demo content pack not found'];
    }
    
    $manifestFile = $demoContentPath . '/manifest.json';
    if (!file_exists($manifestFile)) {
        return ['success' => false, 'message' => 'Manifest file not found'];
    }
    
    $manifest = json_decode(file_get_contents($manifestFile), true);
    if (!$manifest) {
        return ['success' => false, 'message' => 'Invalid manifest file'];
    }
    
    $storage = new Storage('../content');
    $collectionsPath = __DIR__ . '/../content/collections';
    
    // Ensure collections directory exists
    if (!is_dir($collectionsPath)) {
        mkdir($collectionsPath, 0755, true);
    }
    
    $collectionsInstalled = 0;
    $entriesInstalled = 0;
    
    // Process each collection in the pack
    $collectionsDir = $demoContentPath . '/collections';
    if (is_dir($collectionsDir)) {
        $collectionDirs = glob($collectionsDir . '/*', GLOB_ONLYDIR);
        
        foreach ($collectionDirs as $collectionDir) {
            $collectionSlug = basename($collectionDir);
            
            // Read collection metadata
            $collectionFile = $collectionDir . '/collection.json';
            $schemaFile = $collectionDir . '/schema.json';
            
            if (!file_exists($collectionFile)) {
                continue;
            }
            
            $collectionData = json_decode(file_get_contents($collectionFile), true);
            if (!$collectionData) {
                continue;
            }
            
            // Create collection directory if it doesn't exist
            $targetCollectionPath = "$collectionsPath/$collectionSlug";
            if (!is_dir($targetCollectionPath)) {
                mkdir($targetCollectionPath, 0755, true);
                mkdir("$targetCollectionPath/entries", 0755, true);
            }
            
            // Save collection.json
            file_put_contents("$targetCollectionPath/collection.json", json_encode($collectionData, JSON_PRETTY_PRINT));
            
            // Save schema.json if exists
            if (file_exists($schemaFile)) {
                $schemaData = json_decode(file_get_contents($schemaFile), true);
                if ($schemaData) {
                    file_put_contents("$targetCollectionPath/schema.json", json_encode($schemaData, JSON_PRETTY_PRINT));
                }
            }
            
            $collectionsInstalled++;
            
            // Process entries
            $entriesDir = $collectionDir . '/entries';
            if (is_dir($entriesDir)) {
                $entryFiles = glob($entriesDir . '/*.json');
                
                foreach ($entryFiles as $entryFile) {
                    $entryData = json_decode(file_get_contents($entryFile), true);
                    if ($entryData) {
                        // Generate new entry ID
                        $entryId = uniqid();
                        if (isset($entryData['id'])) {
                            // Preserve original ID if desired, or use new one
                            // For demo content, we'll use new IDs to avoid conflicts
                        }
                        
                        // Add/update metadata
                        $entryData['id'] = $entryId;
                        if (!isset($entryData['created_at'])) {
                            $entryData['created_at'] = date('c');
                        }
                        if (!isset($entryData['updated_at'])) {
                            $entryData['updated_at'] = date('c');
                        }
                        
                        // Save entry
                        $targetEntryFile = "$targetCollectionPath/entries/$entryId.json";
                        file_put_contents($targetEntryFile, json_encode($entryData, JSON_PRETTY_PRINT));
                        $entriesInstalled++;
                    }
                }
            }
        }
    }
    
    return [
        'success' => true,
        'message' => 'Demo content installed successfully',
        'collections_installed' => $collectionsInstalled,
        'entries_installed' => $entriesInstalled
    ];
}

// Install demo template
function installDemoTemplate($templateId) {
    $demoTemplatesPath = __DIR__ . "/../demo-templates/$templateId";
    
    if (!is_dir($demoTemplatesPath)) {
        return ['success' => false, 'message' => 'Demo template not found'];
    }
    
    $manifestFile = $demoTemplatesPath . '/manifest.json';
    if (!file_exists($manifestFile)) {
        return ['success' => false, 'message' => 'Manifest file not found'];
    }
    
    $manifest = json_decode(file_get_contents($manifestFile), true);
    if (!$manifest) {
        return ['success' => false, 'message' => 'Invalid manifest file'];
    }
    
    $templatesPath = __DIR__ . '/../templates';
    
    // Ensure templates directory exists
    if (!is_dir($templatesPath)) {
        mkdir($templatesPath, 0755, true);
    }
    
    $templatesInstalled = 0;
    
    // Copy all template files
    $templateFiles = glob($demoTemplatesPath . '/*.html');
    $cssFiles = glob($demoTemplatesPath . '/*.css');
    $jsFiles = glob($demoTemplatesPath . '/*.js');
    $allFiles = array_merge($templateFiles, $cssFiles, $jsFiles);
    
    foreach ($allFiles as $sourceFile) {
        $filename = basename($sourceFile);
        // Skip manifest
        if ($filename === 'manifest.json') {
            continue;
        }
        
        $targetFile = "$templatesPath/$filename";
        if (copy($sourceFile, $targetFile)) {
            $templatesInstalled++;
        }
    }
    
    // Copy subdirectories if they exist
    $subdirs = ['css', 'js', 'assets'];
    foreach ($subdirs as $subdir) {
        $sourceSubdir = "$demoTemplatesPath/$subdir";
        if (is_dir($sourceSubdir)) {
            $targetSubdir = "$templatesPath/$subdir";
            if (!is_dir($targetSubdir)) {
                mkdir($targetSubdir, 0755, true);
            }
            
            $files = glob("$sourceSubdir/*");
            foreach ($files as $file) {
                if (is_file($file)) {
                    $targetFile = "$targetSubdir/" . basename($file);
                    copy($file, $targetFile);
                }
            }
        }
    }
    
    return [
        'success' => true,
        'message' => 'Demo template installed successfully',
        'templates_installed' => $templatesInstalled
    ];
}

switch ($action) {
    case 'list_content':
        // List available demo content packs
        $packs = listDemoContentPacks();
        echo json_encode([
            'status' => 'ok',
            'data' => ['packs' => $packs]
        ]);
        exit;
        
    case 'list_templates':
        // List available demo templates
        $templates = listDemoTemplates();
        echo json_encode([
            'status' => 'ok',
            'data' => ['templates' => $templates]
        ]);
        exit;
        
    case 'install_content':
        // Install demo content pack
        $packId = $input['pack_id'] ?? '';
        if (empty($packId)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Pack ID required']);
            exit;
        }
        
        $result = installDemoContentPack($packId);
        
        if ($result['success']) {
            // Update onboarding progress
            require_once __DIR__ . '/onboarding.php';
            $userId = $_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown';
            saveOnboardingProgress($userId, [
                'demo_content_installed' => true,
                'demo_content_pack' => $packId
            ]);
            
            echo json_encode([
                'status' => 'ok',
                'message' => $result['message'],
                'data' => [
                    'collections_installed' => $result['collections_installed'],
                    'entries_installed' => $result['entries_installed']
                ]
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => $result['message']
            ]);
        }
        exit;
        
    case 'install_template':
        // Install demo template
        $templateId = $input['template_id'] ?? '';
        if (empty($templateId)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Template ID required']);
            exit;
        }
        
        $result = installDemoTemplate($templateId);
        
        if ($result['success']) {
            // Update onboarding progress
            require_once __DIR__ . '/onboarding.php';
            $userId = $_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown';
            saveOnboardingProgress($userId, [
                'demo_template_installed' => true,
                'demo_template_id' => $templateId
            ]);
            
            echo json_encode([
                'status' => 'ok',
                'message' => $result['message'],
                'data' => [
                    'templates_installed' => $result['templates_installed']
                ]
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => $result['message']
            ]);
        }
        exit;
        
    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        exit;
}

