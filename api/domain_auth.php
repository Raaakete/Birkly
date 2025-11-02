<?php
// api/domain_auth.php - Domain-based authentication for HaruCMS
require_once 'storage.php';
require_once 'validate.php';
require_once 'rate_limit.php';
require_once 'log.php';

class DomainAuth {
    private $storage;
    private $licensesFile = '../settings/licenses.json';
    
    public function __construct() {
        $this->storage = new Storage('../settings');
    }
    
    public function validateDomain($domain) {
        $licenses = $this->getLicenses();
        $domain = strtolower(trim($domain));
        
        // Check if it's a local development domain
        if ($this->isLocalDomain($domain)) {
            return ['valid' => true, 'license' => ['domain' => $domain, 'type' => 'local_dev']];
        }
        
        // Check exact domain match
        if (isset($licenses[$domain])) {
            $license = $licenses[$domain];
            if ($this->isLicenseValid($license)) {
                return ['valid' => true, 'license' => $license];
            }
        }
        
        // Check subdomain matches for all registered domains
        foreach ($licenses as $registeredDomain => $license) {
            if ($this->isSubdomain($domain, $registeredDomain) && $this->isLicenseValid($license)) {
                return ['valid' => true, 'license' => $license];
            }
        }
        
        // Check if domain is in any license's allowed_domains array
        foreach ($licenses as $licenseId => $license) {
            if (isset($license['allowed_domains']) && is_array($license['allowed_domains'])) {
                foreach ($license['allowed_domains'] as $allowedDomain) {
                    if ($this->matchesDomain($domain, $allowedDomain) && $this->isLicenseValid($license)) {
                        return ['valid' => true, 'license' => $license];
                    }
                }
            }
        }
        
        return ['valid' => false, 'reason' => 'Domain not licensed'];
    }
    
    private function isLocalDomain($domain) {
        $localDomains = [
            'localhost',
            '127.0.0.1',
            '::1',
            '0.0.0.0'
        ];
        
        // Check exact matches
        if (in_array($domain, $localDomains)) {
            return true;
        }
        
        // Check .local domains
        if (strpos($domain, '.local') !== false) {
            return true;
        }
        
        // Check .test domains
        if (strpos($domain, '.test') !== false) {
            return true;
        }
        
        // Check .dev domains (for local development)
        if (strpos($domain, '.dev') !== false) {
            return true;
        }
        
        // Check localhost with port
        if (preg_match('/^localhost:\d+$/', $domain)) {
            return true;
        }
        
        return false;
    }
    
    private function matchesDomain($requestedDomain, $allowedDomain) {
        // Exact match
        if ($requestedDomain === $allowedDomain) {
            return true;
        }
        
        // Subdomain match
        if ($this->isSubdomain($requestedDomain, $allowedDomain)) {
            return true;
        }
        
        return false;
    }
    
    private function isSubdomain($domain, $parentDomain) {
        return strpos($domain, $parentDomain) !== false && 
               (strlen($domain) > strlen($parentDomain) && 
                substr($domain, -strlen($parentDomain) - 1) === '.' . $parentDomain);
    }
    
    private function isLicenseValid($license) {
        if (empty($license['status']) || $license['status'] !== 'active') {
            return false;
        }
        
        if (!empty($license['expires_at'])) {
            $expires = strtotime($license['expires_at']);
            if ($expires && time() > $expires) {
                return false;
            }
        }
        
        return true;
    }
    
    private function getLicenses() {
        return $this->storage->read('licenses.json') ?? [];
    }
    
    public function getAllLicenses() {
        return $this->getLicenses();
    }
    
    public function registerDomain($domain, $licenseData) {
        $licenses = $this->getLicenses();
        $domain = strtolower(trim($domain));
        
        // Set default permissions if not provided
        $defaultPermissions = [
            'permission_level' => $licenseData['permission_level'] ?? 'read_write', // read_only or read_write
            'read_collections' => $licenseData['read_collections'] ?? [], // empty = all, or specific collection slugs
            'write_collections' => $licenseData['write_collections'] ?? [], // empty = all, or specific collection slugs
            'rate_limits' => [
                'read' => ['limit' => $licenseData['rate_limits']['read']['limit'] ?? 100, 'window' => $licenseData['rate_limits']['read']['window'] ?? 60],
                'write' => ['limit' => $licenseData['rate_limits']['write']['limit'] ?? 10, 'window' => $licenseData['rate_limits']['write']['window'] ?? 3600]
            ]
        ];
        
        $licenses[$domain] = array_merge($licenseData, [
            'domain' => $domain,
            'registered_at' => date('c'),
            'status' => 'active',
            'allowed_domains' => [$domain], // Initialize with primary domain
            'permissions' => $defaultPermissions
        ]);
        
        return $this->storage->write('licenses.json', $licenses);
    }
    
    // Check if domain has permission for an operation
    public function hasPermission($domain, $operation, $collection = null) {
        $validation = $this->validateDomain($domain);
        if (!$validation['valid']) {
            return ['allowed' => false, 'reason' => 'Domain not licensed'];
        }
        
        $license = $validation['license'];
        
        // Localhost always has full permissions for development
        if (($license['type'] ?? '') === 'local_dev') {
            return ['allowed' => true, 'reason' => 'local_dev'];
        }
        
        $permissions = $license['permissions'] ?? [];
        $permissionLevel = $permissions['permission_level'] ?? 'read_write';
        
        // Check read permissions
        if ($operation === 'read') {
            // If permission level is read_only or read_write, allow reads
            if ($permissionLevel === 'read_only' || $permissionLevel === 'read_write') {
                // If specific collections are set, check them
                $readCollections = $permissions['read_collections'] ?? [];
                if (!empty($readCollections) && $collection) {
                    if (in_array($collection, $readCollections)) {
                        return ['allowed' => true, 'reason' => 'collection_allowed'];
                    } else {
                        return ['allowed' => false, 'reason' => 'collection_not_allowed'];
                    }
                }
                return ['allowed' => true, 'reason' => 'read_permission'];
            }
            return ['allowed' => false, 'reason' => 'no_read_permission'];
        }
        
        // Check write permissions
        if ($operation === 'write') {
            // Only read_write allows writes
            if ($permissionLevel === 'read_write') {
                // If specific collections are set, check them
                $writeCollections = $permissions['write_collections'] ?? [];
                if (!empty($writeCollections) && $collection) {
                    if (in_array($collection, $writeCollections)) {
                        return ['allowed' => true, 'reason' => 'collection_allowed'];
                    } else {
                        return ['allowed' => false, 'reason' => 'collection_not_allowed'];
                    }
                }
                return ['allowed' => true, 'reason' => 'write_permission'];
            }
            return ['allowed' => false, 'reason' => 'no_write_permission'];
        }
        
        return ['allowed' => false, 'reason' => 'unknown_operation'];
    }
    
    // Get all registered domains for CORS
    public function getAllowedOrigins() {
        $licenses = $this->getLicenses();
        $origins = ['http://localhost', 'http://127.0.0.1'];
        
        foreach ($licenses as $domain => $license) {
            if (($license['status'] ?? '') === 'active' && $this->isLicenseValid($license)) {
                $origins[] = 'https://' . $domain;
                $origins[] = 'http://' . $domain;
                
                // Add allowed domains
                if (isset($license['allowed_domains'])) {
                    foreach ($license['allowed_domains'] as $allowedDomain) {
                        $origins[] = 'https://' . $allowedDomain;
                        $origins[] = 'http://' . $allowedDomain;
                    }
                }
            }
        }
        
        return array_unique($origins);
    }
    
    public function addAllowedDomain($licenseId, $newDomain) {
        $licenses = $this->getLicenses();
        $newDomain = strtolower(trim($newDomain));
        
        // Find license by ID
        foreach ($licenses as $domain => $license) {
            if (isset($license['id']) && $license['id'] === $licenseId) {
                if (!isset($license['allowed_domains'])) {
                    $license['allowed_domains'] = [];
                }
                
                // Check if we already have 3 domains
                if (count($license['allowed_domains']) >= 3) {
                    return ['success' => false, 'message' => 'Maximum 3 domains allowed per license'];
                }
                
                // Check if domain already exists
                if (in_array($newDomain, $license['allowed_domains'])) {
                    return ['success' => false, 'message' => 'Domain already registered'];
                }
                
                $license['allowed_domains'][] = $newDomain;
                $licenses[$domain] = $license;
                
                $result = $this->storage->write('licenses.json', $licenses);
                return [
                    'success' => $result,
                    'message' => $result ? 'Domain added successfully' : 'Failed to add domain'
                ];
            }
        }
        
        return ['success' => false, 'message' => 'License not found'];
    }
    
    public function removeAllowedDomain($licenseId, $domainToRemove) {
        $licenses = $this->getLicenses();
        $domainToRemove = strtolower(trim($domainToRemove));
        
        // Find license by ID
        foreach ($licenses as $domain => $license) {
            if (isset($license['id']) && $license['id'] === $licenseId) {
                if (!isset($license['allowed_domains'])) {
                    return ['success' => false, 'message' => 'No allowed domains found'];
                }
                
                // Don't allow removing the primary domain
                if ($domainToRemove === $domain) {
                    return ['success' => false, 'message' => 'Cannot remove primary domain'];
                }
                
                $key = array_search($domainToRemove, $license['allowed_domains']);
                if ($key !== false) {
                    unset($license['allowed_domains'][$key]);
                    $license['allowed_domains'] = array_values($license['allowed_domains']); // Reindex
                    $licenses[$domain] = $license;
                    
                    $result = $this->storage->write('licenses.json', $licenses);
                    return [
                        'success' => $result,
                        'message' => $result ? 'Domain removed successfully' : 'Failed to remove domain'
                    ];
                } else {
                    return ['success' => false, 'message' => 'Domain not found in allowed domains'];
                }
            }
        }
        
        return ['success' => false, 'message' => 'License not found'];
    }
    
    public function generateApiKey($domain) {
        $licenses = $this->getLicenses();
        if (!isset($licenses[$domain])) {
            return false;
        }
        
        $apiKey = bin2hex(random_bytes(32));
        $licenses[$domain]['api_key'] = $apiKey;
        $licenses[$domain]['api_key_generated_at'] = date('c');
        
        return $this->storage->write('licenses.json', $licenses) ? $apiKey : false;
    }
    
    public function validateApiKey($apiKey) {
        $licenses = $this->getLicenses();
        
        foreach ($licenses as $domain => $license) {
            if (isset($license['api_key']) && $license['api_key'] === $apiKey) {
                if ($this->isLicenseValid($license)) {
                    return ['valid' => true, 'domain' => $domain, 'license' => $license];
                }
                return ['valid' => false, 'reason' => 'License expired or inactive'];
            }
        }
        
        return ['valid' => false, 'reason' => 'Invalid API key'];
    }
    
    public function updateLicense($domain, $updates) {
        $licenses = $this->getLicenses();
        if (!isset($licenses[$domain])) {
            return false;
        }
        
        $licenses[$domain] = array_merge($licenses[$domain], $updates);
        return $this->storage->write('licenses.json', $licenses);
    }
}

// Set dynamic CORS headers based on registered domains
function setDynamicCORS() {
    $domainAuth = new DomainAuth();
    
    // Get requesting origin
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (!$origin) {
        // No origin header, skip CORS (same-origin request)
        return;
    }
    
    $allowedOrigins = $domainAuth->getAllowedOrigins();
    $parsedOrigin = parse_url($origin);
    $originHost = ($parsedOrigin['scheme'] ?? 'http') . '://' . ($parsedOrigin['host'] ?? '');
    
    // Check if origin matches any allowed origin
    $isAllowed = false;
    foreach ($allowedOrigins as $allowed) {
        // Match exact origin or allow localhost with any port
        if ($originHost === $allowed || 
            (strpos($allowed, 'localhost') !== false && strpos($origin, 'localhost') !== false)) {
            $isAllowed = true;
            break;
        }
    }
    
    if ($isAllowed) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
    } else {
        // For localhost dev, allow it anyway
        if (strpos($origin, 'localhost') !== false || strpos($origin, '127.0.0.1') !== false) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
        }
    }
    
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
    header('Access-Control-Max-Age: 86400');
}

// Middleware function for API endpoints
function requireDomainAuth($operation = 'read', $collection = null) {
    $domainAuth = new DomainAuth();
    
    // Set dynamic CORS first
    setDynamicCORS();
    
    // Get domain from Referer header or Origin header
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    $domain = '';
    if ($origin) {
        $domain = parse_url($origin, PHP_URL_HOST);
    } elseif ($referer) {
        $domain = parse_url($referer, PHP_URL_HOST);
    }
    
    if (!$domain) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Domain validation required']);
        exit;
    }
    
    $validation = $domainAuth->validateDomain($domain);
    if (!$validation['valid']) {
        log_security_event('domain_auth_failed', [
            'domain' => $domain,
            'reason' => $validation['reason'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'operation' => $operation
        ]);
        
        http_response_code(403);
        echo json_encode([
            'status' => 'error', 
            'message' => 'Domain not authorized. Please register your domain in the admin panel.',
            'code' => 'DOMAIN_NOT_LICENSED'
        ]);
        exit;
    }
    
    // Check permissions for the operation
    $permissionCheck = $domainAuth->hasPermission($domain, $operation, $collection);
    if (!$permissionCheck['allowed']) {
        log_security_event('domain_permission_denied', [
            'domain' => $domain,
            'reason' => $permissionCheck['reason'],
            'operation' => $operation,
            'collection' => $collection,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        http_response_code(403);
        echo json_encode([
            'status' => 'error', 
            'message' => 'Permission denied: ' . $permissionCheck['reason'],
            'code' => 'PERMISSION_DENIED'
        ]);
        exit;
    }
    
    // Add license info to request context
    $GLOBALS['current_license'] = $validation['license'];
    $GLOBALS['current_domain'] = $domain;
    $GLOBALS['current_operation'] = $operation;
    
    log_security_event('domain_auth_success', [
        'domain' => $domain,
        'license_id' => $validation['license']['id'] ?? 'local_dev',
        'license_type' => $validation['license']['type'] ?? 'licensed',
        'operation' => $operation
    ]);
}

/**
 * Apply rate limiting based on domain and operation type
 */
function applyDomainRateLimit($operation = 'read') {
    $license = $GLOBALS['current_license'] ?? [];
    $domain = $GLOBALS['current_domain'] ?? 'unknown';
    
    // Localhost has higher limits for development
    if (($license['type'] ?? '') === 'local_dev') {
        $readLimit = 1000;   // 1000 reads/minute for localhost
        $writeLimit = 100;   // 100 writes/hour for localhost
    } else {
        // Get rate limits from license permissions
        $permissions = $license['permissions'] ?? [];
        $rateLimits = $permissions['rate_limits'] ?? [];
        
        $readLimit = $rateLimits['read']['limit'] ?? 100;   // Default: 100 reads/minute
        $writeLimit = $rateLimits['write']['limit'] ?? 10;  // Default: 10 writes/hour
    }
    
    // Create rate limiter based on operation
    require_once __DIR__ . '/rate_limit.php';
    
    if ($operation === 'read') {
        $rateLimiter = new RateLimiter("api_read_$domain", $readLimit, 60); // per minute
    } else {
        $rateLimiter = new RateLimiter("api_write_$domain", $writeLimit, 3600); // per hour
    }
    
    if (!$rateLimiter->check()) {
        require_once __DIR__ . '/log.php';
        log_security_event('rate_limited', [
            'domain' => $domain,
            'operation' => $operation,
            'limit' => $operation === 'read' ? $readLimit : $writeLimit,
            'window' => $operation === 'read' ? 60 : 3600,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        $retryAfter = $rateLimiter->getRetryAfter();
        http_response_code(429);
        echo json_encode([
            'status' => 'error',
            'message' => "Rate limit exceeded. Please try again later.",
            'code' => 'RATE_LIMIT_EXCEEDED',
            'retry_after' => $retryAfter,
            'limit' => $operation === 'read' ? $readLimit : $writeLimit,
            'window' => $operation === 'read' ? 60 : 3600
        ]);
        exit;
    }
}

// Alternative: API key authentication
function requireApiKeyAuth() {
    $domainAuth = new DomainAuth();
    
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
    if (!$apiKey) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'API key required']);
        exit;
    }
    
    $validation = $domainAuth->validateApiKey($apiKey);
    if (!$validation['valid']) {
        log_security_event('api_key_auth_failed', [
            'reason' => $validation['reason'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        http_response_code(401);
        echo json_encode([
            'status' => 'error', 
            'message' => 'Invalid API key',
            'code' => 'INVALID_API_KEY'
        ]);
        exit;
    }
    
    $GLOBALS['current_license'] = $validation['license'];
    
    log_security_event('api_key_auth_success', [
        'domain' => $validation['domain'],
        'license_id' => $validation['license']['id'] ?? 'unknown'
    ]);
} 