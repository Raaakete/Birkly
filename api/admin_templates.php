<?php
/**
 * Admin Templates API
 * Serves templates and data for the admin panel
 */

require_once 'storage.php';

// Load fieldtype base and simple autoloader so fieldtypes can be instantiated for list rendering
$baseFieldPath = __DIR__ . '/../fieldtypes/BaseField.php';
if (file_exists($baseFieldPath)) {
    require_once $baseFieldPath;
}
spl_autoload_register(function ($class) {
    if (strpos($class, 'HaruCMS\\FieldTypes\\') === 0) {
        $suffix = str_replace('HaruCMS\\FieldTypes\\', '', $class); // e.g., Media\Media
        $parts = explode('\\', $suffix);
        $leaf = end($parts);
        $dir = __DIR__ . '/../fieldtypes/' . $leaf;
        $file = $dir . '/' . $leaf . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Simple database connection
function getDatabase() {
    static $pdo = null;
    if ($pdo === null) {
        $dbPath = __DIR__ . '/../database/harucms.db';
        $pdo = new PDO("sqlite:$dbPath");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $pdo;
}

// Only set headers if this file is being called directly (not included)
// When included via admin/index.php, headers are already sent, so skip
if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_page_data':
        $page = $_GET['page'] ?? 'dashboard';
        $data = getPageData($page);
        echo json_encode(['status' => 'ok', 'data' => $data]);
        break;
        
    case 'list_templates':
        $data = getTemplatesData();
        echo json_encode(['status' => 'ok', 'data' => $data]);
        break;
        
    case 'create_template':
        $data = createTemplate();
        echo json_encode($data);
        break;
        
    case 'save_template':
        $data = saveTemplate();
        echo json_encode($data);
        break;
        
    case 'delete_template':
        $data = deleteTemplate();
        echo json_encode($data);
        break;
        
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}

function getPageData($page) {
    switch ($page) {
        case 'dashboard':
            return getDashboardData();
        case 'content':
            return getContentData();
        case 'media':
            return getMediaData();
        case 'settings':
            return getSettingsData();
        case 'collection-editor':
            return getCollectionEditorData();
        case 'entry-editor':
            return getEntryEditorData(true);
        case 'entries':
            return getEntriesData();
        case 'templates':
            return getTemplatesData();
        case 'users':
            return getUsersData();
        case 'roles':
            return getRolesData();
        case 'onboarding':
            return getOnboardingData();
        default:
            return ['error' => 'Page not found'];
    }
}

function getDashboardData() {
    // Include onboarding to get user status
    $GLOBALS['__HARU_INTERNAL_CALL'] = true;
    require_once __DIR__ . '/onboarding.php';
    
    // Get current user
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $userId = $_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown';
    $onboardingStatus = getOnboardingStatus($userId);
    
    // Get collections
    $collectionsPath = __DIR__ . '/../content/collections';
    $collections = [];
    $totalEntries = 0;
    $published = 0;
    $drafts = 0;
    
    if (is_dir($collectionsPath)) {
        $collectionDirs = glob($collectionsPath . '/*', GLOB_ONLYDIR);
        foreach ($collectionDirs as $dir) {
            $collectionName = basename($dir);
            $collectionFile = $dir . '/collection.json';
            
            if (file_exists($collectionFile)) {
                $collectionData = json_decode(file_get_contents($collectionFile), true);
                $entriesDir = $dir . '/entries';
                $entriesCount = is_dir($entriesDir) ? count(glob($entriesDir . '/*.json')) : 0;
                
                $collections[] = [
                    'title' => $collectionData['name'] ?? $collectionName,
                    'slug' => $collectionName,
                    'entries_count' => $entriesCount
                ];
                
                $totalEntries += $entriesCount;
            }
        }
    }
    
    // Get recent activity (simplified)
    $recentActivity = [
        [
            'message' => 'System initialized',
            'created_at' => date('Y-m-d H:i:s')
        ]
    ];
    
    // Get recent collections (last 5)
    $recentCollections = array_slice($collections, 0, 5);
    
    // Build onboarding checklist
    $checklist = [
        [
            'id' => 'setup_complete',
            'label' => 'Complete site setup',
            'completed' => $onboardingStatus['setup_wizard_completed'] ?? false,
            'link' => '/admin/index.php?page=onboarding'
        ],
        [
            'id' => 'first_collection',
            'label' => 'Create your first collection',
            'completed' => count($collections) > 0,
            'link' => '/admin/index.php?page=content'
        ],
        [
            'id' => 'first_entry',
            'label' => 'Add your first entry',
            'completed' => $totalEntries > 0,
            'link' => count($collections) > 0 ? '/admin/index.php?page=entry-editor&collection=' . $collections[0]['slug'] : '/admin/index.php?page=content'
        ],
        [
            'id' => 'upload_media',
            'label' => 'Upload media files',
            'completed' => false, // Could check media directory
            'link' => '/admin/index.php?page=media'
        ],
        [
            'id' => 'configure_settings',
            'label' => 'Configure site settings',
            'completed' => false, // Could check if settings were customized
            'link' => '/admin/index.php?page=settings'
        ],
        [
            'id' => 'create_template',
            'label' => 'Set up your first template (optional)',
            'completed' => false,
            'link' => '/admin/index.php?page=templates'
        ]
    ];
    
    $checklistCompleted = 0;
    foreach ($checklist as $item) {
        if ($item['completed']) {
            $checklistCompleted++;
        }
    }
    
    return [
        'stats' => [
            'collections' => count($collections),
            'total_entries' => $totalEntries,
            'published' => $published,
            'drafts' => $drafts
        ],
        'recent_activity' => $recentActivity,
        'recent_collections' => $recentCollections,
        'onboarding' => [
            'completed' => $onboardingStatus['completed'] ?? false,
            'is_first_time' => $onboardingStatus['is_first_time'] ?? false,
            'checklist' => $checklist,
            'checklist_progress' => [
                'completed' => $checklistCompleted,
                'total' => count($checklist)
            ]
        ]
    ];
}

function getContentData() {
    // Get collections for content page
    $collectionsPath = __DIR__ . '/../content/collections';
    $collections = [];
    
    if (is_dir($collectionsPath)) {
        $collectionDirs = glob($collectionsPath . '/*', GLOB_ONLYDIR);
        foreach ($collectionDirs as $dir) {
            $collectionName = basename($dir);
            $collectionFile = $dir . '/collection.json';
            
            if (file_exists($collectionFile)) {
                $collectionData = json_decode(file_get_contents($collectionFile), true);
                $entriesDir = $dir . '/entries';
                $entriesCount = is_dir($entriesDir) ? count(glob($entriesDir . '/*.json')) : 0;
                
                $collections[] = [
                    'id' => $collectionData['id'] ?? $collectionData['title'] ?? null,
                    'name' => $collectionData['name'] ?? $collectionData['title'] ?? $collectionName,
                    'slug' => $collectionName,
                    'description' => $collectionData['description'] ?? '',
                    'entries_count' => $entriesCount
                ];
            }
        }
    }
    
    // Load groups from groups.json
    $groups = [];
    $groupsFile = $collectionsPath . '/groups.json';
    if (file_exists($groupsFile)) {
        $groupsData = json_decode(file_get_contents($groupsFile), true);
        if (is_array($groupsData)) {
            $groups = $groupsData;
        }
    }
    
    return [
        'collections' => $collections,
        'groups' => $groups
    ];
}

function getCollectionEditorData() {
    // Get collection data from URL parameters
    $collectionSlug = $_GET['collection'] ?? null;
    
    // Get available fieldtypes
    $fieldtypes = [];
    $fieldtypesDir = __DIR__ . "/../fieldtypes";
    if (is_dir($fieldtypesDir)) {
        $dirs = glob($fieldtypesDir . '/*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $fieldtypeName = basename($dir);
            // Convert directory name to lowercase for consistency with fieldtype registration
            $fieldtypeKey = strtolower($fieldtypeName);
            $fieldtypes[] = [
                'name' => $fieldtypeKey,
                'label' => ucfirst($fieldtypeName)
            ];
        }
    }
    
    // Debug: Log fieldtypes found
    error_log("Found " . count($fieldtypes) . " fieldtypes: " . implode(', ', array_column($fieldtypes, 'name')));
    
    if ($collectionSlug) {
        // Editing existing collection
        $collectionFile = __DIR__ . "/../content/collections/$collectionSlug/collection.json";
        $schemaFile = __DIR__ . "/../content/collections/$collectionSlug/schema.json";
        
        if (file_exists($collectionFile)) {
            $collectionData = json_decode(file_get_contents($collectionFile), true);
            $collectionData['slug'] = $collectionSlug;
            $collectionData['title'] = $collectionData['name'] ?? $collectionSlug;
            
            // Read fields from schema.json
            $fields = [];
            if (file_exists($schemaFile)) {
                $schemaData = json_decode(file_get_contents($schemaFile), true);
                if (isset($schemaData['fields'])) {
                    $fields = $schemaData['fields'];
                    // Add IDs to fields for easier reference
                    foreach ($fields as $index => $field) {
                        $fields[$index]['id'] = 'field-' . $index;
                    }
                }
            }
            
            return [
                'collection' => $collectionData,
                'fields' => $fields,
                'fieldtypes' => $fieldtypes
            ];
        }
    }
    
    // New collection
    return [
        'collection' => [
            'title' => '',
            'slug' => '',
            'description' => '',
            'template' => '',
            'sort' => 'created_desc'
        ],
        'fields' => [],
        'fieldtypes' => $fieldtypes
    ];
}

function getEntryEditorData($includeFieldHtml = false) {
    // Get collection and entry data from URL parameters
    $collectionSlug = $_GET['collection'] ?? null;
    $entryId = $_GET['entry'] ?? null;
    
    if (!$collectionSlug) {
        return ['error' => 'Collection parameter required'];
    }
    
    // Get collection data
    $collectionFile = __DIR__ . "/../content/collections/$collectionSlug/collection.json";
    if (!file_exists($collectionFile)) {
        return ['error' => 'Collection not found'];
    }
    
    $collectionData = json_decode(file_get_contents($collectionFile), true);
    $collectionData['slug'] = $collectionSlug;
    // Ensure 'name' field exists for consistency
    if (!isset($collectionData['name']) && isset($collectionData['title'])) {
        $collectionData['name'] = $collectionData['title'];
    }
    
    // Get fields from schema.json
    $fields = [];
    $schemaFile = __DIR__ . "/../content/collections/$collectionSlug/schema.json";
    if (file_exists($schemaFile)) {
        $schemaData = json_decode(file_get_contents($schemaFile), true);
        if (isset($schemaData['fields'])) {
            $fields = $schemaData['fields'];
            // Add id to each field
            foreach ($fields as $index => $field) {
                $fields[$index]['id'] = 'field-' . $index;
            }
        }
    }
    
    // Get all available fieldtypes
    $fieldtypes = [];
    $fieldtypesDir = __DIR__ . '/../fieldtypes';
    if (is_dir($fieldtypesDir)) {
        $fieldtypeDirs = glob($fieldtypesDir . '/*', GLOB_ONLYDIR);
        foreach ($fieldtypeDirs as $fieldtypeDir) {
            $fieldtypeName = basename($fieldtypeDir);
            $fieldtypes[] = [
                'name' => strtolower($fieldtypeName),
                'label' => $fieldtypeName
            ];
        }
    }
    
    // Get entry data if editing
    $entry = null;
    if ($entryId) {
        $entryFile = __DIR__ . "/../content/collections/$collectionSlug/entries/$entryId.json";
        if (file_exists($entryFile)) {
            $entry = json_decode(file_get_contents($entryFile), true);
            $entry['id'] = $entryId;
        }
    }
    
    $payload = [
        'collection' => $collectionData,
        'entry' => $entry ?? [
            'title' => '',
            'status' => 'draft',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ],
        'fields' => $fields,
        'fieldtypes' => $fieldtypes
    ];
    
    // Always add mandatory title field at the top (not from schema)
    // This ensures every entry has a title, regardless of collection schema
    $titleField = [
        'name' => 'title',
        'label' => 'Title',
        'type' => 'text',
        'required' => true,
        'id' => 'field-title'
    ];
    
    // Add title field HTML if including field HTML
    if ($includeFieldHtml) {
        require_once __DIR__ . '/../fieldtypes/BaseField.php';
        require_once __DIR__ . '/../fieldtypes/Text/Text.php';
        
        $titleValue = $payload['entry']['title'] ?? '';
        $titleFieldtype = new \HaruCMS\FieldTypes\Text\Text([
            'name' => 'title',
            'label' => 'Title',
            'value' => $titleValue,
            'required' => true,
            'help' => '',
            'group' => '',
            'options' => []
        ]);
        
        $titleField['html'] = $titleFieldtype->render();
    }
    
    // Prepend title field to fields array
    $payload['fields'] = array_merge([$titleField], $payload['fields']);

    // Optionally embed rendered field HTML for template-driven editor
    if ($includeFieldHtml && !empty($fields)) {
        require_once __DIR__ . '/../fieldtypes/BaseField.php';
        // Include fieldtypes API in internal mode to avoid emitting output
        $____prevInternal = $GLOBALS['__HARU_INTERNAL_CALL'] ?? null;
        $GLOBALS['__HARU_INTERNAL_CALL'] = true;
        require_once __DIR__ . '/admin_fieldtypes.php'; // Use the fieldtype loading functions
        if ($____prevInternal === null) {
            unset($GLOBALS['__HARU_INTERNAL_CALL']);
        } else {
            $GLOBALS['__HARU_INTERNAL_CALL'] = $____prevInternal;
        }
        
        $__fieldtypeCss = [];
        $__fieldtypeJs = [];
        foreach ($payload['fields'] as $idx => $f) {
            $type = $f['type'] ?? '';
            $name = $f['name'] ?? '';
            if (!$type || !$name) continue;
            
            // For multi-language entries, we need to get the value from the default language
            // The entry editor template will handle language-specific values
            $multilanguageEnabled = !empty($collectionData['multilanguage']['enabled']);
            $multilanguageLanguages = $collectionData['multilanguage']['languages'] ?? [];
            $defaultLang = !empty($multilanguageLanguages) ? $multilanguageLanguages[0] : 'en';
            
            $rawValue = ($payload['entry'][$name] ?? null);
            // If multi-language is enabled and value is not at top level, try default language
            if ($rawValue === null && $multilanguageEnabled && !empty($multilanguageLanguages) && isset($payload['entry'][$defaultLang][$name])) {
                $rawValue = $payload['entry'][$defaultLang][$name];
            }
            
            // Use the proper fieldtype loader from admin_fieldtypes.php
            $className = getFieldtypeClass($type);
            if ($className && class_exists($className)) {
                try {
                    // Process the value through processLoadedValue if method exists
                    $value = $rawValue;
                    if ($rawValue !== null) {
                        // Create a temporary instance to process the value
                        $tempFt = new $className([
                            'name' => $name,
                            'label' => $f['label'] ?? $name,
                            'value' => null,
                            'required' => false
                        ]);
                        if (method_exists($tempFt, 'processLoadedValue')) {
                            $value = $tempFt->processLoadedValue($rawValue);
                        }
                    } else {
                        // Use default value if no value exists
                        $tempFt = new $className([
                            'name' => $name,
                            'label' => $f['label'] ?? $name,
                            'value' => null,
                            'required' => false
                        ]);
                        if (method_exists($tempFt, 'getDefaultValue')) {
                            $value = $tempFt->getDefaultValue();
                        }
                    }
                    
                    $ft = new $className([
                        'name' => $name,
                        'label' => $f['label'] ?? $name,
                        'value' => $value,
                        'required' => $f['required'] ?? false,
                        'help' => $f['help_text'] ?? '',
                        'group' => $f['group'] ?? 'Content',
                        'options' => $f['options'] ?? []
                    ]);
                    
                    if (method_exists($ft, 'render')) {
                        // For editor form, use full field render
                        $renderedHtml = $ft->render();
                        
                        // Add data-fieldtype attribute to help with JS initialization
                        // Wrap the HTML or add attribute to the first input/textarea/select
                        $renderedHtml = preg_replace_callback(
                            '/<(input|textarea|select|div|span)([^>]*)>/i',
                            function($matches) use ($type) {
                                $tag = $matches[1];
                                $attrs = $matches[2];
                                // Only add if not already present
                                if (strpos($attrs, 'data-fieldtype') === false) {
                                    $attrs .= ' data-fieldtype="' . htmlspecialchars($type) . '"';
                                }
                                return "<{$tag}{$attrs}>";
                            },
                            $renderedHtml,
                            1 // Only replace first match
                        );
                        
                        $payload['fields'][$idx]['html'] = $renderedHtml;
                        
                        // Collect CSS
                        if (method_exists($ft, 'getCSS')) {
                            $css = (string)$ft->getCSS();
                            if ($css !== '' && !in_array($css, $__fieldtypeCss, true)) {
                                $__fieldtypeCss[] = $css;
                            }
                        }
                        
                        // Collect JavaScript
                        if (method_exists($ft, 'getJavaScript')) {
                            $js = (string)$ft->getJavaScript();
                            if ($js !== '' && !in_array($js, $__fieldtypeJs, true)) {
                                $__fieldtypeJs[] = $js;
                            }
                        }
                    } else {
                        $payload['fields'][$idx]['html'] = '<div class="field-error">Fieldtype "' . htmlspecialchars($type) . '" missing render() method</div>';
                    }
                } catch (\Throwable $e) {
                    error_log("Fieldtype rendering error for {$type}: " . $e->getMessage());
                    $payload['fields'][$idx]['html'] = '<div class="field-error">Fieldtype "' . htmlspecialchars($type) . '" failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            } else {
                $payload['fields'][$idx]['html'] = '<div class="field-error">Fieldtype "' . htmlspecialchars($type) . '" not found</div>';
            }
        }
        
        if (!empty($__fieldtypeCss) || !empty($__fieldtypeJs)) {
            // Write assets to temp files to avoid inline </script> parsing issues
            $tempDir = __DIR__ . '/../temp';
            if (!is_dir($tempDir)) { @mkdir($tempDir, 0755, true); }
            $hashBase = implode('\n', $__fieldtypeCss) . '\n' . implode('\n', $__fieldtypeJs);
            $hash = substr(sha1($hashBase), 0, 12);
            $cssPath = $tempDir . "/fieldtypes-{$hash}.css";
            if (!empty($__fieldtypeCss)) { @file_put_contents($cssPath, implode("\n\n", $__fieldtypeCss)); }

            // Write each JS chunk as a separate file to isolate failures
            $jsFiles = [];
            if (!empty($__fieldtypeJs)) {
                foreach ($__fieldtypeJs as $i => $chunk) {
                    $file = $tempDir . "/fieldtypes-{$hash}-{$i}.js";
                    $wrapped = ";(function(){\n" . $chunk . "\n})();";
                    @file_put_contents($file, "/* HaruCMS fieldtype chunk {$i} */\n'use strict';\n" . $wrapped . "\n");
                    $jsFiles[] = "/temp/fieldtypes-{$hash}-{$i}.js";
                }
            }
            $payload['fieldtype_assets'] = [
                'css' => !empty($__fieldtypeCss) ? "/temp/fieldtypes-{$hash}.css" : '',
                'js_files'  => $jsFiles
            ];
        }
    }

    return $payload;
}

function getEntriesData() {
    // Get collection data from URL parameters
    $collectionSlug = $_GET['collection'] ?? null;
    
    if (!$collectionSlug) {
        return ['error' => 'Collection parameter required'];
    }
    
    // Get collection data
    $collectionFile = __DIR__ . "/../content/collections/$collectionSlug/collection.json";
    if (!file_exists($collectionFile)) {
        return ['error' => 'Collection not found'];
    }
    
    $collectionData = json_decode(file_get_contents($collectionFile), true);
    $collectionData['slug'] = $collectionSlug;
    
    // Get entries
    $entries = [];
    $entriesDir = __DIR__ . "/../content/collections/$collectionSlug/entries";
    if (is_dir($entriesDir)) {
        $entryFiles = glob($entriesDir . '/*.json');
        foreach ($entryFiles as $entryFile) {
            $entryData = json_decode(file_get_contents($entryFile), true);
            $entryData['id'] = basename($entryFile, '.json');
            $entries[] = $entryData;
        }
    }

    // Read field schema for list cell rendering
    $fields = [];
    $schemaFile = __DIR__ . "/../content/collections/$collectionSlug/schema.json";
    if (file_exists($schemaFile)) {
        $schemaData = json_decode(file_get_contents($schemaFile), true);
        if (isset($schemaData['fields']) && is_array($schemaData['fields'])) {
            $fields = $schemaData['fields'];
        }
    }

    // Build per-entry list cells using fieldtypes (if available)
    $entriesWithCells = [];
    // Build a width map per fieldtype (category)
    $typeWidths = [];
    foreach ($fields as $field) {
        $name = $field['name'] ?? null;
        $type = $field['type'] ?? null;
        if (!$name || !$type) continue;
        if (isset($typeWidths[$type])) continue;
        $width = null;
        try {
            $ftName = ucfirst(strtolower($type));
            $class = "HaruCMS\\FieldTypes\\$ftName\\$ftName";
            $file = __DIR__ . "/../fieldtypes/$ftName/$ftName.php";
            if (!class_exists($class) && file_exists($file)) {
                require_once $file;
            }
            if (class_exists($class)) {
                $ft = new $class(['name' => $name]);
                if (method_exists($ft, 'getListColumnWidth')) {
                    $width = $ft->getListColumnWidth();
                }
            }
        } catch (\Throwable $e) {
            $width = null;
        }
        $typeWidths[$type] = $width ?: '240px';
    }

    // Attach width to field definitions for header rendering
    foreach ($fields as $idx => $field) {
        $ftype = $field['type'] ?? '';
        $fields[$idx]['width'] = $typeWidths[$ftype] ?? '240px';
    }
    // Provide a shared CSS var for field column width (from the first fieldtype or a default)
    $preferredWidth = '240px';
    if (!empty($fields)) { $preferredWidth = $fields[0]['width'] ?? '240px'; }

    // Check if collection has multi-language enabled
    $multilanguageEnabled = !empty($collectionData['multilanguage']['enabled']);
    $multilanguageLanguages = $collectionData['multilanguage']['languages'] ?? [];
    $defaultLanguage = !empty($multilanguageLanguages) ? $multilanguageLanguages[0] : 'en';
    
    foreach ($entries as $entry) {
        $cells = [];
        // Title and status are mandatory in UI; ensure presence
        // For multi-language entries, extract title from language section
        $title = $entry['title'] ?? '';
        if (empty($title) && $multilanguageEnabled && !empty($multilanguageLanguages)) {
            // Try to get title from default language
            if (isset($entry[$defaultLanguage]['title'])) {
                $title = $entry[$defaultLanguage]['title'];
            } else {
                // Try any available language
                foreach ($multilanguageLanguages as $lang) {
                    if (isset($entry[$lang]['title']) && !empty($entry[$lang]['title'])) {
                        $title = $entry[$lang]['title'];
                        break;
                    }
                }
            }
        }
        // Fallback to key or slug if no title found
        if (empty($title)) {
            $title = $entry['key'] ?? $entry['slug'] ?? 'Untitled';
        }
        $cells['__title'] = $title;
        $cells['__status'] = $entry['status'] ?? 'draft';

        $entryFieldPairs = [];
        foreach ($fields as $field) {
            $name = $field['name'] ?? null;
            $type = $field['type'] ?? null;
            if (!$name || !$type) continue;

            // For multi-language entries, extract value from language section
            $value = $entry[$name] ?? ($field['default'] ?? null);
            if ($value === null && $multilanguageEnabled && !empty($multilanguageLanguages)) {
                // Try to get value from default language
                if (isset($entry[$defaultLanguage][$name])) {
                    $value = $entry[$defaultLanguage][$name];
                } else {
                    // Try any available language
                    foreach ($multilanguageLanguages as $lang) {
                        if (isset($entry[$lang][$name])) {
                            $value = $entry[$lang][$name];
                            break;
                        }
                    }
                }
            }

            // Try to render via fieldtype class if present
            $rendered = null;
            try {
                $fieldtypeClass = null;
                $ftName = ucfirst(strtolower($type));
                $candidate = "HaruCMS\\FieldTypes\\$ftName\\$ftName";
                if (class_exists($candidate)) {
                    $fieldtypeClass = $candidate;
                }

                if ($fieldtypeClass) {
                    /** @var \HaruCMS\FieldTypes\BaseField $ft */
                    $ft = new $fieldtypeClass([
                        'name' => $name,
                        'label' => $field['label'] ?? $name,
                        'value' => $value,
                        'required' => $field['required'] ?? false,
                        'help' => $field['help_text'] ?? '',
                        'group' => $field['group'] ?? 'Content',
                    ]);
                    if (method_exists($ft, 'renderListCell')) {
                        $rendered = $ft->renderListCell($value, ['entry' => $entry, 'field' => $field]);
                    }
                }
            } catch (\Throwable $e) {
                // Fallback to a simple string representation on error
                $rendered = null;
            }

            if ($rendered === null) {
                // Generic fallback
                if (is_array($value)) {
                    $rendered = '<span class="ft-list-badge">' . count($value) . ' items</span>';
                } else if (is_object($value)) {
                    $rendered = '<span class="ft-list-badge">object</span>';
                } else {
                    $text = htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
                    if ($text === '') $text = '<span class="ft-list-empty">–</span>';
                    $rendered = '<span class="ft-list-text">' . $text . '</span>';
                }
            }

            // Provide both raw text and html variants for templating convenience
            $cells[$name] = strip_tags($rendered);
            $cells[$name . '_html'] = $rendered;

            $entryFieldPairs[] = [
                'name' => $name,
                'label' => $field['label'] ?? $name,
                'type' => $type,
                'width' => $typeWidths[$type] ?? '240px',
                'value' => strip_tags($rendered),
                'value_html' => $rendered
            ];
        }

        // Compose combined fields HTML for list view (ordered by schema)
        $combined = '';
        foreach ($fields as $field) {
            $fname = $field['name'] ?? null;
            if (!$fname) continue;
            $label = htmlspecialchars($field['label'] ?? $fname, ENT_QUOTES, 'UTF-8');
            $valueHtml = $cells[$fname . '_html'] ?? '<span class="ft-list-empty">–</span>';
            $combined .= '<div class="field-chip"><span class="field-label">' . $label . '</span> <span class="field-value">' . $valueHtml . '</span></div>';
        }
        $cells['__fields_html'] = $combined;

        $entryWithCells = $entry;
        $entryWithCells['__cells'] = $cells; // legacy access
        $entryWithCells['entryfields'] = $entryFieldPairs;
        // Flat ordered cells for rendering without dynamic lookups
        $entryWithCells['row_cells'] = array_map(function ($pair) {
            return [
                'width' => $pair['width'] ?? '240px',
                'html' => $pair['value_html'] ?? '<span class="ft-list-empty">–</span>'
            ];
        }, $entryFieldPairs);
        // Also provide pre-rendered HTML to avoid nested templating issues
        $rowCellsHtml = '';
        foreach ($entryFieldPairs as $pair) {
            $w = htmlspecialchars($pair['width'] ?? '240px', ENT_QUOTES, 'UTF-8');
            $h = $pair['value_html'] ?? '<span class="ft-list-empty">–</span>';
            $rowCellsHtml .= '<div class="cell cell-field" style="--col-w: '.$w.'">'.$h.'</div>';
        }
        $entryWithCells['row_cells_html'] = $rowCellsHtml;
        $entriesWithCells[] = $entryWithCells;
    }

    return [
        'collection' => $collectionData,
        'fields' => $fields,
        'entries' => $entriesWithCells,
        'list_column_width' => $preferredWidth
    ];
}

function getMediaData() {
    // Get media files
    $mediaPath = __DIR__ . '/../media';
    $mediaFiles = [];
    
    if (is_dir($mediaPath)) {
        $files = glob($mediaPath . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                $filename = basename($file);
                $extension = pathinfo($filename, PATHINFO_EXTENSION);
                $size = filesize($file);
                
                $mediaFiles[] = [
                    'name' => $filename,
                    'size' => $size,
                    'type' => $extension,
                    'url' => '/media/' . $filename
                ];
            }
        }
    }
    
    return [
        'files' => $mediaFiles
    ];
}

function getSettingsData() {
    // Get settings data
    $settingsPath = __DIR__ . '/../settings';
    $settings = [
        'site' => [
            'title' => 'HaruCMS',
            'description' => 'A headless CMS'
        ],
        'admin' => [
            'email' => 'admin@example.com'
        ],
        'security' => [
            'session_timeout' => 3600,
            'max_login_attempts' => 5,
            'enable_csrf' => true
        ]
    ];
    
    if (is_dir($settingsPath)) {
        $settingFiles = glob($settingsPath . '/*.json');
        foreach ($settingFiles as $file) {
            $category = basename($file, '.json');
            $fileData = json_decode(file_get_contents($file), true);
            if ($fileData) {
                $settings[$category] = array_merge($settings[$category] ?? [], $fileData);
            }
        }
    }
    
    return [
        'settings' => $settings,
        'users' => getUsersData()['users'],
        'roles' => getRolesData()['roles']
    ];
}

function getTemplatesData() {
    $templatesPath = __DIR__ . '/../templates';
    $templates = [];
    
    // Create templates directory if it doesn't exist
    if (!is_dir($templatesPath)) {
        mkdir($templatesPath, 0755, true);
    }
    
    // Get template files
    $templateFiles = glob($templatesPath . '/*.html');
    foreach ($templateFiles as $file) {
        $filename = basename($file, '.html');
        $content = file_get_contents($file);
        $stat = stat($file);
        
        $templates[] = [
            'id' => $filename,
            'name' => ucwords(str_replace(['-', '_'], ' ', $filename)),
            'slug' => $filename,
            'description' => 'Template for ' . $filename,
            'type' => 'custom',
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s', $stat['ctime']),
            'updated_at' => date('Y-m-d H:i:s', $stat['mtime'])
        ];
    }
    
    // Add default templates if none exist
    if (empty($templates)) {
        $defaultTemplates = [
            [
                'id' => 'home',
                'name' => 'Homepage Template',
                'slug' => 'home',
                'description' => 'Default homepage template',
                'type' => 'home',
                'content' => getDefaultHomeTemplate(),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 'page',
                'name' => 'Page Template',
                'slug' => 'page',
                'description' => 'Default page template',
                'type' => 'page',
                'content' => getDefaultPageTemplate(),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 'post',
                'name' => 'Post Template',
                'slug' => 'post',
                'description' => 'Default blog post template',
                'type' => 'post',
                'content' => getDefaultPostTemplate(),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ];
        
        // Create default template files
        foreach ($defaultTemplates as $template) {
            $filePath = $templatesPath . '/' . $template['slug'] . '.html';
            file_put_contents($filePath, $template['content']);
        }
        
        $templates = $defaultTemplates;
    }
    
    return [
        'templates' => $templates
    ];
}

function createTemplate() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['status' => 'error', 'message' => 'Method not allowed'];
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $name = $input['name'] ?? '';
    $slug = $input['slug'] ?? '';
    $description = $input['description'] ?? '';
    $type = $input['type'] ?? 'custom';
    
    if (empty($name) || empty($slug)) {
        return ['status' => 'error', 'message' => 'Name and slug are required'];
    }
    
    $templatesPath = __DIR__ . '/../templates';
    if (!is_dir($templatesPath)) {
        mkdir($templatesPath, 0755, true);
    }
    
    $filePath = $templatesPath . '/' . $slug . '.html';
    
    if (file_exists($filePath)) {
        return ['status' => 'error', 'message' => 'Template with this slug already exists'];
    }
    
    $content = getDefaultTemplateContent($type);
    
    if (file_put_contents($filePath, $content) === false) {
        return ['status' => 'error', 'message' => 'Failed to create template file'];
    }
    
    return [
        'status' => 'ok',
        'message' => 'Template created successfully',
        'data' => [
            'id' => $slug,
            'name' => $name,
            'slug' => $slug
        ]
    ];
}

function saveTemplate() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['status' => 'error', 'message' => 'Method not allowed'];
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $content = $input['content'] ?? '';
    $templateId = $input['template_id'] ?? '';
    
    if (empty($templateId)) {
        return ['status' => 'error', 'message' => 'Template ID is required'];
    }
    
    $templatesPath = __DIR__ . '/../templates';
    $filePath = $templatesPath . '/' . $templateId . '.html';
    
    if (!file_exists($filePath)) {
        return ['status' => 'error', 'message' => 'Template not found'];
    }
    
    if (file_put_contents($filePath, $content) === false) {
        return ['status' => 'error', 'message' => 'Failed to save template'];
    }
    
    return [
        'status' => 'ok',
        'message' => 'Template saved successfully'
    ];
}

function deleteTemplate() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['status' => 'error', 'message' => 'Method not allowed'];
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $templateId = $input['template_id'] ?? '';
    
    if (empty($templateId)) {
        return ['status' => 'error', 'message' => 'Template ID is required'];
    }
    
    $templatesPath = __DIR__ . '/../templates';
    $filePath = $templatesPath . '/' . $templateId . '.html';
    
    if (!file_exists($filePath)) {
        return ['status' => 'error', 'message' => 'Template not found'];
    }
    
    if (!unlink($filePath)) {
        return ['status' => 'error', 'message' => 'Failed to delete template'];
    }
    
    return [
        'status' => 'ok',
        'message' => 'Template deleted successfully'
    ];
}

function getDefaultTemplateContent($type) {
    switch ($type) {
        case 'home':
            return getDefaultHomeTemplate();
        case 'page':
            return getDefaultPageTemplate();
        case 'post':
            return getDefaultPostTemplate();
        case 'list':
            return getDefaultListTemplate();
        default:
            return getDefaultPageTemplate();
    }
}

function getDefaultHomeTemplate() {
    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ site.title }}</title>
    <meta name="description" content="{{ site.description }}">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; line-height: 1.6; margin: 0; padding: 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .header { background: #f8f9fa; padding: 1rem 0; border-bottom: 1px solid #e9ecef; }
        .nav { display: flex; gap: 2rem; }
        .nav a { text-decoration: none; color: #333; }
        .nav a:hover { color: #007bff; }
        .hero { padding: 4rem 0; text-align: center; }
        .blog-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin: 3rem 0; }
        .blog-card { border: 1px solid #e9ecef; border-radius: 8px; padding: 1.5rem; }
        .blog-card h3 { margin-top: 0; }
        .blog-card .date { color: #6c757d; font-size: 0.9rem; }
        .footer { background: #f8f9fa; padding: 2rem 0; margin-top: 3rem; text-align: center; }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <a href="/">Home</a>
                <a href="/blog">Blog</a>
                <a href="/about">About</a>
            </nav>
        </div>
    </header>
    
    <main>
        <div class="hero">
            <div class="container">
                <h1>{{ page.title }}</h1>
                <div>{{ page.content }}</div>
            </div>
        </div>
        
        <div class="container">
            <h2>Latest Blog Posts</h2>
            <div class="blog-grid">
                {% for entry in blog_entries %}
                    <article class="blog-card">
                        <h3><a href="/blog/{{ entry.slug }}">{{ entry.title }}</a></h3>
                        <div class="date">{{ entry.created_at }}</div>
                        <p>{{ entry.excerpt }}</p>
                    </article>
                {% endfor %}
            </div>
        </div>
    </main>
    
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 {{ site.title }}. Built with HaruCMS.</p>
        </div>
    </footer>
</body>
</html>';
}

function getDefaultPageTemplate() {
    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ page.title }} - {{ site.title }}</title>
    <meta name="description" content="{{ page.meta_description }}">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; line-height: 1.6; margin: 0; padding: 0; }
        .container { max-width: 800px; margin: 0 auto; padding: 0 20px; }
        .header { background: #f8f9fa; padding: 1rem 0; border-bottom: 1px solid #e9ecef; }
        .nav { display: flex; gap: 2rem; }
        .nav a { text-decoration: none; color: #333; }
        .nav a:hover { color: #007bff; }
        .content { padding: 3rem 0; }
        .footer { background: #f8f9fa; padding: 2rem 0; margin-top: 3rem; text-align: center; }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <a href="/">Home</a>
                <a href="/blog">Blog</a>
                <a href="/about">About</a>
            </nav>
        </div>
    </header>
    
    <main class="content">
        <div class="container">
            <h1>{{ page.title }}</h1>
            <div>{{ page.content }}</div>
        </div>
    </main>
    
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 {{ site.title }}. Built with HaruCMS.</p>
        </div>
    </footer>
</body>
</html>';
}

function getDefaultPostTemplate() {
    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ entry.title }} - {{ site.title }}</title>
    <meta name="description" content="{{ entry.meta_description }}">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; line-height: 1.6; margin: 0; padding: 0; }
        .container { max-width: 800px; margin: 0 auto; padding: 0 20px; }
        .header { background: #f8f9fa; padding: 1rem 0; border-bottom: 1px solid #e9ecef; }
        .nav { display: flex; gap: 2rem; }
        .nav a { text-decoration: none; color: #333; }
        .nav a:hover { color: #007bff; }
        .content { padding: 3rem 0; }
        .post-meta { color: #6c757d; font-size: 0.9rem; margin-bottom: 2rem; }
        .footer { background: #f8f9fa; padding: 2rem 0; margin-top: 3rem; text-align: center; }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <a href="/">Home</a>
                <a href="/blog">Blog</a>
                <a href="/about">About</a>
            </nav>
        </div>
    </header>
    
    <main class="content">
        <div class="container">
            <h1>{{ entry.title }}</h1>
            <div class="post-meta">
                Published on {{ entry.created_at }}
                {% if entry.author %} by {{ entry.author }}{% endif %}
            </div>
            <div>{{ entry.content }}</div>
        </div>
    </main>
    
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 {{ site.title }}. Built with HaruCMS.</p>
        </div>
    </footer>
</body>
</html>';
}

function getDefaultListTemplate() {
    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ page.title }} - {{ site.title }}</title>
    <meta name="description" content="{{ page.meta_description }}">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; line-height: 1.6; margin: 0; padding: 0; }
        .container { max-width: 800px; margin: 0 auto; padding: 0 20px; }
        .header { background: #f8f9fa; padding: 1rem 0; border-bottom: 1px solid #e9ecef; }
        .nav { display: flex; gap: 2rem; }
        .nav a { text-decoration: none; color: #333; }
        .nav a:hover { color: #007bff; }
        .content { padding: 3rem 0; }
        .list-item { border-bottom: 1px solid #e9ecef; padding: 2rem 0; }
        .list-item h2 { margin-top: 0; }
        .list-item .date { color: #6c757d; font-size: 0.9rem; }
        .footer { background: #f8f9fa; padding: 2rem 0; margin-top: 3rem; text-align: center; }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <a href="/">Home</a>
                <a href="/blog">Blog</a>
                <a href="/about">About</a>
            </nav>
        </div>
    </header>
    
    <main class="content">
        <div class="container">
            <h1>{{ page.title }}</h1>
            <div class="list">
                {% for entry in entries %}
                    <article class="list-item">
                        <h2><a href="/{{ collection.slug }}/{{ entry.slug }}">{{ entry.title }}</a></h2>
                        <div class="date">{{ entry.created_at }}</div>
                        <p>{{ entry.excerpt }}</p>
                    </article>
                {% endfor %}
            </div>
        </div>
    </main>
    
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 {{ site.title }}. Built with HaruCMS.</p>
        </div>
    </footer>
</body>
</html>';
}

function getUsersData() {
    $users = [];
    $usersDir = __DIR__ . '/../users';
    
    if (is_dir($usersDir)) {
        $files = glob($usersDir . '/*.json');
        foreach ($files as $file) {
            $user = json_decode(file_get_contents($file), true);
            if ($user) {
                // Don't include password in the data
                unset($user['password']);
                $users[] = $user;
            }
        }
    }
    
    return [
        'users' => $users,
        'roles' => ['admin', 'editor', 'viewer']
    ];
}

function getRolesData() {
    $roles = [];
    $rolesFile = __DIR__ . '/../settings/roles.json';
    
    if (file_exists($rolesFile)) {
        $roles = json_decode(file_get_contents($rolesFile), true) ?: [];
    }
    
    // Get users for role assignment
    $users = [];
    $usersDir = __DIR__ . '/../users';
    
    if (is_dir($usersDir)) {
        $files = glob($usersDir . '/*.json');
        foreach ($files as $file) {
            $user = json_decode(file_get_contents($file), true);
            if ($user) {
                unset($user['password']);
                $users[] = $user;
            }
        }
    }
    
    return [
        'roles' => $roles,
        'users' => $users
    ];
}

function getOnboardingData() {
    // Get current user
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $userId = $_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown';
    
    // Include onboarding functions
    require_once __DIR__ . '/onboarding.php';
    
    // Get onboarding status
    $status = getOnboardingStatus($userId);
    
    // Check if demo content exists (it ships with the CMS by default)
    $demoContentInfo = [
        'has_demo_content' => false,
        'collections' => [],
        'has_demo_website' => false
    ];
    
    // Check for demo blog collection
    $blogCollectionPath = __DIR__ . '/../content/collections/blog';
    if (is_dir($blogCollectionPath)) {
        $collectionFile = "$blogCollectionPath/collection.json";
        if (file_exists($collectionFile)) {
            $collectionData = json_decode(file_get_contents($collectionFile), true);
            if ($collectionData) {
                $entriesPath = "$blogCollectionPath/entries";
                $entryCount = 0;
                if (is_dir($entriesPath)) {
                    $entryFiles = glob("$entriesPath/*.json");
                    $entryCount = count($entryFiles);
                }
                
                $demoContentInfo['has_demo_content'] = true;
                $demoContentInfo['collections'][] = [
                    'slug' => 'blog',
                    'name' => $collectionData['title'] ?? 'Blog',
                    'entries_count' => $entryCount
                ];
            }
        }
    }
    
    // Check for demo website
    $demoBlogPath = __DIR__ . '/../demo-website';
    $demoContentInfo['has_demo_website'] = is_dir($demoBlogPath);
    
    return [
        'status' => $status,
        'demo_content' => $demoContentInfo,
        'user' => [
            'name' => $_SESSION['user']['name'] ?? $_SESSION['user']['email'] ?? 'User',
            'email' => $_SESSION['user']['email'] ?? ''
        ]
    ];
}
?> 