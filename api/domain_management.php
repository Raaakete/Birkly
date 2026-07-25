<?php
// api/domain_management.php - Domain management API for Birkly
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Prevent any output before headers
if (ob_get_level()) {
    ob_clean();
}

header('Content-Type: application/json');

require_once 'session.php';
haru_secure_session_start();
require_once 'auth.php';
require_once 'domain_auth.php';
require_once 'validate.php';
require_once '../core/csrf.php';
require_once 'log.php';

$input = sanitize_input(json_decode(file_get_contents('php://input'), true));
$rawAction = trim($input['action'] ?? $_GET['action'] ?? '');
$action = strtolower(trim($rawAction));

error_log("Domain Management API - Action received: [" . $action . "] (raw: [" . $rawAction . "], length: " . strlen($action) . ")");

$domainAuth = new DomainAuth();

// Handle actions with if/elseif chain - only one will execute
if ($action === 'list_licenses' || $action === 'list_domains') {
    // Check admin authentication
    if (!isset($_SESSION['user']) || !checkPermission($_SESSION['user'], 'manage_content')) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $licenses = $domainAuth->getAllLicenses();
    $licenseList = [];
    
    foreach ($licenses as $domain => $domainInfo) {
        $licenseList[] = [
            'domain' => $domain,
            'status' => $domainInfo['status'] ?? 'active',
            'registered_at' => $domainInfo['registered_at'] ?? '',
            'customer_email' => $domainInfo['customer_email'] ?? '',
            'analytics_enabled' => !empty($domainInfo['analytics_enabled']),
            'permissions' => $domainInfo['permissions'] ?? [
                'permission_level' => $domainInfo['permission_level'] ?? 'read_write',
                'read_collections' => $domainInfo['read_collections'] ?? [],
                'write_collections' => $domainInfo['write_collections'] ?? [],
                'rate_limits' => $domainInfo['rate_limits'] ?? [
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
} else if ($action === 'register_domain') {
    // Check admin authentication
    if (!isset($_SESSION['user']) || !checkPermission($_SESSION['user'], 'manage_content')) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $domain = trim($input['domain'] ?? '');
    $customerEmail = trim($input['customer_email'] ?? '');
    $permissionLevel = $input['permission_level'] ?? 'read_write';
    $readLimit = intval($input['read_limit'] ?? 100);
    $writeLimit = intval($input['write_limit'] ?? 10);
    
    if (empty($domain) || empty($customerEmail)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Domain and customer email are required']);
        exit;
    }
    
    // Validate domain format
    if (!filter_var('http://' . $domain, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid domain format']);
        exit;
    }
    
    // Validate email
    if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
        exit;
    }
    
    $enableAnalytics = !empty($input['enable_analytics']) || !empty($input['analytics_enabled']);
    
    $domainData = [
        'customer_email' => $customerEmail,
        'created_by' => $_SESSION['user']['email'] ?? 'admin',
        'permission_level' => $permissionLevel,
        'rate_limits' => [
            'read' => ['limit' => $readLimit, 'window' => 60],
            'write' => ['limit' => $writeLimit, 'window' => 3600]
        ],
        'analytics_enabled' => $enableAnalytics
    ];
    
    if ($enableAnalytics) {
        require_once __DIR__ . '/../core/analytics_config.php';
        if (!birkly_analytics_set_domain_tracking($domain, true)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Could not write analytics configuration. Check that data/analytics is writable.']);
            exit;
        }
    }
    
    $result = $domainAuth->registerDomain($domain, $domainData);
    
    if ($result) {
        log_security_event('domain_registered', [
            'domain' => $domain,
            'customer_email' => $customerEmail,
            'admin' => $_SESSION['user']['email'] ?? 'admin'
        ]);
        
        echo json_encode([
            'status' => 'ok',
            'message' => 'Domain registered successfully',
            'data' => [
                'domain' => $domain
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to register domain']);
    }
    exit;
} else if ($action === 'update_license' || $action === 'update_domain') {
    error_log("Domain Management API - update_license/update_domain block executing");
    // Check admin authentication
    if (!isset($_SESSION['user']) || !checkPermission($_SESSION['user'], 'manage_content')) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $domain = trim($input['domain'] ?? '');
    $status = $input['status'] ?? '';
    $permissions = $input['permissions'] ?? [];
    
    if (empty($domain)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Domain is required']);
        exit;
    }
    
    $analyticsEnabled = null;
    if (array_key_exists('analytics_enabled', $input)) {
        $analyticsEnabled = (bool) $input['analytics_enabled'];
    } elseif (array_key_exists('enable_analytics', $input)) {
        $analyticsEnabled = !empty($input['enable_analytics']);
    }
    
    $updates = [
        'status' => $status,
        'updated_at' => date('c'),
        'updated_by' => $_SESSION['user']['email'] ?? 'admin'
    ];
    
    // Merge permissions if provided
    if (!empty($permissions)) {
        $currentLicense = $domainAuth->getAllLicenses()[$domain] ?? [];
        $currentPermissions = $currentLicense['permissions'] ?? [];
        $updates['permissions'] = array_merge($currentPermissions, $permissions);
    }
    
    // Handle analytics toggle
    if ($analyticsEnabled !== null) {
        $updates['analytics_enabled'] = $analyticsEnabled;

        require_once __DIR__ . '/../core/analytics_config.php';
        if (!birkly_analytics_set_domain_tracking($domain, $analyticsEnabled)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Could not update analytics configuration. Check that data/analytics is writable.']);
            exit;
        }
    }
    
    $result = $domainAuth->updateLicense($domain, $updates);
    
    if ($result) {
        log_security_event('domain_updated', [
            'domain' => $domain,
            'status' => $status,
            'admin' => $_SESSION['user']['email'] ?? 'admin'
        ]);
        
        // Clear any previous output
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Set success status code
        http_response_code(200);
        
        echo json_encode([
            'status' => 'ok',
            'message' => 'Domain updated successfully'
        ]);
        exit;
    } else {
        // Clear any previous output
        if (ob_get_level()) {
            ob_clean();
        }
        
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to update domain']);
        exit;
    }
} else if ($action === 'delete_license' || $action === 'delete_domain' || $action === 'revoke_license') {
    // Check admin authentication
    if (!isset($_SESSION['user']) || !checkPermission($_SESSION['user'], 'manage_content')) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $domain = trim($input['domain'] ?? '');
    if (empty($domain)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Domain is required']);
        exit;
    }
    
    $licenses = $domainAuth->getAllLicenses();
    if (isset($licenses[$domain])) {
        unset($licenses[$domain]);
        $storage = new Storage('../settings');
        $result = $storage->write('domains.json', $licenses);
        
        if ($result) {
            log_security_event('domain_deleted', [
                'domain' => $domain,
                'admin' => $_SESSION['user']['email'] ?? 'admin'
            ]);
            
            echo json_encode([
                'status' => 'ok',
                'message' => 'Domain deleted successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete domain']);
        }
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Domain not found']);
    }
    exit;
} else if ($action === 'generate_api_key') {
    // Check admin authentication
    if (!isset($_SESSION['user']) || !checkPermission($_SESSION['user'], 'manage_content')) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $domain = trim($input['domain'] ?? '');
    if (empty($domain)) {
        http_response_code(400);
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
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to generate API key']);
    }
    exit;
}

// If no action was provided at all, return error
if (empty($action)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No action specified']);
    exit;
}

// Note: With the if/elseif chain above, all valid actions have exit statements.
// If we reach here, it means an invalid action was provided, but we don't output
// anything to avoid double responses. The client will receive an empty response
// or timeout, which is acceptable for an unhandled action.

