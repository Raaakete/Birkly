<?php
/**
 * Plugin Dependency Management for Birkly
 * 
 * Handles plugin dependencies, version checking, and service dependencies.
 */

require_once __DIR__ . '/services.php';

/**
 * Check plugin dependencies
 * 
 * @param string $plugin_id Plugin ID to check
 * @return array Array of errors (empty if all dependencies met)
 */
function check_plugin_dependencies($plugin_id) {
    global $haru_plugins;
    
    $plugin = $haru_plugins[$plugin_id] ?? null;
    if (!$plugin) {
        return ["Plugin '{$plugin_id}' not found"];
    }
    
    $errors = [];
    $metadata = load_plugin_metadata($plugin['path'] ?? '');
    $dependencies = $metadata['dependencies'] ?? [];
    
    // Check plugin dependencies
    foreach ($dependencies['plugins'] ?? [] as $dep) {
        $dep_id = is_array($dep) ? ($dep['id'] ?? '') : $dep;
        $required_version = is_array($dep) ? ($dep['version'] ?? '*') : '*';
        $required = is_array($dep) ? ($dep['required'] ?? true) : true;
        
        $dep_plugin = $haru_plugins[$dep_id] ?? null;
        
        if (!$dep_plugin) {
            if ($required) {
                $errors[] = "Required plugin '{$dep_id}' not found";
            }
            continue;
        }
        
        // Check version
        if ($required_version !== '*') {
            $dep_version = $dep_plugin['version'] ?? '0.0.0';
            if (!version_compare($dep_version, $required_version, '>=')) {
                $errors[] = "Plugin '{$dep_id}' version mismatch. Required: {$required_version}, Found: {$dep_version}";
            }
        }
        
        // Check if plugin is active (if required)
        if ($required && !($dep_plugin['active'] ?? false)) {
            $errors[] = "Required plugin '{$dep_id}' is not active";
        }
    }
    
    // Check service dependencies
    foreach ($dependencies['services'] ?? [] as $service) {
        $service_id = is_array($service) ? ($service['id'] ?? '') : $service;
        $required_version = is_array($service) ? ($service['version'] ?? '*') : '*';
        $provided_by = is_array($service) ? ($service['provided_by'] ?? null) : null;
        
        if (empty($service_id)) {
            continue;
        }
        
        // Check if service exists
        global $haru_plugin_services;
        if (!isset($haru_plugin_services[$service_id])) {
            $errors[] = "Required service '{$service_id}' not available";
            continue;
        }
        
        $service_info = $haru_plugin_services[$service_id];
        
        if ($service_info) {
            
            // Check version if specified
            if ($required_version !== '*' && isset($service_info['version'])) {
                if (!version_compare($service_info['version'], $required_version, '>=')) {
                    $errors[] = "Service '{$service_id}' version mismatch. Required: {$required_version}, Found: {$service_info['version']}";
                }
            }
            
            // Check provider if specified
            if ($provided_by && isset($service_info['plugin_id'])) {
                if ($service_info['plugin_id'] !== $provided_by) {
                    $errors[] = "Service '{$service_id}' must be provided by '{$provided_by}', but is provided by '{$service_info['plugin_id']}'";
                }
            }
            
            // Check if provider plugin is active
            $provider_plugin_id = $service_info['plugin_id'] ?? null;
            if ($provider_plugin_id) {
                global $haru_plugins;
                if (isset($haru_plugins[$provider_plugin_id]) && !($haru_plugins[$provider_plugin_id]['active'] ?? false)) {
                    $errors[] = "Service '{$service_id}' provider plugin '{$provider_plugin_id}' is not active";
                }
            }
        }
    }
    
    // Check PHP extension dependencies
    foreach ($dependencies['php_extensions'] ?? [] as $ext) {
        if (!extension_loaded($ext)) {
            $errors[] = "Required PHP extension '{$ext}' is not loaded";
        }
    }
    
    // Check PHP version
    $required_php = $metadata['requires_php'] ?? null;
    if ($required_php && !version_compare(PHP_VERSION, $required_php, '>=')) {
        $errors[] = "PHP version mismatch. Required: {$required_php}, Found: " . PHP_VERSION;
    }
    
    // Check Birkly version
    $required_haru = $metadata['requires'] ?? null;
    if ($required_haru) {
        $haru_version = get_birkly_version();
        if (!version_compare($haru_version, $required_haru, '>=')) {
            $errors[] = "Birkly version mismatch. Required: {$required_haru}, Found: {$haru_version}";
        }
    }
    
    return $errors;
}

/**
 * Get Birkly version
 * 
 * @return string Version string
 */
function get_birkly_version() {
    if (file_exists(__DIR__ . '/../version.php')) {
        require_once __DIR__ . '/../version.php';
        return defined('HARUCMS_VERSION') ? HARUCMS_VERSION : '1.0.0';
    }
    return '1.0.0';
}

/**
 * Validate plugin can be activated
 * 
 * @param string $plugin_id Plugin ID
 * @return array Array with 'valid' (bool) and 'errors' (array)
 */
function validate_plugin_activation($plugin_id) {
    $errors = check_plugin_dependencies($plugin_id);
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}
