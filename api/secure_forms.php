<?php
// api/secure_forms.php - Secure form submission endpoint for Birkly
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    require_once 'domain_auth.php';
    setDynamicCORS();
    http_response_code(200);
    exit;
}

require_once 'domain_auth.php';
require_once 'storage.php';
require_once 'validate.php';
require_once 'rate_limit.php';
require_once 'log.php';

// Require domain authentication with write permission for form submissions
requireDomainAuth('write');

// Apply rate limiting based on domain's write limits
applyDomainRateLimit('write');

$input = sanitize_input(json_decode(file_get_contents('php://input'), true));
$action = $input['action'] ?? '';

$storage = new Storage('../content');

if ($action === 'submit_form') {
    $collection = trim($input['collection'] ?? '');
    $formData = $input['data'] ?? [];
    $formConfig = $input['config'] ?? [];
    
    if (empty($collection)) {
        echo json_encode(['status' => 'error', 'message' => 'Collection is required']);
        exit;
    }
    
    // Validate collection exists
    $collectionPath = "../content/collections/$collection";
    if (!is_dir($collectionPath)) {
        echo json_encode(['status' => 'error', 'message' => 'Collection not found']);
        exit;
    }
    
    // Check if collection allows public submissions
    $collectionConfig = $storage->read("collections/$collection/collection.json");
    if (!$collectionConfig || !($collectionConfig['allow_public_submit'] ?? false)) {
        echo json_encode(['status' => 'error', 'message' => 'Collection does not allow public submissions']);
        exit;
    }
    
    // Validate form data against schema
    $schema = $storage->read("collections/$collection/schema.json");
    if (!$schema) {
        echo json_encode(['status' => 'error', 'message' => 'Collection schema not found']);
        exit;
    }
    
    $validationResult = validateFormData($formData, $schema, $formConfig);
    if (!$validationResult['valid']) {
        echo json_encode(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validationResult['errors']]);
        exit;
    }
    
    // Rate limiting is already applied via applyDomainRateLimit('write') above
    
    // Get domain for logging
    $domain = $GLOBALS['current_domain'] ?? 'unknown';
    
    // Create entry
    $entryId = uniqid();
    $entryData = [
        'id' => $entryId,
        'created_at' => date('c'),
        'updated_at' => date('c'),
        'status' => $collectionConfig['auto_publish'] ?? false ? 'published' : 'unpublished',
        'submitted_by' => $domain,
        'submitted_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'form_config' => $formConfig
    ];
    
    // Merge form data with entry data
    $entryData = array_merge($entryData, $formData);
    
    // Save entry
    $result = $storage->write("collections/$collection/entries/$entryId.json", $entryData);
    
    if ($result) {
        // Log successful submission
        log_security_event('form_submission_success', [
            'collection' => $collection,
            'entry_id' => $entryId,
            'domain' => $domain,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        // Send notification if configured
        if ($collectionConfig['notify_on_submit'] ?? false) {
            sendFormNotification($collection, $entryData, $collectionConfig);
        }
        
        echo json_encode([
            'status' => 'ok',
            'message' => 'Form submitted successfully',
            'data' => [
                'entry_id' => $entryId,
                'status' => $entryData['status']
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save form submission']);
    }
    exit;
}

if ($action === 'get_form_config') {
    $collection = trim($input['collection'] ?? '');
    
    if (empty($collection)) {
        echo json_encode(['status' => 'error', 'message' => 'Collection is required']);
        exit;
    }
    
    // Validate collection exists and allows public submissions
    $collectionConfig = $storage->read("collections/$collection/collection.json");
    if (!$collectionConfig || !($collectionConfig['allow_public_submit'] ?? false)) {
        echo json_encode(['status' => 'error', 'message' => 'Collection not found or does not allow public submissions']);
        exit;
    }
    
    $schema = $storage->read("collections/$collection/schema.json");
    if (!$schema) {
        echo json_encode(['status' => 'error', 'message' => 'Collection schema not found']);
        exit;
    }
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'collection' => $collection,
            'schema' => $schema,
            'config' => [
                'auto_publish' => $collectionConfig['auto_publish'] ?? false,
                'notify_on_submit' => $collectionConfig['notify_on_submit'] ?? false,
                'rate_limit' => $collectionConfig['rate_limit'] ?? 10,
                'rate_limit_window' => $collectionConfig['rate_limit_window'] ?? 3600
            ]
        ]
    ]);
    exit;
}

function validateFormData($data, $schema, $config) {
    $errors = [];
    
    foreach ($schema as $fieldName => $fieldSchema) {
        $value = $data[$fieldName] ?? null;
        
        // Check required fields
        if (($fieldSchema['required'] ?? false) && (empty($value) && $value !== '0')) {
            $errors[$fieldName] = 'This field is required';
            continue;
        }
        
        // Skip validation for empty optional fields
        if (empty($value) && $value !== '0') {
            continue;
        }
        
        // Validate field type
        $fieldType = $fieldSchema['type'] ?? 'text';
        $validationResult = validateFieldValue($value, $fieldType, $fieldSchema);
        if (!$validationResult['valid']) {
            $errors[$fieldName] = $validationResult['message'];
        }
    }
    
    // Check for spam/honeypot
    if (isset($data['_honeypot']) && !empty($data['_honeypot'])) {
        $errors['_honeypot'] = 'Spam detected';
    }
    
    // Check for required config fields
    if (isset($config['required_fields'])) {
        foreach ($config['required_fields'] as $requiredField) {
            if (!isset($data[$requiredField]) || empty($data[$requiredField])) {
                $errors[$requiredField] = 'This field is required';
            }
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

function validateFieldValue($value, $type, $schema) {
    switch ($type) {
        case 'email':
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return ['valid' => false, 'message' => 'Invalid email format'];
            }
            break;
            
        case 'number':
            if (!is_numeric($value)) {
                return ['valid' => false, 'message' => 'Must be a number'];
            }
            if (isset($schema['min']) && $value < $schema['min']) {
                return ['valid' => false, 'message' => "Minimum value is {$schema['min']}"];
            }
            if (isset($schema['max']) && $value > $schema['max']) {
                return ['valid' => false, 'message' => "Maximum value is {$schema['max']}"];
            }
            break;
            
        case 'text':
        case 'textarea':
            $length = strlen($value);
            if (isset($schema['min_length']) && $length < $schema['min_length']) {
                return ['valid' => false, 'message' => "Minimum length is {$schema['min_length']} characters"];
            }
            if (isset($schema['max_length']) && $length > $schema['max_length']) {
                return ['valid' => false, 'message' => "Maximum length is {$schema['max_length']} characters"];
            }
            break;
            
        case 'select':
            if (isset($schema['options']) && !in_array($value, $schema['options'])) {
                return ['valid' => false, 'message' => 'Invalid option selected'];
            }
            break;
            
        case 'date':
            if (!strtotime($value)) {
                return ['valid' => false, 'message' => 'Invalid date format'];
            }
            break;
    }
    
    return ['valid' => true];
}

function sendFormNotification($collection, $entryData, $collectionConfig) {
    // Simple email notification - you can enhance this
    $to = $collectionConfig['notification_email'] ?? '';
    if (empty($to)) return;
    
    $subject = "New form submission: $collection";
    $message = "A new form submission has been received:\n\n";
    $message .= "Collection: $collection\n";
    $message .= "Entry ID: {$entryData['id']}\n";
    $message .= "Submitted by: {$entryData['submitted_by']}\n";
    $message .= "Date: {$entryData['created_at']}\n\n";
    $message .= "Data:\n";
    
    foreach ($entryData as $key => $value) {
        if (in_array($key, ['id', 'created_at', 'updated_at', 'status', 'submitted_by', 'submitted_ip', 'form_config'])) {
            continue;
        }
        $message .= "$key: $value\n";
    }
    
    mail($to, $subject, $message);
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']); 