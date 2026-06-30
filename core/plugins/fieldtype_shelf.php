<?php
/**
 * Fieldtype Shelf - Allows plugins to register fieldtypes
 * Part of the "Rooms and Shelves" architecture
 * 
 * Plugins can place fieldtypes on this shelf, making them available
 * throughout the system. When a plugin is deactivated, its fieldtypes
 * are automatically removed from the shelf.
 */

// Global registry for plugin-registered fieldtypes
$haru_plugin_fieldtypes = [];

/**
 * Register a fieldtype from a plugin (Place fieldtype on Fieldtype Shelf)
 * 
 * @param string $type Fieldtype identifier (e.g., 'user')
 * @param string $class Full class name (e.g., 'UserManagement\\Fieldtypes\\User')
 * @param string $plugin_id Plugin ID that owns this fieldtype
 * @return bool True on success
 */
function register_plugin_fieldtype($type, $class, $plugin_id) {
    global $haru_plugin_fieldtypes;
    
    // Validate inputs
    if (empty($type) || !is_string($type) || !preg_match('/^[a-z0-9_]+$/', $type)) {
        error_log("Invalid fieldtype name: " . $type);
        return false;
    }
    
    if (empty($class) || !is_string($class)) {
        error_log("Invalid fieldtype class: " . $class);
        return false;
    }
    
    if (empty($plugin_id) || !is_string($plugin_id)) {
        error_log("Invalid plugin ID: " . $plugin_id);
        return false;
    }
    
    // Check if plugin is active
    global $birkly_plugins;
    if (!isset($birkly_plugins[$plugin_id])) {
        error_log("Cannot register fieldtype '$type': Plugin '$plugin_id' not found");
        return false;
    }
    
    if (!($birkly_plugins[$plugin_id]['active'] ?? false)) {
        error_log("Cannot register fieldtype '$type': Plugin '$plugin_id' is not active");
        return false;
    }
    
    // Verify class exists and extends BaseField
    if (!class_exists($class)) {
        error_log("Fieldtype class '$class' not found for type '$type'. Make sure the class file is loaded before registration.");
        return false;
    }
    
    // Check if class extends BaseField
    if (!is_subclass_of($class, 'Birkly\\FieldTypes\\BaseField')) {
        error_log("Fieldtype class '$class' must extend Birkly\\FieldTypes\\BaseField");
        return false;
    }
    
    // Register on shelf
    $haru_plugin_fieldtypes[$type] = [
        'class' => $class,
        'plugin_id' => $plugin_id,
        'registered_at' => date('c')
    ];
    
    // Also register in FieldRegistry so it works with existing system
    if (class_exists('Birkly\\FieldTypes\\FieldRegistry')) {
        try {
            \Birkly\FieldTypes\FieldRegistry::register($type, $class);
        } catch (Exception $e) {
            error_log("Error registering fieldtype '$type' in FieldRegistry: " . $e->getMessage());
            // Don't fail completely, but log the error
        }
    }
    
    return true;
}

/**
 * Unregister a plugin fieldtype (Remove from Fieldtype Shelf)
 * 
 * @param string $type Fieldtype identifier
 * @param string $plugin_id Plugin ID
 * @return bool True on success
 */
function unregister_plugin_fieldtype($type, $plugin_id) {
    global $haru_plugin_fieldtypes;
    
    if (!isset($haru_plugin_fieldtypes[$type])) {
        return false;
    }
    
    // Only unregister if owned by this plugin
    if ($haru_plugin_fieldtypes[$type]['plugin_id'] !== $plugin_id) {
        error_log("Cannot unregister fieldtype '$type': Not owned by plugin '$plugin_id'");
        return false;
    }
    
    unset($haru_plugin_fieldtypes[$type]);
    
    // Note: We can't easily remove from FieldRegistry's internal array,
    // but it won't be available since the class won't be loaded when plugin is inactive
    // The FieldRegistry::getFieldType() method will check plugin fieldtypes first
    
    return true;
}

/**
 * Get all plugin-registered fieldtypes
 * 
 * @return array Fieldtypes with metadata (filtered by active plugins)
 */
function get_plugin_fieldtypes() {
    global $haru_plugin_fieldtypes;
    global $birkly_plugins;
    
    // Filter by active plugins
    $active_fieldtypes = [];
    
    foreach ($haru_plugin_fieldtypes as $type => $data) {
        $plugin_id = $data['plugin_id'];
        if (isset($birkly_plugins[$plugin_id]) && ($birkly_plugins[$plugin_id]['active'] ?? false)) {
            $active_fieldtypes[$type] = $data;
        }
    }
    
    return $active_fieldtypes;
}

/**
 * Check if a fieldtype is plugin-registered
 * 
 * @param string $type Fieldtype identifier
 * @return bool True if registered by a plugin
 */
function is_plugin_fieldtype($type) {
    global $haru_plugin_fieldtypes;
    return isset($haru_plugin_fieldtypes[$type]);
}

/**
 * Get plugin ID that owns a fieldtype
 * 
 * @param string $type Fieldtype identifier
 * @return string|null Plugin ID or null if not found
 */
function get_fieldtype_plugin($type) {
    global $haru_plugin_fieldtypes;
    return $haru_plugin_fieldtypes[$type]['plugin_id'] ?? null;
}

/**
 * Unregister all fieldtypes for a plugin (called when plugin deactivates)
 * 
 * @param string $plugin_id Plugin ID
 * @return int Number of fieldtypes unregistered
 */
function unregister_plugin_fieldtypes($plugin_id) {
    global $haru_plugin_fieldtypes;
    
    $unregistered = 0;
    $types_to_remove = [];
    
    // Find all fieldtypes owned by this plugin
    foreach ($haru_plugin_fieldtypes as $type => $data) {
        if ($data['plugin_id'] === $plugin_id) {
            $types_to_remove[] = $type;
        }
    }
    
    // Remove them
    foreach ($types_to_remove as $type) {
        if (unregister_plugin_fieldtype($type, $plugin_id)) {
            $unregistered++;
        }
    }
    
    return $unregistered;
}
