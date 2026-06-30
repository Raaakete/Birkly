<?php
/**
 * Shared Data Storage for Birkly
 * 
 * Allows plugins to share data with other plugins in a controlled manner.
 */

require_once __DIR__ . '/capabilities.php';
require_once __DIR__ . '/../../core/storage/StorageFactory.php';

/**
 * Store shared data
 * 
 * @param string $key Data key
 * @param mixed $value Data value
 * @param array $options Options array:
 *   - 'capability' (string): Required capability to read this data
 *   - 'scope' (string): Scope for capability check
 *   - 'ttl' (int): Time to live in seconds (optional)
 * 
 * @return bool True on success
 */
function store_shared_data($key, $value, $options = []) {
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
    
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $sharedData = $storage->read('plugin_shared_data.json') ?? [];
    
    $sharedData[$key] = [
        'value' => $value,
        'plugin_id' => $plugin_id,
        'capability' => $options['capability'] ?? null,
        'scope' => $options['scope'] ?? 'global',
        'ttl' => $options['ttl'] ?? null,
        'expires_at' => $options['ttl'] ? date('c', time() + $options['ttl']) : null,
        'stored_at' => date('c')
    ];
    
    return $storage->write('plugin_shared_data.json', $sharedData);
}

/**
 * Get shared data
 * 
 * @param string $key Data key
 * @param string|null $requesting_plugin_id Plugin ID requesting the data (for permission check)
 * @param mixed $default Default value if not found
 * @return mixed Data value or default
 */
function get_shared_data($key, $requesting_plugin_id = null, $default = null) {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $sharedData = $storage->read('plugin_shared_data.json') ?? [];
    
    if (!isset($sharedData[$key])) {
        return $default;
    }
    
    $data = $sharedData[$key];
    
    // Check TTL
    if (!empty($data['expires_at'])) {
        $expires = strtotime($data['expires_at']);
        if (time() > $expires) {
            // Data expired, remove it
            unset($sharedData[$key]);
            $storage->write('plugin_shared_data.json', $sharedData);
            return $default;
        }
    }
    
    // Check capability if required
    $capability = $data['capability'] ?? null;
    $scope = $data['scope'] ?? 'global';
    
    if ($capability && $requesting_plugin_id) {
        if (!has_plugin_capability($requesting_plugin_id, $capability, $scope)) {
            return $default; // Permission denied
        }
    }
    
    return $data['value'] ?? $default;
}

/**
 * Delete shared data
 * 
 * @param string $key Data key
 * @return bool True on success
 */
function delete_shared_data($key) {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $sharedData = $storage->read('plugin_shared_data.json') ?? [];
    
    if (isset($sharedData[$key])) {
        unset($sharedData[$key]);
        return $storage->write('plugin_shared_data.json', $sharedData);
    }
    
    return true;
}

/**
 * List all shared data keys
 * 
 * @param string|null $requesting_plugin_id Plugin ID requesting the list (for filtering by permissions)
 * @return array List of data keys
 */
function list_shared_data($requesting_plugin_id = null) {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $sharedData = $storage->read('plugin_shared_data.json') ?? [];
    
    $keys = [];
    
    foreach ($sharedData as $key => $data) {
        // Check TTL
        if (!empty($data['expires_at'])) {
            $expires = strtotime($data['expires_at']);
            if (time() > $expires) {
                continue; // Skip expired data
            }
        }
        
        // Check capability if required
        $capability = $data['capability'] ?? null;
        $scope = $data['scope'] ?? 'global';
        
        if ($capability && $requesting_plugin_id) {
            if (!has_plugin_capability($requesting_plugin_id, $capability, $scope)) {
                continue; // Skip data without permission
            }
        }
        
        $keys[] = $key;
    }
    
    return $keys;
}
