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
        
        $licenses[$domain] = array_merge($licenseData, [
            'domain' => $domain,
            'registered_at' => date('c'),
            'status' => 'active',
            'allowed_domains' => [$domain] // Initialize with primary domain
        ]);
        
        return $this->storage->write('licenses.json', $licenses);
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

// Middleware function for API endpoints
function requireDomainAuth() {
    $domainAuth = new DomainAuth();
    
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
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        http_response_code(403);
        echo json_encode([
            'status' => 'error', 
            'message' => 'Domain not authorized. Please contact support to register your domain.',
            'code' => 'DOMAIN_NOT_LICENSED'
        ]);
        exit;
    }
    
    // Add license info to request context
    $GLOBALS['current_license'] = $validation['license'];
    
    log_security_event('domain_auth_success', [
        'domain' => $domain,
        'license_id' => $validation['license']['id'] ?? 'local_dev',
        'license_type' => $validation['license']['type'] ?? 'licensed'
    ]);
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