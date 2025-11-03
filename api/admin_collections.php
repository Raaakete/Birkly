<?php
// Only set headers if this file is being called directly (not included)
// When included via admin/index.php, headers are already sent, so skip
if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    header('Content-Type: application/json');
    
    // Handle CORS
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Debug: Log the request
error_log("admin_collections.php called with method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get form data
    $name = $_POST['name'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $description = $_POST['description'] ?? '';
    $template = $_POST['template'] ?? '';
    $sort = $_POST['sort'] ?? 'created_desc';
    $fields = $_POST['fields'] ?? [];
    $entryMethod = $_POST['entry_method'] ?? 'admin_only';
    $apiKey = $_POST['api_key'] ?? '';
    $apiSettings = $_POST['api_settings'] ?? [];
    
    error_log("Processing form data:");
    error_log("name: " . $name);
    error_log("slug: " . $slug);
    error_log("description: " . $description);
    error_log("template: " . $template);
    error_log("sort: " . $sort);
    error_log("fields: " . print_r($fields, true));
    
    // Validate required fields
    if (empty($name)) {
        throw new Exception('Collection name is required');
    }
    
    // Generate slug if not provided
    if (empty($slug)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        $slug = trim($slug, '-');
    }
    
    error_log("Generated slug: " . $slug);
    
    // Validate slug format
    error_log("Validating slug format: " . $slug);
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        error_log("Slug validation failed for: " . $slug);
        throw new Exception('Slug can only contain lowercase letters, numbers, and hyphens');
    }
    
    error_log("Slug validation passed");
    
    // Check if collection already exists (for updates)
    $existingCollection = null;
    $collectionsPath = __DIR__ . '/../content/collections';
    $collectionDir = $collectionsPath . '/' . $slug;
    
    error_log("Collection path: " . $collectionsPath);
    error_log("Collection dir: " . $collectionDir);
    error_log("Collections path exists: " . (is_dir($collectionsPath) ? 'yes' : 'no'));
    error_log("Collection dir exists: " . (is_dir($collectionDir) ? 'yes' : 'no'));
    
    if (is_dir($collectionDir)) {
        $collectionFile = $collectionDir . '/collection.json';
        error_log("Collection file: " . $collectionFile);
        error_log("Collection file exists: " . (file_exists($collectionFile) ? 'yes' : 'no'));
        if (file_exists($collectionFile)) {
            $existingCollection = json_decode(file_get_contents($collectionFile), true);
            error_log("Existing collection found: " . print_r($existingCollection, true));
        }
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
        $apiKey = 'haru_';
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
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Add API key and settings if Public API is enabled
    if ($entryMethod === 'public_api') {
        $collectionData['api_key'] = $apiKey;
        $collectionData['api_settings'] = [
            'auto_publish' => !empty($apiSettings['auto_publish']),
            'rate_limit' => isset($apiSettings['rate_limit']) ? (int)$apiSettings['rate_limit'] : 10,
            'notification_email' => $apiSettings['notification_email'] ?? '',
            'captcha_provider' => $apiSettings['captcha_provider'] ?? '',
            'captcha_site_key' => $apiSettings['captcha_site_key'] ?? '',
            'captcha_secret_key' => $apiSettings['captcha_secret_key'] ?? ''
        ];
    } else {
        // Remove API settings if switching back to Admin Only
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
            
            $field = [
                'name' => strtolower(trim(preg_replace('/[^A-Za-z0-9_]+/', '_', $fieldData['name']))),
                'label' => $fieldData['label'] ?? $fieldData['name'],
                'type' => $fieldData['type'],
                'required' => !empty($fieldData['required']),
                'id' => $fieldId
            ];
            
            // Add field-specific settings based on fieldtype
            if ($fieldData['type'] === 'select') {
                $options = [];
                
                // Handle options_text format (label|value per line)
                if (!empty($fieldData['options_text'])) {
                    $lines = explode("\n", $fieldData['options_text']);
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (empty($line)) continue;
                        
                        // Parse label|value format
                        if (strpos($line, '|') !== false) {
                            list($label, $value) = explode('|', $line, 2);
                            $options[] = [
                                'label' => trim($label),
                                'value' => trim($value)
                            ];
                        } else {
                            // If no pipe, use line as both label and value
                            $options[] = [
                                'label' => $line,
                                'value' => strtolower(preg_replace('/[^a-z0-9]+/', '-', $line))
                            ];
                        }
                    }
                }
                
                // Also handle array format from existing data
                if (isset($fieldData['options']) && is_array($fieldData['options'])) {
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
            }
            
            // Add other fieldtype-specific settings
            if ($fieldData['type'] === 'number') {
                if (isset($fieldData['min'])) {
                    $field['min'] = $fieldData['min'];
                }
                if (isset($fieldData['max'])) {
                    $field['max'] = $fieldData['max'];
                }
                if (isset($fieldData['step'])) {
                    $field['step'] = $fieldData['step'];
                }
            }
            
            if ($fieldData['type'] === 'text' || $fieldData['type'] === 'textarea') {
                if (isset($fieldData['placeholder'])) {
                    $field['placeholder'] = $fieldData['placeholder'];
                }
                if (isset($fieldData['minLength'])) {
                    $field['minLength'] = (int)$fieldData['minLength'];
                }
                if (isset($fieldData['maxLength'])) {
                    $field['maxLength'] = (int)$fieldData['maxLength'];
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
    
    echo json_encode([
        'status' => 'ok',
        'message' => 'Collection saved successfully',
        'data' => [
            'slug' => $slug,
            'name' => $name
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?> 