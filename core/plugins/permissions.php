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
    if (!array_key_exists('_plugin_permissions_cache', $GLOBALS)) {
        $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
        $permissionsData = $storage->read('plugin_permissions.json') ?? [];
        $GLOBALS['_plugin_permissions_cache'] = $permissionsData;
    }

    $permissionsCache = $GLOBALS['_plugin_permissions_cache'];
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
    
    unset($GLOBALS['_plugin_permissions_cache']);
    
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
    if (!array_key_exists('_plugin_denylist_cache', $GLOBALS)) {
        $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
        $denylistData = $storage->read('plugin_denylist.json') ?? [];
        $GLOBALS['_plugin_denylist_cache'] = $denylistData['resources'] ?? [];
    }

    return $GLOBALS['_plugin_denylist_cache'];
}

/**
 * Structured denylist metadata (collections + spaces stubs for admin UI).
 *
 * @return array{collections: array, spaces: array, resources: array}
 */
function get_plugin_denylist_meta() {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $denylistData = $storage->read('plugin_denylist.json') ?? [];
    $resources = $denylistData['resources'] ?? [];
    $collections = $denylistData['deny_collections'] ?? [];
    $spaces = $denylistData['deny_spaces'] ?? [];

    if (empty($collections) && !empty($resources)) {
        foreach ($resources as $resource) {
            if (is_string($resource) && strpos($resource, '*') === false && strpos($resource, '/') === false && strpos($resource, ':') === false) {
                $collections[] = $resource;
            }
        }
        $collections = array_values(array_unique($collections));
    }

    return [
        'resources' => $resources,
        'deny_collections' => $collections,
        'deny_spaces' => $spaces,
    ];
}

/**
 * Set plugin denylist
 * 
 * @param array $resources List of protected resource identifiers
 * @param array $options Optional deny_collections / deny_spaces metadata
 * @return bool True on success
 */
function set_plugin_denylist($resources, array $options = []) {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $existing = $storage->read('plugin_denylist.json') ?? [];
    $denylistData = [
        'resources' => array_values(array_unique($resources)),
        'deny_collections' => $options['deny_collections'] ?? ($existing['deny_collections'] ?? []),
        'deny_spaces' => $options['deny_spaces'] ?? ($existing['deny_spaces'] ?? []),
        'updated_at' => date('c'),
    ];

    $result = $storage->write('plugin_denylist.json', $denylistData);

    unset($GLOBALS['_plugin_denylist_cache']);

    return $result;
}

/**
 * Add resource to denylist
 * 
 * @param string $resource Resource identifier
 * @return bool True on success
 */
function add_to_denylist($resource, array $options = []) {
    $denylist = get_plugin_denylist();

    if (!in_array($resource, $denylist)) {
        $denylist[] = $resource;
        $meta = get_plugin_denylist_meta();
        if (!empty($options['as_collection'])) {
            $collections = $meta['deny_collections'];
            if (!in_array($resource, $collections, true)) {
                $collections[] = $resource;
            }
            $options['deny_collections'] = $collections;
        }
        if (!empty($options['as_space'])) {
            $spaces = $meta['deny_spaces'];
            if (!in_array($resource, $spaces, true)) {
                $spaces[] = $resource;
            }
            $options['deny_spaces'] = $spaces;
            if (!in_array('space:' . $resource, $denylist, true)) {
                $denylist[] = 'space:' . $resource;
            }
        }
        return set_plugin_denylist($denylist, $options);
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
        return $r !== $resource && $r !== ('space:' . $resource);
    }));

    $meta = get_plugin_denylist_meta();
    $collections = array_values(array_filter($meta['deny_collections'], function($c) use ($resource) {
        return $c !== $resource;
    }));
    $spaces = array_values(array_filter($meta['deny_spaces'], function($s) use ($resource) {
        return $s !== $resource;
    }));

    return set_plugin_denylist($denylist, ['deny_collections' => $collections, 'deny_spaces' => $spaces]);
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
