<?php
// CRITICAL: Suppress ALL errors and warnings FIRST, before anything else
error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// CRITICAL: Start output buffering IMMEDIATELY to catch any stray output
ob_start();

// Only set headers if this file is being called directly (not included)
// When included via admin/index.php, headers are already sent, so skip
if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    header('Content-Type: application/json');
    
    // SECURITY FIX: Replace wildcard CORS with origin whitelist
    require_once __DIR__ . '/../core/cors.php';
    set_cors_headers('admin');
}

// Debug: Log the request (this will always run now, even if there are errors later)
error_log("=== admin_collections.php called ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST keys: " . implode(', ', array_keys($_POST ?? [])));
if (!empty($_POST['sub_collections'])) {
    error_log("Sub-collections present in POST: " . (is_array($_POST['sub_collections']) ? count($_POST['sub_collections']) : 'not array'));
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// SECURITY FIX: Require CSRF protection for all POST requests
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
haru_secure_session_start();

// SECURITY: Require authentication + permission for collection mutations
// Collections and sub-collections are part of content management.
requireAuth('manage_content');

$csrf = $_POST['_csrf'] ?? '';
if (!validate_csrf_token($csrf)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

/**
 * Process nested sub-collection rows from POST (standalone sub-collection editor).
 *
 * @param array<int, array<string, mixed>> $rawNested
 * @return list<array<string, mixed>>
 */
function birkly_admin_build_nested_sub_collections(array $rawNested, string $parentSlug): array
{
    $processed = [];
    foreach ($rawNested as $nestedData) {
        if (!is_array($nestedData) || empty($nestedData['slug']) || empty($nestedData['name'])) {
            continue;
        }

        $nestedFields = $nestedData['schema']['fields'] ?? $nestedData['fields'] ?? [];
        $processedFields = [];
        if (is_array($nestedFields)) {
            foreach ($nestedFields as $fieldId => $fieldData) {
                $fieldName = $fieldData['name'] ?? '';
                $fieldLabel = $fieldData['label'] ?? '';
                $fieldType = $fieldData['type'] ?? '';
                if (empty($fieldName) && !empty($fieldLabel)) {
                    $fieldName = strtolower(trim(preg_replace('/[^A-Za-z0-9_]+/', '_', $fieldLabel)));
                }
                if (empty($fieldName) || empty($fieldType)) {
                    continue;
                }
                $processedFields[] = [
                    'name' => strtolower(trim(preg_replace('/[^A-Za-z0-9_]+/', '_', $fieldName))),
                    'label' => !empty($fieldLabel) ? $fieldLabel : $fieldName,
                    'type' => $fieldType,
                    'required' => !empty($fieldData['required']),
                    'id' => $fieldId,
                ];
            }
        }

        $nestedSlug = trim((string) $nestedData['slug']);
        $processed[] = [
            'slug' => $nestedSlug,
            'name' => trim((string) $nestedData['name']),
            'description' => trim((string) ($nestedData['description'] ?? '')),
            'entry_method' => $nestedData['entry_method'] ?? 'admin_only',
            'auto_publish' => !empty($nestedData['auto_publish']),
            'fields' => $processedFields,
            'parent_field' => $parentSlug . '_id',
            'parent_sub_collection' => $parentSlug,
        ];
    }

    return $processed;
}

try {
    // Check for sub-collection specific actions
    $action = $_POST['action'] ?? '';
    
    // Handle add_sub_collection action (standalone sub-collection creation)
    if ($action === 'add_sub_collection') {
        $collectionSlug = $_POST['collection_slug'] ?? '';
        $parentSubCollectionSlug = $_POST['parent_sub_collection_slug'] ?? null;
        
        if (empty($collectionSlug)) {
            throw new Exception('Collection slug is required');
        }
        
        // Load existing collection
        $collectionFile = __DIR__ . "/../content/collections/$collectionSlug/collection.json";
        if (!file_exists($collectionFile)) {
            throw new Exception('Collection not found');
        }
        
        $collectionData = json_decode(file_get_contents($collectionFile), true);
        
        // Determine where to add the sub-collection
        if ($parentSubCollectionSlug) {
            // Nested sub-collection - add to parent sub-collection
            $existingSubCollections = $collectionData['sub_collections'] ?? [];
            $parentSubCollection = null;
            $parentIndex = null;
            foreach ($existingSubCollections as $idx => $subCol) {
                if (($subCol['slug'] ?? '') === $parentSubCollectionSlug) {
                    $parentSubCollection = $subCol;
                    $parentIndex = $idx;
                    break;
                }
            }
            if (!$parentSubCollection) {
                throw new Exception('Parent sub-collection not found');
            }
            $targetSubCollections = $parentSubCollection['sub_collections'] ?? [];
        } else {
            // Top-level sub-collection
            $existingSubCollections = $collectionData['sub_collections'] ?? [];
            $targetSubCollections = $existingSubCollections;
            $parentSubCollection = null;
            $parentIndex = null;
        }
        
        // Get sub-collection data from form
        $subCollections = $_POST['sub_collections'] ?? [];
        if (empty($subCollections) || !is_array($subCollections)) {
            throw new Exception('Sub-collection data is required');
        }
        
        $subCollectionData = $subCollections[0] ?? [];
        if (empty($subCollectionData['name']) || empty($subCollectionData['slug'])) {
            throw new Exception('Sub-collection name and slug are required');
        }
        
        // Process sub-collection fields
        $subCollectionFields = $subCollectionData['schema']['fields'] ?? $subCollectionData['fields'] ?? [];
        $processedSubFields = [];
        
        foreach ($subCollectionFields as $fieldId => $fieldData) {
            $fieldName = $fieldData['name'] ?? '';
            $fieldLabel = $fieldData['label'] ?? '';
            $fieldType = $fieldData['type'] ?? '';
            
            if (empty($fieldName) && !empty($fieldLabel)) {
                $fieldName = strtolower(trim(preg_replace('/[^A-Za-z0-9_]+/', '_', $fieldLabel)));
            }
            
            if (empty($fieldName) || empty($fieldType)) {
                continue;
            }
            
            $field = [
                'name' => strtolower(trim(preg_replace('/[^A-Za-z0-9_]+/', '_', $fieldName))),
                'label' => !empty($fieldLabel) ? $fieldLabel : $fieldName,
                'type' => $fieldType,
                'required' => !empty($fieldData['required']),
                'id' => $fieldId
            ];
            
            if (in_array($fieldType, ['select', 'radio', 'multiselect']) && isset($fieldData['options'])) {
                $field['options'] = is_array($fieldData['options']) ? $fieldData['options'] : array_map('trim', explode(',', $fieldData['options']));
            }
            
            if (isset($fieldData['default']) && $fieldData['default'] !== '') {
                $field['default'] = $fieldData['default'];
            }
            
            $processedSubFields[] = $field;
        }
        
        $newSubCollection = [
            'slug' => trim($subCollectionData['slug']),
            'name' => trim($subCollectionData['name']),
            'description' => trim($subCollectionData['description'] ?? ''),
            'entry_method' => $subCollectionData['entry_method'] ?? 'admin_only',
            'auto_publish' => !empty($subCollectionData['auto_publish']),
            'fields' => $processedSubFields,
            'parent_field' => $parentSubCollectionSlug ? ($parentSubCollectionSlug . '_id') : ($collectionSlug . '_id'),
            'parent_sub_collection' => $parentSubCollectionSlug
        ];

        if (isset($subCollectionData['sub_collections']) && is_array($subCollectionData['sub_collections'])) {
            $newSubCollection['sub_collections'] = birkly_admin_build_nested_sub_collections(
                $subCollectionData['sub_collections'],
                trim($subCollectionData['slug'])
            );
        }
        
        // Add to target sub-collections
        $targetSubCollections[] = $newSubCollection;
        
        if ($parentSubCollectionSlug) {
            // Update nested sub-collection
            $existingSubCollections[$parentIndex]['sub_collections'] = $targetSubCollections;
            $collectionData['sub_collections'] = $existingSubCollections;
        } else {
            // Update top-level sub-collections
            $collectionData['sub_collections'] = $targetSubCollections;
        }
        
        // Save collection
        if (file_put_contents($collectionFile, json_encode($collectionData, JSON_PRETTY_PRINT)) === false) {
            throw new Exception('Failed to save sub-collection');
        }
        
        ob_clean();
        echo json_encode([
            'status' => 'ok',
            'message' => 'Sub-collection created successfully',
            'data' => [
                'sub_collection' => $newSubCollection
            ]
        ]);
        ob_end_flush();
        exit;
    }
    
    // Handle update_sub_collection action (standalone sub-collection editing)
    if ($action === 'update_sub_collection') {
        $collectionSlug = $_POST['collection_slug'] ?? '';
        $subCollectionSlug = $_POST['sub_collection_slug'] ?? '';
        $parentSubCollectionSlug = $_POST['parent_sub_collection_slug'] ?? null;
        
        if (empty($collectionSlug) || empty($subCollectionSlug)) {
            throw new Exception('Collection slug and sub-collection slug are required');
        }
        
        // Load existing collection
        $collectionFile = __DIR__ . "/../content/collections/$collectionSlug/collection.json";
        if (!file_exists($collectionFile)) {
            throw new Exception('Collection not found');
        }
        
        $collectionData = json_decode(file_get_contents($collectionFile), true);
        $existingSubCollections = $collectionData['sub_collections'] ?? [];
        
        // Find and update the sub-collection (check nested if parent_sub_collection_slug is provided)
        $found = false;
        $targetSubCollections = null;
        $targetIndex = null;
        $parentIndex = null;
        
        if ($parentSubCollectionSlug) {
            // Look for nested sub-collection
            foreach ($existingSubCollections as $pIdx => $parentSubCol) {
                if (($parentSubCol['slug'] ?? '') === $parentSubCollectionSlug) {
                    $parentIndex = $pIdx;
                    $nestedSubCollections = $parentSubCol['sub_collections'] ?? [];
                    foreach ($nestedSubCollections as $nIdx => $nestedSubCol) {
                        if (($nestedSubCol['slug'] ?? '') === $subCollectionSlug) {
                            $targetSubCollections = &$nestedSubCollections;
                            $targetIndex = $nIdx;
                            $found = true;
                            break 2;
                        }
                    }
                }
            }
        } else {
            // Look for top-level sub-collection
            foreach ($existingSubCollections as $index => $subCollection) {
                if (($subCollection['slug'] ?? '') === $subCollectionSlug) {
                    $targetSubCollections = &$existingSubCollections;
                    $targetIndex = $index;
                    $found = true;
                    break;
                }
            }
        }
        
        if ($found && $targetSubCollections !== null && $targetIndex !== null) {
            $subCollection = $targetSubCollections[$targetIndex];
                // Get updated sub-collection data from form
                $subCollections = $_POST['sub_collections'] ?? [];
                $subCollectionData = $subCollections[0] ?? [];
                
                // Process sub-collection fields
                $subCollectionFields = $subCollectionData['schema']['fields'] ?? $subCollectionData['fields'] ?? [];
                $processedSubFields = [];
                
                foreach ($subCollectionFields as $fieldId => $fieldData) {
                    $fieldName = $fieldData['name'] ?? '';
                    $fieldLabel = $fieldData['label'] ?? '';
                    $fieldType = $fieldData['type'] ?? '';
                    
                    if (empty($fieldName) && !empty($fieldLabel)) {
                        $fieldName = strtolower(trim(preg_replace('/[^A-Za-z0-9_]+/', '_', $fieldLabel)));
                    }
                    
                    if (empty($fieldName) || empty($fieldType)) {
                        continue;
                    }
                    
                    $field = [
                        'name' => strtolower(trim(preg_replace('/[^A-Za-z0-9_]+/', '_', $fieldName))),
                        'label' => !empty($fieldLabel) ? $fieldLabel : $fieldName,
                        'type' => $fieldType,
                        'required' => !empty($fieldData['required']),
                        'id' => $fieldId
                    ];
                    
                    if (in_array($fieldType, ['select', 'radio', 'multiselect']) && isset($fieldData['options'])) {
                        $field['options'] = is_array($fieldData['options']) ? $fieldData['options'] : array_map('trim', explode(',', $fieldData['options']));
                    }
                    
                    if (isset($fieldData['default']) && $fieldData['default'] !== '') {
                        $field['default'] = $fieldData['default'];
                    }
                    
                    $processedSubFields[] = $field;
                }
                
            $updatedSubCollection = [
                'slug' => trim($subCollectionData['slug'] ?? $subCollectionSlug),
                'name' => trim($subCollectionData['name'] ?? $subCollection['name']),
                'description' => trim($subCollectionData['description'] ?? ''),
                'entry_method' => $subCollectionData['entry_method'] ?? 'admin_only',
                'auto_publish' => !empty($subCollectionData['auto_publish']),
                'fields' => $processedSubFields,
                'parent_field' => $subCollection['parent_field'] ?? ($parentSubCollectionSlug ? ($parentSubCollectionSlug . '_id') : ($collectionSlug . '_id')),
                'parent_sub_collection' => $parentSubCollectionSlug ?? ($subCollection['parent_sub_collection'] ?? null),
            ];

            if (isset($subCollectionData['sub_collections']) && is_array($subCollectionData['sub_collections'])) {
                $updatedSubCollection['sub_collections'] = birkly_admin_build_nested_sub_collections(
                    $subCollectionData['sub_collections'],
                    trim($subCollectionData['slug'] ?? $subCollectionSlug)
                );
            } else {
                $updatedSubCollection['sub_collections'] = $subCollection['sub_collections'] ?? [];
            }

            $targetSubCollections[$targetIndex] = $updatedSubCollection;
            
            if ($parentSubCollectionSlug && $parentIndex !== null) {
                $existingSubCollections[$parentIndex]['sub_collections'] = $targetSubCollections;
            }
        }
        
        if (!$found) {
            throw new Exception('Sub-collection not found');
        }
        
        $collectionData['sub_collections'] = $existingSubCollections;
        
        // Save collection
        if (file_put_contents($collectionFile, json_encode($collectionData, JSON_PRETTY_PRINT)) === false) {
            throw new Exception('Failed to update sub-collection');
        }
        
        ob_clean();
        echo json_encode([
            'status' => 'ok',
            'message' => 'Sub-collection updated successfully',
            'data' => [
                'sub_collection' => $targetSubCollections[$targetIndex]
            ]
        ]);
        ob_end_flush();
        exit;
    }
    
    // Get form data (for regular collection operations)
    $name = $_POST['name'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $description = $_POST['description'] ?? '';
    $icon = trim((string) ($_POST['icon'] ?? ''));
    $iconKey = trim((string) ($_POST['icon_key'] ?? ''));
    $template = $_POST['template'] ?? '';
    $sort = $_POST['sort'] ?? 'created_desc';
    $fields = $_POST['fields'] ?? [];
    $entryMethod = $_POST['entry_method'] ?? 'admin_only';
    $allowPublicRead = !empty($_POST['allow_public_read']);
    $apiKey = $_POST['api_key'] ?? '';
    $apiSettings = $_POST['api_settings'] ?? [];
    $multilanguageEnabled = !empty($_POST['multilanguage_enabled']);
    $multilanguageLanguages = $_POST['multilanguage_languages'] ?? [];
    $subCollections = $_POST['sub_collections'] ?? [];
    
    error_log("Processing form data:");
    error_log("name: " . $name);
    error_log("slug: " . $slug);
    error_log("description: " . $description);
    error_log("template: " . $template);
    error_log("sort: " . $sort);
    error_log("fields: " . print_r($fields, true));
    
    require_once __DIR__ . '/../core/entry_slug.php';

    // Check if collection already exists (for updates) — use posted slug to locate directory
    $existingCollection = null;
    $collectionsPath = __DIR__ . '/../content/collections';
    $postedSlug = trim((string) $slug);
    if ($postedSlug !== '') {
        $candidateDir = $collectionsPath . '/' . birkly_normalize_slug($postedSlug);
        if (is_dir($candidateDir)) {
            $collectionFile = $candidateDir . '/collection.json';
            if (file_exists($collectionFile)) {
                $existingCollection = json_decode(file_get_contents($collectionFile), true);
            }
        }
    }

    $collectionId = $existingCollection['id'] ?? null;
    $slug = birkly_resolve_collection_slug($_POST, $existingCollection, $name, $collectionId);

    if ($slug === '') {
        throw new Exception('Collection slug is required');
    }
    
    error_log("Generated slug: " . $slug);
    
    // Validate slug format
    error_log("Validating slug format: " . $slug);
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        error_log("Slug validation failed for: " . $slug);
        throw new Exception('Slug can only contain lowercase letters, numbers, and hyphens');
    }
    
    error_log("Slug validation passed");
    
    $collectionDir = $collectionsPath . '/' . $slug;
    
    error_log("Collection path: " . $collectionsPath);
    error_log("Collection dir: " . $collectionDir);
    error_log("Collections path exists: " . (is_dir($collectionsPath) ? 'yes' : 'no'));
    error_log("Collection dir exists: " . (is_dir($collectionDir) ? 'yes' : 'no'));
    
    if (!$existingCollection && is_dir($collectionDir)) {
        $collectionFile = $collectionDir . '/collection.json';
        error_log("Collection file: " . $collectionFile);
        error_log("Collection file exists: " . (file_exists($collectionFile) ? 'yes' : 'no'));
        if (file_exists($collectionFile)) {
            $existingCollection = json_decode(file_get_contents($collectionFile), true);
            error_log("Existing collection found: " . print_r($existingCollection, true));
        }
    }

    if ($action === 'create' && $existingCollection) {
        throw new Exception('A collection with this slug already exists. Choose a different slug or open the existing collection to edit it.');
    }
    
    // Create collection directory if it doesn't exist
    if (!is_dir($collectionsPath)) {
        error_log("Creating collections directory: " . $collectionsPath);
        $result = mkdir($collectionsPath, 0755, true);
        error_log("mkdir result: " . ($result ? 'success' : 'failed'));
    }
    
    if (!is_dir($collectionDir)) {
        error_log("Creating collection directory: " . $collectionDir);
        $result = mkdir($collectionDir, 0755, true);
        error_log("mkdir result: " . ($result ? 'success' : 'failed'));
    }
    
    // Generate API key if Public API is selected and no key exists
    if ($entryMethod === 'public_api' && empty($apiKey)) {
        // Generate a secure random API key
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $apiKey = 'birkly_';
        for ($i = 0; $i < 32; $i++) {
            $apiKey .= $chars[random_int(0, strlen($chars) - 1)];
        }
    }
    
    // Prepare collection data
    $collectionData = [
        'name' => $name,
        'slug' => $slug,
        'description' => $description,
        'template' => $template,
        'sort' => $sort,
        'entry_method' => $entryMethod,
        'allow_public_read' => $allowPublicRead,
        'multilanguage' => [
            'enabled' => $multilanguageEnabled,
            'languages' => $multilanguageLanguages
        ],
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    if ($icon !== '') {
        $collectionData['icon'] = $icon;
    }
    if ($iconKey !== '') {
        $collectionData['icon_key'] = $iconKey;
    }
    
    // Add API key and settings if Public API is enabled
    if ($entryMethod === 'public_api') {
        require_once __DIR__ . '/../core/ai_collection_config.php';
        $collectionData['api_key'] = $apiKey;
        $rawApiSettings = is_array($apiSettings) ? $apiSettings : [];
        $apiSettingsError = birkly_validate_api_settings_keys($rawApiSettings);
        if ($apiSettingsError !== null) {
            throw new Exception($apiSettingsError);
        }
        $existingApiSettings = is_array($existingCollection['api_settings'] ?? null)
            ? birkly_migrate_collection_api_settings($existingCollection['api_settings'])
            : [];
        $collectionData['api_settings'] = birkly_ai_normalize_api_settings(array_merge($existingApiSettings, $rawApiSettings));
    } else {
        unset($collectionData['api_key']);
        unset($collectionData['api_settings']);
    }
    
    // Preserve existing created_at if updating
    if ($existingCollection && isset($existingCollection['created_at'])) {
        $collectionData['created_at'] = $existingCollection['created_at'];
    }
    
    // Preserve existing API key if updating and not changing entry method
    if ($existingCollection && isset($existingCollection['entry_method']) && 
        $existingCollection['entry_method'] === 'public_api' && 
        $entryMethod === 'public_api' && 
        empty($apiKey) && 
        isset($existingCollection['api_key'])) {
        $collectionData['api_key'] = $existingCollection['api_key'];
        // Merge existing API settings with new ones
        if (isset($existingCollection['api_settings'])) {
            $collectionData['api_settings'] = array_merge(
                $existingCollection['api_settings'],
                $collectionData['api_settings']
            );
        }
    }
    
    // Process and save sub-collections
    error_log("Raw sub_collections POST data: " . print_r($subCollections, true));
    
    if (!empty($_POST['sub_collections_replace'])) {
        $processedSubCollections = [];
        if (is_array($subCollections) && count($subCollections) > 0) {
        foreach ($subCollections as $subCollectionIndex => $subCollectionData) {
            error_log("Processing sub-collection index: $subCollectionIndex");
            error_log("Sub-collection data keys: " . implode(', ', array_keys($subCollectionData)));
            
            if (empty($subCollectionData['slug']) || empty($subCollectionData['name'])) {
                error_log("Skipping sub-collection: missing slug or name");
                continue; // Skip invalid sub-collections
            }
            
            // Debug: Log sub-collection data structure
            // Extract fields from schema if present, otherwise use fields directly
            $subCollectionFields = [];
            
            // Check for schema[fields] structure (form sends: sub_collections[index][schema][fields][fieldIndex][type])
            if (isset($subCollectionData['schema']['fields']) && is_array($subCollectionData['schema']['fields'])) {
                $subCollectionFields = $subCollectionData['schema']['fields'];
                error_log("Found fields in schema[fields] structure: " . count($subCollectionFields));
            } 
            // Check for direct fields structure (backward compatibility)
            elseif (isset($subCollectionData['fields']) && is_array($subCollectionData['fields'])) {
                $subCollectionFields = $subCollectionData['fields'];
                error_log("Found fields in direct fields structure: " . count($subCollectionFields));
            } else {
                error_log("No fields found in sub-collection data. Available keys: " . implode(', ', array_keys($subCollectionData)));
                if (isset($subCollectionData['schema'])) {
                    error_log("Schema structure: " . print_r($subCollectionData['schema'], true));
                }
            }
            
            // Debug logging
            error_log("Sub-collection '" . ($subCollectionData['name'] ?? 'unnamed') . "': Found " . count($subCollectionFields) . " fields");
            if (count($subCollectionFields) > 0) {
                error_log("First field sample: " . json_encode(array_slice($subCollectionFields, 0, 1, true)));
            }
            
            // Process sub-collection fields similar to main collection fields
            $processedSubFields = [];
            if (is_array($subCollectionFields) && count($subCollectionFields) > 0) {
                foreach ($subCollectionFields as $fieldId => $fieldData) {
                    error_log("Processing field ID: $fieldId, data: " . print_r($fieldData, true));
                    
                    // Check if field has required data - be more lenient with name (can be auto-generated from label)
                    $fieldName = $fieldData['name'] ?? '';
                    $fieldLabel = $fieldData['label'] ?? '';
                    $fieldType = $fieldData['type'] ?? '';
                    
                    // If name is empty but label exists, generate name from label
                    if (empty($fieldName) && !empty($fieldLabel)) {
                        $fieldName = strtolower(trim(preg_replace('/[^A-Za-z0-9_]+/', '_', $fieldLabel)));
                    }
                    
                    // Skip if still no name or no type
                    if (empty($fieldName) || empty($fieldType)) {
                        error_log("Skipping field: missing name or type (name: '$fieldName', type: '$fieldType')");
                        continue; // Skip invalid fields
                    }
                    
                    $field = [
                        'name' => strtolower(trim(preg_replace('/[^A-Za-z0-9_]+/', '_', $fieldName))),
                        'label' => !empty($fieldLabel) ? $fieldLabel : $fieldName,
                        'type' => $fieldType,
                        'required' => !empty($fieldData['required']),
                        'id' => $fieldId
                    ];
                    
                    // Add field-specific settings based on fieldtype (simplified version)
                    
                    // Handle options for select, radio, multiselect
                    if (in_array($fieldType, ['select', 'radio', 'multiselect'])) {
                        if (isset($fieldData['options']) && is_array($fieldData['options'])) {
                            $field['options'] = $fieldData['options'];
                        } elseif (isset($fieldData['options']) && is_string($fieldData['options'])) {
                            // Handle comma-separated options string
                            $field['options'] = array_map('trim', explode(',', $fieldData['options']));
                        }
                    }
                    
                    // Handle default value
                    if (isset($fieldData['default']) && $fieldData['default'] !== '') {
                        $field['default'] = $fieldData['default'];
                    }
                    
                    // Handle placeholder
                    if (isset($fieldData['placeholder']) && $fieldData['placeholder'] !== '') {
                        $field['placeholder'] = $fieldData['placeholder'];
                    }
                    
                    // Handle help text
                    if (isset($fieldData['help']) && $fieldData['help'] !== '') {
                        $field['help'] = $fieldData['help'];
                    }
                    
                    error_log("Processed field: " . json_encode($field));
                    $processedSubFields[] = $field;
                }
            } else {
                error_log("No fields to process for sub-collection '" . ($subCollectionData['name'] ?? 'unnamed') . "'");
            }
            
            $subCollection = [
                'slug' => trim($subCollectionData['slug']),
                'name' => trim($subCollectionData['name']),
                'description' => trim($subCollectionData['description'] ?? ''),
                'entry_method' => $subCollectionData['entry_method'] ?? 'admin_only',
                'auto_publish' => !empty($subCollectionData['auto_publish']),
                'fields' => $processedSubFields
            ];

            if (($subCollection['entry_method'] ?? 'admin_only') === 'public_api') {
                require_once __DIR__ . '/../core/ai_collection_config.php';
                $subApiSettings = is_array($subCollectionData['api_settings'] ?? null) ? $subCollectionData['api_settings'] : [];
                $subApiSettingsError = birkly_validate_api_settings_keys($subApiSettings);
                if ($subApiSettingsError !== null) {
                    throw new Exception($subApiSettingsError);
                }
                $subCollection['api_settings'] = birkly_ai_normalize_api_settings($subApiSettings);
            }
            
            // Auto-generate parent field reference
            $parentFieldName = $slug . '_id';
            $subCollection['parent_field'] = $parentFieldName;
            
            error_log("Final sub-collection structure: " . json_encode($subCollection, JSON_PRETTY_PRINT));
            $processedSubCollections[] = $subCollection;
        }
        }
        $collectionData['sub_collections'] = $processedSubCollections;
        error_log("Total processed sub-collections: " . count($processedSubCollections));
    } else {
        error_log("No sub-collections replace flag or empty replace payload");
        // Preserve existing sub_collections when replace marker absent (API-only updates)
        if ($existingCollection && isset($existingCollection['sub_collections'])) {
            $collectionData['sub_collections'] = $existingCollection['sub_collections'];
            error_log("Preserving existing sub-collections: " . count($existingCollection['sub_collections']));
        } else {
            $collectionData['sub_collections'] = [];
        }
    }
    
    // Save collection.json
    $collectionFile = $collectionDir . '/collection.json';
    if (file_put_contents($collectionFile, json_encode($collectionData, JSON_PRETTY_PRINT)) === false) {
        throw new Exception('Failed to save collection data');
    }
    
    // Process and save fields
    $processedFields = [];
    if (is_array($fields)) {
        foreach ($fields as $fieldId => $fieldData) {
            if (empty($fieldData['name']) || empty($fieldData['type'])) {
                continue; // Skip invalid fields
            }
            
            // Debug: Log field data
            error_log("Processing field ID: $fieldId, type: " . ($fieldData['type'] ?? 'missing') . ", keys: " . implode(', ', array_keys($fieldData)));
            
            $fieldName = strtolower(trim(preg_replace('/[^A-Za-z0-9_]+/', '_', $fieldData['name'])));
            $reservedNames = array_flip(array_map('strtolower', birkly_entry_reserved_field_names()));
            if (isset($reservedNames[$fieldName])) {
                throw new Exception(
                    'Field name "' . $fieldName . '" is reserved for entry metadata (slug, status, SEO, etc.). Choose a different name.'
                );
            }

            $field = [
                'name' => $fieldName,
                'label' => $fieldData['label'] ?? $fieldData['name'],
                'type' => $fieldData['type'],
                'required' => !empty($fieldData['required']),
                'id' => $fieldId
            ];
            
            // Add field-specific settings based on fieldtype
            // Keep fieldtype as-is (don't normalize - let getFieldtypeClass handle aliases)
            $fieldType = $fieldData['type'];
            
            // Handle options for select, radio, multiselect
            if (in_array($fieldType, ['select', 'radio', 'multiselect'])) {
                $options = [];
                
                // Handle new format: option-label and option-value inputs
                if (isset($fieldData['option-label']) && isset($fieldData['option-value'])) {
                    $labels = is_array($fieldData['option-label']) ? $fieldData['option-label'] : [$fieldData['option-label']];
                    $values = is_array($fieldData['option-value']) ? $fieldData['option-value'] : [$fieldData['option-value']];
                    
                    for ($i = 0; $i < count($labels); $i++) {
                        $label = trim($labels[$i] ?? '');
                        $value = trim($values[$i] ?? '');
                        if (!empty($label)) {
                            $options[] = [
                                'label' => $label,
                                'value' => !empty($value) ? $value : strtolower(preg_replace('/[^a-z0-9]+/', '_', $label))
                            ];
                        }
                    }
                }
                
                // Fallback: Handle options_text format (label|value per line) for backward compatibility
                if (empty($options) && !empty($fieldData['options_text'])) {
                    $lines = explode("\n", $fieldData['options_text']);
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (empty($line)) continue;
                        
                        if (strpos($line, '|') !== false) {
                            list($label, $value) = explode('|', $line, 2);
                            $options[] = [
                                'label' => trim($label),
                                'value' => trim($value)
                            ];
                        } else {
                            $options[] = [
                                'label' => $line,
                                'value' => strtolower(preg_replace('/[^a-z0-9]+/', '-', $line))
                            ];
                        }
                    }
                }
                
                // Also handle array format from existing data
                if (empty($options) && isset($fieldData['options']) && is_array($fieldData['options'])) {
                    foreach ($fieldData['options'] as $optionData) {
                        if (is_array($optionData) && !empty($optionData['label']) && !empty($optionData['value'])) {
                            $options[] = [
                                'label' => $optionData['label'],
                                'value' => $optionData['value']
                            ];
                        }
                    }
                }
                
                if (!empty($options)) {
                    $field['options'] = $options;
                }
                
                // Radio-specific: layout
                if ($fieldType === 'radio' && isset($fieldData['layout'])) {
                    $field['layout'] = $fieldData['layout'];
                }
                
                // Multiselect-specific: searchable
                if ($fieldType === 'multiselect' && isset($fieldData['searchable'])) {
                    $field['searchable'] = !empty($fieldData['searchable']);
                }
            }
            
            // Number fieldtype
            if ($fieldType === 'number') {
                if (isset($fieldData['min']) && $fieldData['min'] !== '') {
                    $field['min'] = (float)$fieldData['min'];
                }
                if (isset($fieldData['max']) && $fieldData['max'] !== '') {
                    $field['max'] = (float)$fieldData['max'];
                }
                if (isset($fieldData['step']) && $fieldData['step'] !== '') {
                    $field['step'] = (float)$fieldData['step'];
                }
                if (isset($fieldData['integer'])) {
                    $field['integer'] = !empty($fieldData['integer']);
                }
            }
            
            // Text and Textarea
            if (in_array($fieldType, ['text', 'textarea', 'email', 'url'])) {
                if (isset($fieldData['placeholder'])) {
                    $field['placeholder'] = $fieldData['placeholder'];
                }
                if (isset($fieldData['minLength']) && $fieldData['minLength'] !== '') {
                    $field['minLength'] = (int)$fieldData['minLength'];
                }
                if (isset($fieldData['maxLength']) && $fieldData['maxLength'] !== '') {
                    $field['maxLength'] = (int)$fieldData['maxLength'];
                }
            }
            
            // Textarea-specific: rows
            if ($fieldType === 'textarea' && isset($fieldData['rows'])) {
                $field['rows'] = (int)$fieldData['rows'];
            }
            
            // Email-specific: multiple
            if ($fieldType === 'email' && isset($fieldData['multiple'])) {
                $field['multiple'] = !empty($fieldData['multiple']);
            }
            
            // URL-specific: autoProtocol
            if ($fieldType === 'url' && isset($fieldData['autoProtocol'])) {
                $field['autoProtocol'] = !empty($fieldData['autoProtocol']);
            }
            
            // Date fieldtype
            if ($fieldType === 'date' && isset($fieldData['format'])) {
                $field['format'] = $fieldData['format'];
            }
            
            // Time fieldtype
            if ($fieldType === 'time') {
                if (isset($fieldData['format'])) {
                    $field['format'] = $fieldData['format'];
                }
                if (isset($fieldData['step']) && $fieldData['step'] !== '') {
                    $field['step'] = (int)$fieldData['step'];
                }
            }
            
            // DateTime fieldtype
            if ($fieldType === 'datetime' && isset($fieldData['format'])) {
                $field['format'] = $fieldData['format'];
            }
            
            // ContentEditor fieldtype (check both with and without hyphen)
            // Note: schema stores as "contenteditor", but form may send "content-editor"
            if ($fieldType === 'content-editor' || $fieldType === 'contenteditor') {
                if (isset($fieldData['css_url']) && !empty(trim($fieldData['css_url']))) {
                    $field['css_url'] = trim($fieldData['css_url']);
                    error_log("ContentEditor: Saved css_url: " . $field['css_url']);
                } else {
                    error_log("ContentEditor: css_url not set or empty. fieldType: " . $fieldType . ", fieldData keys: " . implode(', ', array_keys($fieldData)));
                }
            }
            
            // Range fieldtype
            if ($fieldType === 'range') {
                if (isset($fieldData['min']) && $fieldData['min'] !== '') {
                    $field['min'] = (float)$fieldData['min'];
                }
                if (isset($fieldData['max']) && $fieldData['max'] !== '') {
                    $field['max'] = (float)$fieldData['max'];
                }
                if (isset($fieldData['step']) && $fieldData['step'] !== '') {
                    $field['step'] = (float)$fieldData['step'];
                }
                if (isset($fieldData['showValue'])) {
                    $field['showValue'] = !empty($fieldData['showValue']);
                }
            }
            
            // Color fieldtype
            if ($fieldType === 'color') {
                if (isset($fieldData['format'])) {
                    $field['format'] = $fieldData['format'];
                }
                if (isset($fieldData['alpha'])) {
                    $field['alpha'] = !empty($fieldData['alpha']);
                }
            }
            
            // File and Media fieldtypes
            if (in_array($fieldType, ['file', 'media'])) {
                $field['options'] = [];
                if (isset($fieldData['accept'])) {
                    $field['options']['accept'] = $fieldData['accept'];
                }
                if (isset($fieldData['multiple'])) {
                    $field['options']['multiple'] = !empty($fieldData['multiple']);
                }
            }
            
            // Gallery fieldtype
            if ($fieldType === 'gallery' && isset($fieldData['maxImages']) && $fieldData['maxImages'] !== '') {
                $field['maxImages'] = (int)$fieldData['maxImages'];
            }
            
            // Relation fieldtype
            if ($fieldType === 'relation') {
                if (isset($fieldData['collection'])) {
                    $field['collection'] = $fieldData['collection'];
                }
                if (isset($fieldData['multiple'])) {
                    $field['multiple'] = !empty($fieldData['multiple']);
                }
            }
            
            $processedFields[] = $field;
        }
    }
    
    // Save schema.json
    $schemaData = [
        'fields' => $processedFields
    ];
    
    $schemaFile = $collectionDir . '/schema.json';
    if (file_put_contents($schemaFile, json_encode($schemaData, JSON_PRETTY_PRINT)) === false) {
        throw new Exception('Failed to save schema data');
    }
    
    // Create entries directory
    $entriesDir = $collectionDir . '/entries';
    if (!is_dir($entriesDir)) {
        mkdir($entriesDir, 0755, true);
    }
    
    ob_clean();
    echo json_encode([
        'status' => 'ok',
        'message' => 'Collection saved successfully',
        'data' => [
            'slug' => $slug,
            'name' => $name
        ]
    ]);
    ob_end_flush();
    
} catch (Throwable $e) {
    error_log("Error in admin_collections.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    http_response_code(400);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    exit;
}
?> 