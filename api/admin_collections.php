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
    $multilanguageEnabled = !empty($_POST['multilanguage_enabled']);
    $multilanguageLanguages = $_POST['multilanguage_languages'] ?? [];
    
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
        'multilanguage' => [
            'enabled' => $multilanguageEnabled,
            'languages' => $multilanguageLanguages
        ],
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
            
            // Debug: Log field data
            error_log("Processing field ID: $fieldId, type: " . ($fieldData['type'] ?? 'missing') . ", keys: " . implode(', ', array_keys($fieldData)));
            
            $field = [
                'name' => strtolower(trim(preg_replace('/[^A-Za-z0-9_]+/', '_', $fieldData['name']))),
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