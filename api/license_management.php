<?php
// api/license_management.php - License management API for HaruCMS
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

header('Content-Type: application/json');

require_once 'session.php';
haru_secure_session_start();
require_once 'auth.php';
require_once 'domain_auth.php';
require_once 'validate.php';
require_once '../core/csrf.php';
require_once 'log.php';

$input = sanitize_input(json_decode(file_get_contents('php://input'), true));
$action = $input['action'] ?? $_GET['action'] ?? '';

$domainAuth = new DomainAuth();

if ($action === 'list_licenses') {
    // Check admin authentication
    if (!isset($_SESSION['user']) || !checkPermission($_SESSION['user'], 'manage_content')) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $licenses = $domainAuth->getAllLicenses();
    $licenseList = [];
    
    foreach ($licenses as $domain => $license) {
        $licenseList[] = [
            'domain' => $domain,
            'license_id' => $license['id'] ?? '',
            'status' => $license['status'] ?? 'inactive',
            'registered_at' => $license['registered_at'] ?? '',
            'expires_at' => $license['expires_at'] ?? null,
            'customer_email' => $license['customer_email'] ?? '',
            'plan' => $license['plan'] ?? 'basic',
            'has_api_key' => !empty($license['api_key']),
            'features' => $license['features'] ?? [],
            'allowed_domains' => $license['allowed_domains'] ?? [$domain],
            'domain_count' => count($license['allowed_domains'] ?? [$domain]),
            'permissions' => $license['permissions'] ?? [
                'permission_level' => $license['permission_level'] ?? 'read_write',
                'read_collections' => $license['read_collections'] ?? [],
                'write_collections' => $license['write_collections'] ?? [],
                'rate_limits' => $license['rate_limits'] ?? [
                    'read' => ['limit' => 100, 'window' => 60],
                    'write' => ['limit' => 10, 'window' => 3600]
                ]
            ]
        ];
    }
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'licenses' => $licenseList
        ]
    ]);
    exit;
}

if ($action === 'register_domain') {
    // Check admin authentication
    if (!isset($_SESSION['user']) || !checkPermission($_SESSION['user'], 'manage_content')) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $domain = trim($input['domain'] ?? '');
    $customerEmail = trim($input['customer_email'] ?? '');
    $plan = $input['plan'] ?? 'basic';
    $expiresAt = $input['expires_at'] ?? null;
    $additionalDomains = $input['additional_domains'] ?? [];
    $permissionLevel = $input['permission_level'] ?? 'read_write';
    $readLimit = intval($input['read_limit'] ?? $input['rate_limits']['read']['limit'] ?? 100);
    $writeLimit = intval($input['write_limit'] ?? $input['rate_limits']['write']['limit'] ?? 10);
    
    if (empty($domain) || empty($customerEmail)) {
        echo json_encode(['status' => 'error', 'message' => 'Domain and customer email are required']);
        exit;
    }
    
    // Validate domain format
    if (!filter_var('http://' . $domain, FILTER_VALIDATE_URL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid domain format']);
        exit;
    }
    
    // Validate email
    if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
        exit;
    }
    
    // Validate additional domains (max 2 additional = 3 total)
    if (count($additionalDomains) > 2) {
        echo json_encode(['status' => 'error', 'message' => 'Maximum 3 domains allowed per license (1 primary + 2 additional)']);
        exit;
    }
    
    // Validate additional domains format
    foreach ($additionalDomains as $addDomain) {
        if (!filter_var('http://' . $addDomain, FILTER_VALIDATE_URL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid additional domain format: ' . $addDomain]);
            exit;
        }
    }
    
    $licenseData = [
        'id' => 'license_' . uniqid(),
        'customer_email' => $customerEmail,
        'plan' => $plan,
        'expires_at' => $expiresAt,
        'features' => getPlanFeatures($plan),
        'created_by' => $_SESSION['user']['email'] ?? 'admin',
        'allowed_domains' => array_merge([$domain], $additionalDomains),
        'permission_level' => $permissionLevel,
        'rate_limits' => [
            'read' => ['limit' => $readLimit, 'window' => 60],
            'write' => ['limit' => $writeLimit, 'window' => 3600]
        ]
    ];
    
    $result = $domainAuth->registerDomain($domain, $licenseData);
    
    if ($result) {
        log_security_event('license_registered', [
            'domain' => $domain,
            'customer_email' => $customerEmail,
            'plan' => $plan,
            'admin' => $_SESSION['user']['email'] ?? 'admin'
        ]);
        
        echo json_encode([
            'status' => 'ok',
            'message' => 'Domain registered successfully',
            'data' => [
                'domain' => $domain,
                'license_id' => $licenseData['id']
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to register domain']);
    }
    exit;
}

if ($action === 'add_domain') {
    // Check admin authentication
    if (!isset($_SESSION['user']) || !checkPermission($_SESSION['user'], 'manage_content')) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $licenseId = trim($input['license_id'] ?? '');
    $newDomain = trim($input['domain'] ?? '');
    
    if (empty($licenseId) || empty($newDomain)) {
        echo json_encode(['status' => 'error', 'message' => 'License ID and domain are required']);
        exit;
    }
    
    // Validate domain format
    if (!filter_var('http://' . $newDomain, FILTER_VALIDATE_URL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid domain format']);
        exit;
    }
    
    $result = $domainAuth->addAllowedDomain($licenseId, $newDomain);
    
    if ($result['success']) {
        log_security_event('domain_added', [
            'license_id' => $licenseId,
            'domain' => $newDomain,
            'admin' => $_SESSION['user']['email'] ?? 'admin'
        ]);
        
        echo json_encode([
            'status' => 'ok',
            'message' => $result['message']
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => $result['message']
        ]);
    }
    exit;
}

if ($action === 'remove_domain') {
    // Check admin authentication
    if (!isset($_SESSION['user']) || !checkPermission($_SESSION['user'], 'manage_content')) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $licenseId = trim($input['license_id'] ?? '');
    $domainToRemove = trim($input['domain'] ?? '');
    
    if (empty($licenseId) || empty($domainToRemove)) {
        echo json_encode(['status' => 'error', 'message' => 'License ID and domain are required']);
        exit;
    }
    
    $result = $domainAuth->removeAllowedDomain($licenseId, $domainToRemove);
    
    if ($result['success']) {
        log_security_event('domain_removed', [
            'license_id' => $licenseId,
            'domain' => $domainToRemove,
            'admin' => $_SESSION['user']['email'] ?? 'admin'
        ]);
        
        echo json_encode([
            'status' => 'ok',
            'message' => $result['message']
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => $result['message']
        ]);
    }
    exit;
}

if ($action === 'generate_api_key') {
    // Check admin authentication
    if (!isset($_SESSION['user']) || !checkPermission($_SESSION['user'], 'manage_content')) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $domain = trim($input['domain'] ?? '');
    if (empty($domain)) {
        echo json_encode(['status' => 'error', 'message' => 'Domain is required']);
        exit;
    }
    
    $apiKey = $domainAuth->generateApiKey($domain);
    
    if ($apiKey) {
        log_security_event('api_key_generated', [
            'domain' => $domain,
            'admin' => $_SESSION['user']['email'] ?? 'admin'
        ]);
        
        echo json_encode([
            'status' => 'ok',
            'message' => 'API key generated successfully',
            'data' => [
                'domain' => $domain,
                'api_key' => $apiKey
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to generate API key']);
    }
    exit;
}

if ($action === 'update_license') {
    // Check admin authentication
    if (!isset($_SESSION['user']) || !checkPermission($_SESSION['user'], 'manage_content')) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $domain = trim($input['domain'] ?? '');
    $status = $input['status'] ?? '';
    $expiresAt = $input['expires_at'] ?? null;
    $plan = $input['plan'] ?? '';
    $permissions = $input['permissions'] ?? [];
    
    if (empty($domain)) {
        echo json_encode(['status' => 'error', 'message' => 'Domain is required']);
        exit;
    }
    
    $updates = [
        'status' => $status,
        'expires_at' => $expiresAt,
        'plan' => $plan,
        'features' => getPlanFeatures($plan),
        'updated_at' => date('c'),
        'updated_by' => $_SESSION['user']['email'] ?? 'admin'
    ];
    
    // Merge permissions if provided
    if (!empty($permissions)) {
        $currentLicense = $domainAuth->getAllLicenses()[$domain] ?? [];
        $currentPermissions = $currentLicense['permissions'] ?? [];
        $updates['permissions'] = array_merge($currentPermissions, $permissions);
    }
    
    $result = $domainAuth->updateLicense($domain, $updates);
    
    if ($result) {
        log_security_event('license_updated', [
            'domain' => $domain,
            'status' => $status,
            'admin' => $_SESSION['user']['email'] ?? 'admin'
        ]);
        
        echo json_encode([
            'status' => 'ok',
            'message' => 'License updated successfully'
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update license']);
    }
    exit;
}

if ($action === 'delete_license' || $action === 'revoke_license') {
    // Check admin authentication
    if (!isset($_SESSION['user']) || !checkPermission($_SESSION['user'], 'manage_content')) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $domain = trim($input['domain'] ?? '');
    if (empty($domain)) {
        echo json_encode(['status' => 'error', 'message' => 'Domain is required']);
        exit;
    }
    
    $licenses = $domainAuth->getAllLicenses();
    if (isset($licenses[$domain])) {
        unset($licenses[$domain]);
        $storage = new Storage('../settings');
        $result = $storage->write('licenses.json', $licenses);
        
        if ($result) {
            log_security_event('license_deleted', [
                'domain' => $domain,
                'admin' => $_SESSION['user']['email'] ?? 'admin'
            ]);
            
            echo json_encode([
                'status' => 'ok',
                'message' => 'Domain deleted successfully'
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete domain']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Domain not found']);
    }
    exit;
}

function getPlanFeatures($plan) {
    $features = [
        'basic' => ['content_read', 'basic_api'],
        'pro' => ['content_read', 'content_write', 'media_upload', 'advanced_api', 'priority_support'],
        'enterprise' => ['content_read', 'content_write', 'media_upload', 'advanced_api', 'priority_support', 'custom_integration', 'white_label']
    ];
    
    return $features[$plan] ?? $features['basic'];
}

// If no action matches, return error
if (!empty($action)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
} 