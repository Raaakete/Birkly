<?php
/**
 * Security Functions for Birkly Plugins
 * 
 * Provides input validation, output sanitization, and security utilities.
 */

/**
 * Validate and sanitize input data
 * 
 * @param mixed $data Input data
 * @param string $type Data type ('string', 'int', 'float', 'email', 'url', 'array', 'json')
 * @param array $options Validation options:
 *   - 'required' (bool): Whether field is required
 *   - 'min_length' (int): Minimum length for strings
 *   - 'max_length' (int): Maximum length for strings
 *   - 'min' (int/float): Minimum value for numbers
 *   - 'max' (int/float): Maximum value for numbers
 *   - 'pattern' (string): Regex pattern for validation
 *   - 'allowed_tags' (array): Allowed HTML tags (for HTML content)
 * 
 * @return mixed Sanitized data or false on validation failure
 */
function validate_plugin_input($data, $type = 'string', $options = []) {
    // Check required
    if (!empty($options['required']) && ($data === null || $data === '')) {
        return false;
    }
    
    // Handle null/empty for non-required fields
    if (($data === null || $data === '') && empty($options['required'])) {
        return $data;
    }
    
    switch ($type) {
        case 'string':
            $data = (string)$data;
            if (isset($options['min_length']) && strlen($data) < $options['min_length']) {
                return false;
            }
            if (isset($options['max_length']) && strlen($data) > $options['max_length']) {
                return false;
            }
            if (isset($options['pattern']) && !preg_match($options['pattern'], $data)) {
                return false;
            }
            // Basic sanitization
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
            return $data;
            
        case 'int':
            $data = filter_var($data, FILTER_VALIDATE_INT);
            if ($data === false) {
                return false;
            }
            if (isset($options['min']) && $data < $options['min']) {
                return false;
            }
            if (isset($options['max']) && $data > $options['max']) {
                return false;
            }
            return $data;
            
        case 'float':
            $data = filter_var($data, FILTER_VALIDATE_FLOAT);
            if ($data === false) {
                return false;
            }
            if (isset($options['min']) && $data < $options['min']) {
                return false;
            }
            if (isset($options['max']) && $data > $options['max']) {
                return false;
            }
            return $data;
            
        case 'email':
            $data = filter_var($data, FILTER_VALIDATE_EMAIL);
            return $data !== false ? $data : false;
            
        case 'url':
            $data = filter_var($data, FILTER_VALIDATE_URL);
            return $data !== false ? $data : false;
            
        case 'array':
            if (!is_array($data)) {
                return false;
            }
            // Recursively validate array elements
            $sanitized = [];
            foreach ($data as $key => $value) {
                $sanitized[$key] = validate_plugin_input($value, $options['item_type'] ?? 'string', $options['item_options'] ?? []);
            }
            return $sanitized;
            
        case 'json':
            if (is_string($data)) {
                $decoded = json_decode($data, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return false;
                }
                return $decoded;
            }
            return is_array($data) ? $data : false;
            
        default:
            return $data;
    }
}

/**
 * Sanitize output for safe display
 * 
 * @param string $output Output to sanitize
 * @param string $type Output type ('html', 'text', 'url', 'attribute')
 * @return string Sanitized output
 */
function sanitize_plugin_output($output, $type = 'html') {
    switch ($type) {
        case 'html':
            // Allow safe HTML, strip dangerous tags
            $allowed_tags = '<p><br><strong><em><u><a><ul><ol><li><h1><h2><h3><h4><h5><h6><code><pre><blockquote>';
            return strip_tags($output, $allowed_tags);
            
        case 'text':
            // Plain text only
            return htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
            
        case 'url':
            // URL encoding
            return htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
            
        case 'attribute':
            // HTML attribute encoding
            return htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
            
        default:
            return htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Validate CSRF token for plugin endpoints
 * 
 * @param string $token CSRF token to validate
 * @return bool True if valid
 */
function validate_plugin_csrf($token) {
    require_once __DIR__ . '/../../core/csrf.php';
    return validate_csrf_token($token);
}

/**
 * Log security event
 * 
 * @param string $event Event type
 * @param array $data Event data
 * @return bool True on success
 */
function log_plugin_security_event($event, $data = []) {
    require_once __DIR__ . '/../../core/storage/StorageFactory.php';
    
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $auditLog = $storage->read('plugin_security_audit.json') ?? [];
    
    $auditLog[] = [
        'event' => $event,
        'data' => $data,
        'timestamp' => date('c'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    // Keep only last 5000 entries
    if (count($auditLog) > 5000) {
        $auditLog = array_slice($auditLog, -5000);
    }
    
    return $storage->write('plugin_security_audit.json', $auditLog);
}

/**
 * Check if a plugin action is rate limited
 * 
 * @param string $action Action identifier
 * @param int $limit Maximum number of actions
 * @param int $window Time window in seconds
 * @return bool True if rate limit exceeded
 */
function check_plugin_rate_limit($action, $limit = 10, $window = 3600) {
    require_once __DIR__ . '/../../api/rate_limit.php';
    $key = "plugin_action_{$action}";
    return !check_rate_limit($key, $limit, $window);
}

/**
 * Escape JavaScript string
 * 
 * @param string $string String to escape
 * @return string Escaped string
 */
if (!function_exists('escape_js')) {
    function escape_js($string) {
        return json_encode($string, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}

/**
 * Escape CSS string
 * 
 * @param string $string String to escape
 * @return string Escaped string
 */
function escape_css($string) {
    return preg_replace('/[^a-zA-Z0-9]/', '', $string);
}
