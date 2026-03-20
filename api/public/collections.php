<?php
/**
 * Public API Endpoint for Collection Entries
 * POST /api/public/collections.php?collection={slug}
 * 
 * Allows external submissions to collections with entry_method = 'public_api'
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');

header('Content-Type: application/json');

// SECURITY FIX: Replace wildcard CORS with origin whitelist
require_once __DIR__ . '/../../core/cors.php';
set_cors_headers('public');

require_once __DIR__ . '/../rate_limit.php';
require_once __DIR__ . '/../log.php';
require_once __DIR__ . '/../validate.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Get collection slug from query parameter
$collectionSlug = $_GET['collection'] ?? '';
if (empty($collectionSlug)) {
    respond(['status' => 'error', 'message' => 'Collection parameter required'], 400);
}

// Get API key from header or query parameter
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
if (empty($apiKey)) {
    respond(['status' => 'error', 'message' => 'API key required'], 401);
}

// Load collection configuration
// SECURITY FIX: Sanitize collection slug to prevent path traversal
require_once __DIR__ . '/validate.php';
$collectionSlug = sanitize_identifier($collectionSlug);
if ($collectionSlug === false) {
    respond(['status' => 'error', 'message' => 'Invalid collection identifier'], 400);
}

$baseDir = __DIR__ . '/../../content/collections';
$collectionFile = sanitize_path($collectionSlug . '/collection.json', $baseDir);
if ($collectionFile === false || !file_exists($collectionFile)) {
    respond(['status' => 'error', 'message' => 'Collection not found'], 404);
}

$collectionData = json_decode(file_get_contents($collectionFile), true);
if (!$collectionData) {
    respond(['status' => 'error', 'message' => 'Invalid collection configuration'], 500);
}

// Verify entry method
if (($collectionData['entry_method'] ?? 'admin_only') !== 'public_api') {
    respond(['status' => 'error', 'message' => 'Public API not enabled for this collection'], 403);
}

// Verify API key
if (($collectionData['api_key'] ?? '') !== $apiKey) {
    log_security_event('api_key_auth_failed', [
        'collection' => $collectionSlug,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'provided_key' => substr($apiKey, 0, 10) . '...'
    ]);
    respond(['status' => 'error', 'message' => 'Invalid API key'], 401);
}

// Get API settings
$apiSettings = $collectionData['api_settings'] ?? [];
$rateLimit = (int)($apiSettings['rate_limit'] ?? 10);
$autoPublish = !empty($apiSettings['auto_publish']);
$notificationEmail = $apiSettings['notification_email'] ?? '';

// Apply rate limiting per API key
$rateLimiter = new RateLimiter('public_api_' . $apiKey, $rateLimit, 3600); // per hour
if (!$rateLimiter->check()) {
    log_security_event('rate_limited', [
        'action' => 'public_api_submit',
        'collection' => $collectionSlug,
        'api_key' => substr($apiKey, 0, 10) . '...',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'limit' => $rateLimit
    ]);
    $retryAfter = $rateLimiter->getRetryAfter();
    respond([
        'status' => 'error',
        'message' => 'Rate limit exceeded. Please try again later.',
        'retry_after' => $retryAfter,
        'limit' => $rateLimit
    ], 429);
}

// Load collection schema
// SECURITY FIX: Use sanitized path
$schemaFile = sanitize_path($collectionSlug . '/schema.json', $baseDir);
if ($schemaFile === false || !file_exists($schemaFile)) {
    respond(['status' => 'error', 'message' => 'Collection schema not found'], 404);
}
if (!file_exists($schemaFile)) {
    respond(['status' => 'error', 'message' => 'Collection schema not found'], 500);
}

$schemaData = json_decode(file_get_contents($schemaFile), true);
if (!$schemaData || !isset($schemaData['fields'])) {
    respond(['status' => 'error', 'message' => 'Invalid collection schema'], 500);
}

// Parse request body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    respond(['status' => 'error', 'message' => 'Invalid JSON in request body'], 400);
}

// Validate CAPTCHA if configured
$captchaProvider = $apiSettings['captcha_provider'] ?? '';
$captchaSiteKey = $apiSettings['captcha_site_key'] ?? '';
$captchaSecretKey = $apiSettings['captcha_secret_key'] ?? '';

if (!empty($captchaProvider) && !empty($captchaSiteKey) && !empty($captchaSecretKey)) {
    $captchaToken = $input['captcha_token'] ?? '';
    if (empty($captchaToken)) {
        respond(['status' => 'error', 'message' => 'CAPTCHA token required'], 400);
    }
    
    $captchaValid = false;
    if ($captchaProvider === 'recaptcha_v3') {
        // Verify Google reCAPTCHA v3
        $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
        $postData = http_build_query([
            'secret' => $captchaSecretKey,
            'response' => $captchaToken,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
        
        $ch = curl_init($verifyUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        $captchaValid = !empty($result['success']) && ($result['score'] ?? 0) >= 0.5;
    } elseif ($captchaProvider === 'hcaptcha') {
        // Verify hCaptcha
        $verifyUrl = 'https://hcaptcha.com/siteverify';
        $postData = http_build_query([
            'secret' => $captchaSecretKey,
            'response' => $captchaToken,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
        
        $ch = curl_init($verifyUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        $captchaValid = !empty($result['success']);
    }
    
    if (!$captchaValid) {
        log_security_event('captcha_failed', [
            'collection' => $collectionSlug,
            'provider' => $captchaProvider,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        respond(['status' => 'error', 'message' => 'CAPTCHA verification failed'], 400);
    }
}

// Check honeypot (always enabled, even without CAPTCHA)
if (!empty($input['_honeypot'])) {
    log_security_event('honeypot_triggered', [
        'collection' => $collectionSlug,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    respond(['status' => 'error', 'message' => 'Bot detected'], 400);
}

// Extract entry data (exclude metadata fields)
$entryData = [];
$errors = [];

foreach ($schemaData['fields'] as $field) {
    $fieldName = $field['name'] ?? '';
    $fieldLabel = $field['label'] ?? $fieldName;
    $fieldType = $field['type'] ?? 'text';
    $required = !empty($field['required']);
    
    $value = $input[$fieldName] ?? null;
    
    // Check required fields
    if ($required && (empty($value) && $value !== '0' && $value !== 0)) {
        $errors[$fieldName] = $fieldLabel . ' is required';
        continue;
    }
    
    // Skip empty optional fields
    if (empty($value) && $value !== '0' && $value !== 0) {
        continue;
    }
    
    // Validate field type
    $validationResult = validateFieldValue($value, $fieldType, $field);
    if (!$validationResult['valid']) {
        $errors[$fieldName] = $validationResult['message'];
        continue;
    }
    
    // Sanitize value
    if ($fieldType === 'editor' || $fieldType === 'wysiwyg') {
        // Allow HTML for editor fields
        $entryData[$fieldName] = $value;
    } else {
        $entryData[$fieldName] = sanitize_string($value);
    }
}

if (!empty($errors)) {
    respond(['status' => 'error', 'message' => 'Validation failed', 'errors' => $errors], 422);
}

// Create entry
$entryId = uniqid('entry_');
$entryData['id'] = $entryId;
// Auto-fill title with entry ID if not provided
$entryData['title'] = $entryData['title'] ?? $entryId;
$entryData['status'] = $autoPublish ? 'published' : 'draft';
$entryData['created_at'] = date('Y-m-d H:i:s');
$entryData['updated_at'] = date('Y-m-d H:i:s');
$entryData['submitted_via'] = 'public_api';
$entryData['submitted_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Save entry
// SECURITY FIX: Use sanitized path
$entriesDir = sanitize_path($collectionSlug . '/entries', $baseDir);
if ($entriesDir === false) {
    respond(['status' => 'error', 'message' => 'Invalid collection path'], 400);
}
if (!is_dir($entriesDir)) {
    mkdir($entriesDir, 0755, true);
}

$entryFile = $entriesDir . '/' . $entryId . '.json';
if (file_put_contents($entryFile, json_encode($entryData, JSON_PRETTY_PRINT)) === false) {
    respond(['status' => 'error', 'message' => 'Failed to save entry'], 500);
}

// Log successful submission
log_security_event('public_api_submission', [
    'collection' => $collectionSlug,
    'entry_id' => $entryId,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'status' => $entryData['status']
]);

// Send notification email if configured
if (!empty($notificationEmail)) {
    $subject = 'New Submission: ' . ($collectionData['name'] ?? $collectionSlug);
    $body = "A new entry was submitted to collection: " . ($collectionData['name'] ?? $collectionSlug) . "\n\n";
    $body .= "Entry ID: $entryId\n";
    $body .= "Status: " . $entryData['status'] . "\n";
    $body .= "Submitted: " . $entryData['created_at'] . "\n";
    $body .= "IP: " . $entryData['submitted_ip'] . "\n\n";
    $body .= "Entry Data:\n";
    foreach ($entryData as $key => $value) {
        if (in_array($key, ['id', 'status', 'created_at', 'updated_at', 'submitted_via', 'submitted_ip'])) {
            continue;
        }
        $body .= "$key: " . (is_array($value) ? json_encode($value) : $value) . "\n";
    }
    
    $headers = 'From: noreply@' . ($_SERVER['SERVER_NAME'] ?? 'birkly.local');
    @mail($notificationEmail, $subject, $body, $headers);
}

// Return success response
respond([
    'status' => 'ok',
    'message' => 'Entry created successfully',
    'data' => [
        'entry_id' => $entryId,
        'status' => $entryData['status']
    ]
]);

// Helper function to validate field values
function validateFieldValue($value, $type, $field) {
    switch ($type) {
        case 'email':
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return ['valid' => false, 'message' => 'Invalid email format'];
            }
            break;
            
        case 'number':
        case 'integer':
            if (!is_numeric($value)) {
                return ['valid' => false, 'message' => 'Must be a number'];
            }
            if (isset($field['min']) && $value < $field['min']) {
                return ['valid' => false, 'message' => "Minimum value is {$field['min']}"];
            }
            if (isset($field['max']) && $value > $field['max']) {
                return ['valid' => false, 'message' => "Maximum value is {$field['max']}"];
            }
            break;
            
        case 'text':
        case 'textarea':
            $length = strlen($value);
            if (isset($field['min_length']) && $length < $field['min_length']) {
                return ['valid' => false, 'message' => "Minimum length is {$field['min_length']} characters"];
            }
            if (isset($field['max_length']) && $length > $field['max_length']) {
                return ['valid' => false, 'message' => "Maximum length is {$field['max_length']} characters"];
            }
            break;
            
        case 'select':
            if (isset($field['options']) && is_array($field['options'])) {
                $validValues = array_column($field['options'], 'value');
                if (!in_array($value, $validValues)) {
                    return ['valid' => false, 'message' => 'Invalid option selected'];
                }
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

