<?php
/**
 * Plugin Permission Storage and Management for Birkly
 * 
 * Handles storage and retrieval of plugin permissions and denylist.
 */

require_once __DIR__ . '/../../core/storage/StorageFactory.php';

/**
 * Get plugin permissions
 * 
 * @param string $plugin_id Plugin identifier
 * @return array Permissions structure: ['global' => [...], 'collection_slug' => [...]]
 */
function get_plugin_permissions($plugin_id) {
    static $permissionsCache = null;
    
    if ($permissionsCache === null) {
        $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
        $permissionsData = $storage->read('plugin_permissions.json') ?? [];
        $permissionsCache = $permissionsData;
    }
    
    return $permissionsCache[$plugin_id] ?? ['global' => []];
}

/**
 * Set plugin permissions
 * 
 * @param string $plugin_id Plugin identifier
 * @param array $permissions Permissions structure
 * @return bool True on success
 */
function set_plugin_permissions($plugin_id, $permissions) {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $permissionsData = $storage->read('plugin_permissions.json') ?? [];
    $permissionsData[$plugin_id] = $permissions;
    
    $result = $storage->write('plugin_permissions.json', $permissionsData);
    
    // Clear cache
    $GLOBALS['_plugin_permissions_cache'] = null;
    
    // Log permission change
    log_permission_change($plugin_id, $permissions);
    
    return $result;
}

/**
 * Grant a capability to a plugin
 * 
 * @param string $plugin_id Plugin identifier
 * @param string $capability Capability name
 * @param string|null $scope Optional scope (collection slug or 'global')
 * @return bool True on success
 */
function grant_plugin_capability($plugin_id, $capability, $scope = 'global') {
    $permissions = get_plugin_permissions($plugin_id);
    
    if (!isset($permissions[$scope])) {
        $permissions[$scope] = [];
    }
    
    if (!in_array($capability, $permissions[$scope])) {
        $permissions[$scope][] = $capability;
        return set_plugin_permissions($plugin_id, $permissions);
    }
    
    return true; // Already granted
}

/**
 * Revoke a capability from a plugin
 * 
 * @param string $plugin_id Plugin identifier
 * @param string $capability Capability name
 * @param string|null $scope Optional scope
 * @return bool True on success
 */
function revoke_plugin_capability($plugin_id, $capability, $scope = 'global') {
    $permissions = get_plugin_permissions($plugin_id);
    
    if (isset($permissions[$scope])) {
        $permissions[$scope] = array_values(array_filter($permissions[$scope], function($cap) use ($capability) {
            return $cap !== $capability;
        }));
        
        // Remove empty scope arrays (except global)
        if ($scope !== 'global' && empty($permissions[$scope])) {
            unset($permissions[$scope]);
        }
        
        return set_plugin_permissions($plugin_id, $permissions);
    }
    
    return true; // Already not granted
}

/**
 * Get plugin denylist (protected resources)
 * 
 * @return array List of protected resource identifiers
 */
function get_plugin_denylist() {
    static $denylistCache = null;
    
    if ($denylistCache === null) {
        $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
        $denylistData = $storage->read('plugin_denylist.json') ?? [];
        $denylistCache = $denylistData['resources'] ?? [];
    }
    
    return $denylistCache;
}

/**
 * Set plugin denylist
 * 
 * @param array $resources List of protected resource identifiers
 * @return bool True on success
 */
function set_plugin_denylist($resources) {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $denylistData = [
        'resources' => $resources,
        'updated_at' => date('c')
    ];
    
    $result = $storage->write('plugin_denylist.json', $denylistData);
    
    // Clear cache
    $GLOBALS['_plugin_denylist_cache'] = null;
    
    return $result;
}

/**
 * Add resource to denylist
 * 
 * @param string $resource Resource identifier
 * @return bool True on success
 */
function add_to_denylist($resource) {
    $denylist = get_plugin_denylist();
    
    if (!in_array($resource, $denylist)) {
        $denylist[] = $resource;
        return set_plugin_denylist($denylist);
    }
    
    return true; // Already in denylist
}

/**
 * Remove resource from denylist
 * 
 * @param string $resource Resource identifier
 * @return bool True on success
 */
function remove_from_denylist($resource) {
    $denylist = get_plugin_denylist();
    
    $denylist = array_values(array_filter($denylist, function($r) use ($resource) {
        return $r !== $resource;
    }));
    
    return set_plugin_denylist($denylist);
}

/**
 * Log permission change for audit trail
 * 
 * @param string $plugin_id Plugin identifier
 * @param array $permissions New permissions
 */
function log_permission_change($plugin_id, $permissions) {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $auditLog = $storage->read('plugin_permissions_audit.json') ?? [];
    
    $auditLog[] = [
        'plugin_id' => $plugin_id,
        'permissions' => $permissions,
        'changed_by' => $_SESSION['user']['id'] ?? 'system',
        'changed_at' => date('c')
    ];
    
    // Keep only last 1000 entries
    if (count($auditLog) > 1000) {
        $auditLog = array_slice($auditLog, -1000);
    }
    
    $storage->write('plugin_permissions_audit.json', $auditLog);
}
