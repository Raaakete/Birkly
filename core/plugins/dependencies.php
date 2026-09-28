<?php
/**
 * Plugin Dependency Management for Birkly
 * 
 * Handles plugin dependencies, version checking, and service dependencies.
 */

require_once __DIR__ . '/services.php';
require_once __DIR__ . '/version_constraints.php';

/**
 * Check plugin dependencies
 * 
 * @param string $plugin_id Plugin ID to check
 * @return array Array of errors (empty if all dependencies met)
 */
function check_plugin_dependencies($plugin_id) {
    global $birkly_plugins;
    
    $plugin = $birkly_plugins[$plugin_id] ?? null;
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
        
        $dep_plugin = $birkly_plugins[$dep_id] ?? null;
        
        if (!$dep_plugin) {
            if ($required) {
                $errors[] = "Required plugin '{$dep_id}' not found";
            }
            continue;
        }
        
        // Check version
        if ($required_version !== '*') {
            $dep_version = $dep_plugin['version'] ?? '0.0.0';
            if (!birkly_version_satisfies_constraint($dep_version, birkly_minimum_version_constraint((string) $required_version))) {
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
                if (!birkly_version_satisfies_constraint((string) $service_info['version'], birkly_minimum_version_constraint((string) $required_version))) {
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
                global $birkly_plugins;
                if (isset($birkly_plugins[$provider_plugin_id]) && !($birkly_plugins[$provider_plugin_id]['active'] ?? false)) {
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
    
    foreach (birkly_plugin_compat_errors_from_metadata($metadata) as $compatError) {
        $errors[] = $compatError;
    }
    
    return $errors;
}

/**
 * Get Birkly version
 * 
 * @return string Version string
 */
function get_birkly_version() {
    require_once __DIR__ . '/../version.php';
    return BirklyVersion::getCurrentVersion();
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
