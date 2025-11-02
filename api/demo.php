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

$action = trim($_GET['action'] ?? $_POST['action'] ?? '');
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Debug logging (can be removed later)
if (empty($action)) {
    error_log("Demo API called without action. GET: " . json_encode($_GET) . ", POST: " . json_encode($_POST));
}

// Helper to list available demo websites
function listDemoWebsites() {
    $demoWebsitesPath = __DIR__ . '/../demo-websites';
    $websites = [];
    
    if (!is_dir($demoWebsitesPath)) {
        return $websites;
    }
    
    $dirs = glob($demoWebsitesPath . '/*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $websiteName = basename($dir);
        $manifestFile = $dir . '/manifest.json';
        
        if (file_exists($manifestFile)) {
            $manifest = json_decode(file_get_contents($manifestFile), true);
            if ($manifest) {
                $websites[] = [
                    'id' => $websiteName,
                    'name' => $manifest['name'] ?? $websiteName,
                    'description' => $manifest['description'] ?? '',
                    'preview_image' => $manifest['preview_image'] ?? null,
                    'collections_count' => $manifest['collections_count'] ?? 0,
                    'entries_count' => $manifest['entries_count'] ?? 0,
                    'has_frontend' => $manifest['has_frontend'] ?? false,
                    'version' => $manifest['version'] ?? '1.0.0'
                ];
            }
        }
    }
    
    return $websites;
}

// Legacy function for backward compatibility
function listDemoContentPacks() {
    return listDemoWebsites();
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

// Install demo website (content + frontend together)
function installDemoWebsite($websiteId) {
    // Normalize website ID (remove any quotes or whitespace)
    $websiteId = trim($websiteId, " \t\n\r\0\x0B\"'");
    $demoWebsitePath = __DIR__ . "/../demo-websites/$websiteId";
    
    // Use realpath to resolve any path issues
    $resolvedPath = realpath($demoWebsitePath);
    
    if (!$resolvedPath || !is_dir($resolvedPath)) {
        error_log("Demo website not found - ID: '$websiteId', Path: '$demoWebsitePath', Resolved: " . ($resolvedPath ?: 'NOT FOUND'));
        return ['success' => false, 'message' => 'Demo website not found: ' . $websiteId];
    }
    
    // Use resolved path for all operations
    $demoWebsitePath = $resolvedPath;
    
    $manifestFile = $demoWebsitePath . '/manifest.json';
    if (!file_exists($manifestFile)) {
        return ['success' => false, 'message' => 'Manifest file not found'];
    }
    
    $manifest = json_decode(file_get_contents($manifestFile), true);
    if (!$manifest) {
        return ['success' => false, 'message' => 'Invalid manifest file'];
    }
    
    // Install content (collections)
    $collectionsPath = __DIR__ . '/../content/collections';
    if (!is_dir($collectionsPath)) {
        mkdir($collectionsPath, 0755, true);
    }
    
    $collectionsInstalled = 0;
    $entriesInstalled = 0;
    
    // Process collections from demo website
    $collectionsDir = $demoWebsitePath . '/collections';
    if (is_dir($collectionsDir)) {
        $collectionDirs = glob($collectionsDir . '/*', GLOB_ONLYDIR);
        
        foreach ($collectionDirs as $collectionDir) {
            $collectionSlug = basename($collectionDir);
            
            $collectionFile = $collectionDir . '/collection.json';
            $schemaFile = $collectionDir . '/schema.json';
            
            if (!file_exists($collectionFile)) {
                continue;
            }
            
            $collectionData = json_decode(file_get_contents($collectionFile), true);
            if (!$collectionData) {
                continue;
            }
            
            $targetCollectionPath = "$collectionsPath/$collectionSlug";
            if (!is_dir($targetCollectionPath)) {
                mkdir($targetCollectionPath, 0755, true);
                mkdir("$targetCollectionPath/entries", 0755, true);
            }
            
            file_put_contents("$targetCollectionPath/collection.json", json_encode($collectionData, JSON_PRETTY_PRINT));
            
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
                        $entryId = uniqid();
                        $entryData['id'] = $entryId;
                        if (!isset($entryData['created_at'])) {
                            $entryData['created_at'] = date('c');
                        }
                        if (!isset($entryData['updated_at'])) {
                            $entryData['updated_at'] = date('c');
                        }
                        
                        $targetEntryFile = "$targetCollectionPath/entries/$entryId.json";
                        file_put_contents($targetEntryFile, json_encode($entryData, JSON_PRETTY_PRINT));
                        $entriesInstalled++;
                    }
                }
            }
        }
    }
    
    // Install frontend website if it exists
    $websiteDir = $demoWebsitePath . '/website';
    $frontendInstalled = false;
    $websitePath = __DIR__ . '/../demo-website-' . $websiteId;
    
    if (is_dir($websiteDir)) {
        if (!is_dir($websitePath)) {
            mkdir($websitePath, 0755, true);
        }
        
        // Copy all website files
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($websiteDir),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($files as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($websiteDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $targetPath = $websitePath . DIRECTORY_SEPARATOR . $relativePath;
                $targetDir = dirname($targetPath);
                
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                
                copy($file->getPathname(), $targetPath);
            }
        }
        
        $frontendInstalled = true;
    }
    
    return [
        'success' => true,
        'message' => 'Demo website installed successfully',
        'collections_installed' => $collectionsInstalled,
        'entries_installed' => $entriesInstalled,
        'frontend_installed' => $frontendInstalled,
        'website_path' => $frontendInstalled ? 'demo-website-' . $websiteId : null
    ];
}

// Legacy function for backward compatibility
function installDemoTemplate($templateId) {
    // Try to install as demo website first
    $result = installDemoWebsite($templateId);
    if ($result['success']) {
        return [
            'success' => true,
            'message' => $result['message'],
            'templates_installed' => $result['frontend_installed'] ? 1 : 0
        ];
    }
    
    // Fallback to old template system
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
    
    if (!is_dir($templatesPath)) {
        mkdir($templatesPath, 0755, true);
    }
    
    $templatesInstalled = 0;
    
    $templateFiles = glob($demoTemplatesPath . '/*.html');
    $cssFiles = glob($demoTemplatesPath . '/*.css');
    $jsFiles = glob($demoTemplatesPath . '/*.js');
    $allFiles = array_merge($templateFiles, $cssFiles, $jsFiles);
    
    foreach ($allFiles as $sourceFile) {
        $filename = basename($sourceFile);
        if ($filename === 'manifest.json') {
            continue;
        }
        
        $targetFile = "$templatesPath/$filename";
        if (copy($sourceFile, $targetFile)) {
            $templatesInstalled++;
        }
    }
    
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
    case 'list_websites':
        // List available demo websites
        $websites = listDemoWebsites();
        echo json_encode([
            'status' => 'ok',
            'data' => ['websites' => $websites, 'packs' => $websites] // Legacy compatibility
        ]);
        exit;
        
    case 'list_templates':
        // List available demo templates (legacy)
        $templates = listDemoTemplates();
        echo json_encode([
            'status' => 'ok',
            'data' => ['templates' => $templates]
        ]);
        exit;
        
    case 'install_website':
        // Install demo website (content + frontend together)
        $websiteId = $input['website_id'] ?? '';
        if (empty($websiteId)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Website ID required']);
            exit;
        }
        
        $result = installDemoWebsite($websiteId);
        
        if ($result['success']) {
            // Update onboarding progress
            require_once __DIR__ . '/onboarding.php';
            $userId = $_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown';
            saveOnboardingProgress($userId, [
                'demo_content_installed' => true,
                'demo_template_installed' => true,
                'demo_website_installed' => true,
                'demo_website_id' => $websiteId
            ]);
            
            echo json_encode([
                'status' => 'ok',
                'message' => $result['message'],
                'data' => [
                    'collections_installed' => $result['collections_installed'],
                    'entries_installed' => $result['entries_installed'],
                    'frontend_installed' => $result['frontend_installed'],
                    'website_path' => $result['website_path']
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

