<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
// Input validation and sanitization helper for Birkly

function sanitize_string($value) {
    if (!is_string($value)) return '';
    return htmlspecialchars(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function sanitize_email($value) {
    if (!is_string($value)) return '';
    return filter_var(trim($value), FILTER_SANITIZE_EMAIL);
}

function sanitize_int($value) {
    return filter_var($value, FILTER_VALIDATE_INT);
}

function sanitize_url($value) {
    if (!is_string($value)) return '';
    return filter_var(trim($value), FILTER_SANITIZE_URL);
}

function sanitize_html($value, $allowed_tags = '') {
    if (!is_string($value)) return '';
    return strip_tags($value, $allowed_tags);
}

function sanitize_array($arr) {
    if (!is_array($arr)) return [];
    $out = [];
    foreach ($arr as $k => $v) {
        if (is_array($v)) {
            $out[$k] = sanitize_array($v);
        } elseif (is_bool($v)) {
            // Preserve boolean values
            $out[$k] = $v;
        } elseif (is_int($v) || is_float($v)) {
            // Preserve numeric values
            $out[$k] = $v;
        } elseif (is_null($v)) {
            // Preserve null values
            $out[$k] = $v;
        } else {
            // Only sanitize strings
            $out[$k] = sanitize_string($v);
        }
    }
    return $out;
}

function sanitize_input($input) {
    if (is_array($input)) return sanitize_array($input);
    if (is_string($input)) return sanitize_string($input);
    return $input;
}

// Output escaping functions (only define if not already defined)
// Note: escape_js is defined in core/plugins/security.php, so we don't redefine it here
if (!function_exists('escape_html')) {
    function escape_html($value) {
        if (!is_string($value)) return '';
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

// escape_js is defined in core/plugins/security.php - don't redefine it here
// If it doesn't exist, create a simple fallback
if (!function_exists('escape_js')) {
    function escape_js($value) {
        if (!is_string($value)) return '';
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}

if (!function_exists('escape_url')) {
    function escape_url($value) {
        if (!is_string($value)) return '';
        return urlencode($value);
    }
}

if (!function_exists('escape_attr')) {
    function escape_attr($value) {
        if (!is_string($value)) return '';
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

/**
 * SECURITY FIX: Set comprehensive security headers including CSP
 *
 * @param string $context Context: 'admin', 'api', or 'public'
 * @param string|null $cspNonce Per-request nonce for admin inline scripts (P16-M5)
 */
function set_security_headers($context = 'api', ?string $cspNonce = null) {
    // Content Security Policy - more restrictive for admin, permissive for public APIs
    if ($context === 'admin') {
        $nonce = trim((string) ($cspNonce ?? ''));
        if ($nonce !== '') {
            // Admin shell: inline scripts require nonce; external scripts allowed via 'self'
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" . $nonce . "' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
        } else {
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
        }
    } elseif ($context === 'api') {
        // API endpoints - very restrictive, no scripts/styles needed
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
    } else {
        // Public pages - moderate restrictions
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'self'");
    }
    
    // Other security headers
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}

/**
 * SECURITY FIX: Sanitize file path to prevent path traversal attacks
 * 
 * @param string $path User-provided path component
 * @param string $baseDir Base directory that the path must be within
 * @return string|false Sanitized path or false if invalid
 */
function sanitize_path($path, $baseDir) {
    if (!is_string($path)) {
        return false;
    }
    
    // Remove any directory traversal attempts
    $path = str_replace(['../', '..\\', '..', '/..', '\\..'], '', $path);
    
    // Remove any leading/trailing slashes
    $path = trim($path, '/\\');
    
    // Only allow alphanumeric, hyphens, underscores, and dots (for extensions)
    // This prevents any path manipulation
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $path)) {
        return false;
    }
    
    // Construct full path
    $fullPath = $baseDir . '/' . $path;
    
    // Resolve real path to prevent symlink attacks
    $realPath = realpath(dirname($fullPath));
    $realBaseDir = realpath($baseDir);
    
    if ($realPath === false || $realBaseDir === false) {
        return false;
    }
    
    // Ensure resolved path is within base directory
    if (strpos($realPath, $realBaseDir) !== 0) {
        return false;
    }
    
    return $fullPath;
}

/**
 * Validate that a file path is within allowed directory
 * 
 * @param string $filePath Full file path
 * @param string $baseDir Base directory
 * @return bool True if path is safe
 */
function validate_path_within_base($filePath, $baseDir) {
    $realPath = realpath($filePath);
    $realBaseDir = realpath($baseDir);
    
    if ($realPath === false || $realBaseDir === false) {
        return false;
    }
    
    // Ensure path is within base directory
    return strpos($realPath, $realBaseDir) === 0;
}

/**
 * Sanitize collection slug or entry ID
 * Only allows alphanumeric, hyphens, underscores
 * 
 * @param string $identifier Collection slug or entry ID
 * @return string|false Sanitized identifier or false if invalid
 */
function sanitize_identifier($identifier) {
    if (!is_string($identifier)) {
        return false;
    }
    
    // Remove any dangerous characters
    $identifier = preg_replace('/[^a-zA-Z0-9_-]/', '', $identifier);
    
    // Must not be empty
    if (empty($identifier)) {
        return false;
    }
    
    return $identifier;
}

/**
 * SECURITY FIX: Validate password strength
 * 
 * @param string $password Password to validate
 * @return array ['valid' => bool, 'message' => string]
 */
function validate_password_strength($password) {
    $minLength = 8;
    $requireStrong = true;

    if (function_exists('get_security_settings')) {
        $security = get_security_settings();
        $minLength = (int) ($security['password_min_length'] ?? 8);
        $requireStrong = (bool) ($security['require_strong_password'] ?? true);
    } else {
        $securityFile = __DIR__ . '/../settings/security.json';
        if (file_exists($securityFile)) {
            $security = json_decode(file_get_contents($securityFile), true);
            if (is_array($security)) {
                $minLength = (int) ($security['password_min_length'] ?? 8);
                $requireStrong = (bool) ($security['require_strong_password'] ?? true);
            }
        }
    }

    if (strlen($password) < $minLength) {
        return [
            'valid' => false,
            'message' => 'Password must be at least ' . $minLength . ' characters long',
        ];
    }

    if (!$requireStrong) {
        return ['valid' => true, 'message' => ''];
    }

    $hasUpper = preg_match('/[A-Z]/', $password);
    $hasLower = preg_match('/[a-z]/', $password);
    $hasNumber = preg_match('/[0-9]/', $password);
    $hasSpecial = preg_match('/[^A-Za-z0-9]/', $password);

    $complexityScore = ($hasUpper ? 1 : 0) + ($hasLower ? 1 : 0) + ($hasNumber ? 1 : 0) + ($hasSpecial ? 1 : 0);

    if ($complexityScore < 3) {
        return [
            'valid' => false,
            'message' => 'Password must contain at least 3 of the following: uppercase letters, lowercase letters, numbers, special characters',
        ];
    }

    return ['valid' => true, 'message' => ''];
} 