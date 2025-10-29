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
    
    // Prepare collection data
    $collectionData = [
        'name' => $name,
        'slug' => $slug,
        'description' => $description,
        'template' => $template,
        'sort' => $sort,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
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
            
            // Add field-specific settings
            if ($fieldData['type'] === 'select' && isset($fieldData['options'])) {
                $options = [];
                foreach ($fieldData['options'] as $optionData) {
                    if (!empty($optionData['label']) && !empty($optionData['value'])) {
                        $options[] = [
                            'label' => $optionData['label'],
                            'value' => $optionData['value']
                        ];
                    }
                }
                if (!empty($options)) {
                    $field['options'] = $options;
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