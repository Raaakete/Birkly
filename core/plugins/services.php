<?php
/**
 * Plugin Service Registry for Birkly
 * 
 * Enables plugin-to-plugin communication through service discovery.
 */

require_once __DIR__ . '/capabilities.php';

// Global service registry
$haru_plugin_services = [];

/**
 * Register a plugin service
 * 
 * @param string $service_id Service identifier (e.g., 'payment_service', 'user_service')
 * @param mixed $service Service implementation (object, callable, or data)
 * @param array $options Options array:
 *   - 'version' (string): Service version (default: '1.0.0')
 *   - 'description' (string): Service description
 *   - 'capability' (string): Required capability to access this service
 *   - 'scope' (string): Scope for capability check
 * 
 * @return bool True on success
 */
function register_plugin_service($service_id, $service, $options = []) {
    global $haru_plugin_services;
    
    // Get plugin ID from backtrace
    $plugin_id = null;
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    if (isset($backtrace[1]['file'])) {
        $file = $backtrace[1]['file'];
        global $birkly_plugins;
        if (isset($birkly_plugins)) {
            foreach ($birkly_plugins as $pid => $plugin) {
                if (strpos($file, $plugin['path'] ?? '') !== false) {
                    $plugin_id = $pid;
                    break;
                }
            }
        }
    }
    
    $haru_plugin_services[$service_id] = [
        'service' => $service,
        'plugin_id' => $plugin_id,
        'version' => $options['version'] ?? '1.0.0',
        'description' => $options['description'] ?? '',
        'capability' => $options['capability'] ?? null,
        'scope' => $options['scope'] ?? 'global',
        'registered_at' => date('c')
    ];
    
    return true;
}

/**
 * Get a plugin service
 * 
 * @param string $service_id Service identifier
 * @param string|null $requesting_plugin_id Plugin ID requesting the service (for permission check)
 * @return mixed Service implementation or null if not found/not accessible
 */
function get_plugin_service($service_id, $throw_exception = true, $requesting_plugin_id = null) {
    global $haru_plugin_services;
    
    if (!isset($haru_plugin_services[$service_id])) {
        return null;
    }
    
    $service_data = $haru_plugin_services[$service_id];
    $provider_plugin_id = $service_data['plugin_id'];
    $capability = $service_data['capability'];
    $scope = $service_data['scope'];
    
    // Check if requesting plugin has permission
    if ($requesting_plugin_id && $capability) {
        if (!has_plugin_capability($requesting_plugin_id, $capability, $scope)) {
            return null; // Permission denied
        }
    }
    
    // Check if provider plugin is active
    if ($provider_plugin_id) {
        global $birkly_plugins;
        if (isset($birkly_plugins[$provider_plugin_id]) && !($birkly_plugins[$provider_plugin_id]['active'] ?? false)) {
            return null; // Provider plugin is inactive
        }
    }
    
    return $service_data['service'];
}

/**
 * Discover available services
 * 
 * @param string|null $requesting_plugin_id Plugin ID requesting discovery (for filtering by permissions)
 * @return array List of available services with metadata
 */
function discover_plugin_services($requesting_plugin_id = null) {
    global $haru_plugin_services;
    
    $services = [];
    
    foreach ($haru_plugin_services as $service_id => $service_data) {
        $capability = $service_data['capability'];
        $scope = $service_data['scope'];
        
        // Check permission if requesting plugin is specified
        if ($requesting_plugin_id && $capability) {
            if (!has_plugin_capability($requesting_plugin_id, $capability, $scope)) {
                continue; // Skip services without permission
            }
        }
        
        // Check if provider plugin is active
        $provider_plugin_id = $service_data['plugin_id'];
        if ($provider_plugin_id) {
            global $birkly_plugins;
            if (isset($birkly_plugins[$provider_plugin_id]) && !($birkly_plugins[$provider_plugin_id]['active'] ?? false)) {
                continue; // Skip services from inactive plugins
            }
        }
        
        $services[$service_id] = [
            'id' => $service_id,
            'version' => $service_data['version'],
            'description' => $service_data['description'],
            'provider_plugin' => $provider_plugin_id,
            'capability_required' => $capability
        ];
    }
    
    return $services;
}

/**
 * Check service version compatibility
 * 
 * @param string $service_id Service identifier
 * @param string $required_version Required version (e.g., '1.0.0', '>=1.0.0')
 * @return bool True if compatible
 */
function check_service_version($service_id, $required_version) {
    global $haru_plugin_services;
    
    if (!isset($haru_plugin_services[$service_id])) {
        return false;
    }
    
    $service_version = $haru_plugin_services[$service_id]['version'];
    
    // Simple version comparison (can be enhanced)
    if (strpos($required_version, '>=') === 0) {
        $required = substr($required_version, 2);
        return version_compare($service_version, $required, '>=');
    } elseif (strpos($required_version, '>') === 0) {
        $required = substr($required_version, 1);
        return version_compare($service_version, $required, '>');
    } elseif (strpos($required_version, '<=') === 0) {
        $required = substr($required_version, 2);
        return version_compare($service_version, $required, '<=');
    } elseif (strpos($required_version, '<') === 0) {
        $required = substr($required_version, 1);
        return version_compare($service_version, $required, '<');
    } else {
        return version_compare($service_version, $required_version, '==');
    }
}
