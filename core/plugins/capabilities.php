<?php
/**
 * Plugin Capability System for Birkly
 * 
 * Manages plugin capabilities and permissions.
 * Implements the denylist-first security model.
 */

require_once __DIR__ . '/permissions.php';

/**
 * Check if a plugin has a specific capability
 * 
 * @param string $plugin_id Plugin identifier
 * @param string $capability Capability name (e.g., 'read_entries', 'modify_collections')
 * @param string|null $scope Optional scope (collection slug, entry id, or 'global')
 * @return bool True if plugin has the capability
 */
function has_plugin_capability($plugin_id, $capability, $scope = null) {
    // First check denylist - if resource is protected, deny immediately
    if ($scope && is_resource_protected($scope)) {
        return false;
    }
    
    // Get plugin permissions
    $permissions = get_plugin_permissions($plugin_id);
    
    // Check global permissions first
    if (isset($permissions['global']) && in_array($capability, $permissions['global'])) {
        return true;
    }
    
    // Check scope-specific permissions
    if ($scope && isset($permissions[$scope]) && in_array($capability, $permissions[$scope])) {
        return true;
    }
    
    return false;
}

/**
 * Require a plugin capability (throws exception if not granted)
 * 
 * @param string $plugin_id Plugin identifier
 * @param string $capability Capability name
 * @param string|null $scope Optional scope
 * @throws Exception If capability is not granted
 */
function require_plugin_capability($plugin_id, $capability, $scope = null) {
    if (!has_plugin_capability($plugin_id, $capability, $scope)) {
        $scopeText = $scope ? " for scope '$scope'" : '';
        throw new Exception("Plugin '$plugin_id' does not have capability '$capability'$scopeText");
    }
}

/**
 * Check if a resource is protected by the denylist
 * 
 * @param string $resource Resource identifier (collection slug, entry id, etc.)
 * @return bool True if resource is protected (denied)
 */
function is_resource_protected($resource) {
    $denylist = get_plugin_denylist();
    
    // Check exact match
    if (in_array($resource, $denylist)) {
        return true;
    }
    
    // Check wildcard patterns (e.g., 'payroll/*')
    foreach ($denylist as $pattern) {
        if (strpos($pattern, '*') !== false) {
            $regex = '/^' . str_replace(['*', '/'], ['.*', '\/'], $pattern) . '$/';
            if (preg_match($regex, $resource)) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Get all capabilities declared by a plugin
 * 
 * @param string $plugin_id Plugin identifier
 * @return array List of declared capabilities
 */
function get_plugin_declared_capabilities($plugin_id) {
    global $birkly_plugins;
    
    if (!isset($birkly_plugins[$plugin_id])) {
        return [];
    }
    
    $plugin = $birkly_plugins[$plugin_id];
    return $plugin['capabilities'] ?? [];
}

/**
 * Get all capabilities granted to a plugin
 * 
 * @param string $plugin_id Plugin identifier
 * @return array Permissions array with 'global' and scope-specific capabilities
 */
function get_plugin_granted_capabilities($plugin_id) {
    return get_plugin_permissions($plugin_id);
}

/**
 * Check if plugin has any capabilities granted
 * 
 * @param string $plugin_id Plugin identifier
 * @return bool True if plugin has any granted capabilities
 */
function plugin_has_any_capabilities($plugin_id) {
    $permissions = get_plugin_permissions($plugin_id);
    return !empty($permissions['global']) || count($permissions) > 1;
}
