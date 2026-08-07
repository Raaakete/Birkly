<?php
/**
 * CORS (Cross-Origin Resource Sharing) Helper for Birkly CMS
 * 
 * SECURITY FIX: Replaces wildcard CORS with origin whitelist
 * Prevents CSRF attacks and unauthorized API access
 */

/**
 * Set CORS headers with origin whitelist
 * 
 * @param string $type Type of endpoint: 'public', 'admin', or 'domain'
 * @param array $allowedOrigins Optional custom allowed origins
 * @return void
 */
function set_cors_headers($type = 'public', $allowedOrigins = []) {
    // Get the origin from request
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    // Default allowed origins based on type
    if (empty($allowedOrigins)) {
        switch ($type) {
            case 'admin':
                // Admin APIs should only allow same-origin or configured admin domain
                $adminDomain = $_SERVER['HTTP_HOST'] ?? '';
                $allowedOrigins = [
                    'https://' . $adminDomain,
                    'http://' . $adminDomain,
                    'http://localhost',
                    'http://127.0.0.1'
                ];
                break;
                
            case 'domain':
                // Domain-authenticated APIs - check registered domains
                $allowedOrigins = get_registered_domains();
                break;
                
            case 'public':
            default:
                // Public APIs - allow registered domains only
                $allowedOrigins = get_registered_domains();
                // Also allow same-origin for development
                $currentHost = $_SERVER['HTTP_HOST'] ?? '';
                if ($currentHost) {
                    $allowedOrigins[] = 'http://' . $currentHost;
                    $allowedOrigins[] = 'https://' . $currentHost;
                }
                break;
        }
    }
    
    // Check if origin is allowed (exact match, www↔apex, or localhost with any port)
    $isAllowed = false;
    if ($origin && birkly_cors_origin_allowed($origin, $allowedOrigins)) {
        $isAllowed = true;
    }
    
    // For same-origin requests (no Origin header), allow if it's the same host
    if (!$origin) {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if ($referer) {
            $refererHost = parse_url($referer, PHP_URL_HOST);
            $currentHost = $_SERVER['HTTP_HOST'] ?? '';
            if ($refererHost === $currentHost) {
                $isAllowed = true;
            }
        } else {
            // No origin and no referer - likely same-origin request
            $isAllowed = true;
        }
    }
    
    // Set CORS headers only if origin is allowed
    if ($isAllowed && $origin) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
    } else {
        // For same-origin requests, don't set CORS headers
        // This is the default behavior and is secure
    }
    
    // Set other CORS headers
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
        header('Access-Control-Max-Age: 86400'); // 24 hours
        http_response_code(200);
        exit;
    }
}

/**
 * Get registered domains from settings
 * 
 * @return array List of allowed origins
 */
function get_registered_domains() {
    static $cachedDomains = null;
    
    if ($cachedDomains !== null) {
        return $cachedDomains;
    }
    
    $cachedDomains = [];
    
    try {
        require_once __DIR__ . '/storage/StorageFactory.php';
        $storage = StorageFactory::createStorage(__DIR__ . '/../settings');
        $domains = $storage->read('domains.json') ?? [];
        
        foreach ($domains as $domainKey => $domain) {
            if (!is_array($domain)) {
                continue;
            }

            $domainName = trim((string) ($domain['domain'] ?? (is_string($domainKey) ? $domainKey : '')));
            if ($domainName === '') {
                continue;
            }
            // Add both http and https versions + www↔apex variants
            $cachedDomains[] = 'https://' . $domainName;
            $cachedDomains[] = 'http://' . $domainName;
            if (strpos($domainName, 'www.') === 0) {
                $apex = substr($domainName, 4);
                if ($apex !== '') {
                    $cachedDomains[] = 'https://' . $apex;
                    $cachedDomains[] = 'http://' . $apex;
                }
            } else {
                $cachedDomains[] = 'https://www.' . $domainName;
                $cachedDomains[] = 'http://www.' . $domainName;
            }
        }
    } catch (Exception $e) {
        // If domains can't be loaded, return empty array (most restrictive)
        error_log('Failed to load registered domains: ' . $e->getMessage());
    }
    
    // Always allow localhost for development (ports matched in birkly_cors_origin_allowed)
    $cachedDomains[] = 'http://localhost';
    $cachedDomains[] = 'http://127.0.0.1';
    
    return array_unique($cachedDomains);
}

/**
 * Whether a request Origin is allowed given an allowlist of scheme://host strings.
 * Supports www↔apex expansion and localhost/127.0.0.1 with any port (P61-B5/B6).
 *
 * @param list<string> $allowedOrigins
 */
function birkly_cors_origin_allowed(string $origin, array $allowedOrigins): bool
{
    $origin = trim($origin);
    if ($origin === '') {
        return false;
    }
    if (in_array($origin, $allowedOrigins, true)) {
        return true;
    }

    $parts = parse_url($origin);
    if (!is_array($parts) || empty($parts['host'])) {
        return false;
    }
    $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
    $host = strtolower((string) $parts['host']);

    // Localhost / loopback with any port
    if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        return true;
    }

    $candidates = [$host];
    if (str_starts_with($host, 'www.')) {
        $candidates[] = substr($host, 4);
    } else {
        $candidates[] = 'www.' . $host;
    }

    foreach ($candidates as $candidate) {
        if (in_array($scheme . '://' . $candidate, $allowedOrigins, true)) {
            return true;
        }
        // Allowlist entries may omit scheme variants already expanded by get_registered_domains
        if (in_array('https://' . $candidate, $allowedOrigins, true) || in_array('http://' . $candidate, $allowedOrigins, true)) {
            return true;
        }
    }

    return false;
}

/**
 * Set dynamic CORS based on domain authentication
 * Used by domain-authenticated endpoints
 */
function set_dynamic_cors() {
    // Get domain from Referer or Origin
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    $domain = '';
    if ($origin) {
        $domain = parse_url($origin, PHP_URL_HOST);
    } elseif ($referer) {
        $domain = parse_url($referer, PHP_URL_HOST);
    }
    
    if ($domain) {
        $registeredDomains = get_registered_domains();
        $originUrl = $origin !== ''
            ? $origin
            : ((parse_url($referer, PHP_URL_SCHEME) ?: 'https') . '://' . $domain);

        if ($originUrl && birkly_cors_origin_allowed($originUrl, $registeredDomains)) {
            header('Access-Control-Allow-Origin: ' . ($origin !== '' ? $origin : $originUrl));
            header('Access-Control-Allow-Credentials: true');
        }
    }
    
    // Handle OPTIONS preflight
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
        header('Access-Control-Max-Age: 86400');
        http_response_code(200);
        exit;
    }
}
