<?php
/**
 * Template Function Registration for Birkly
 * 
 * Allows plugins to register functions callable from templates.
 * Follows the "Rooms and Shelves" model - plugins place functions on the Template Shelf.
 * 
 * Template functions are the PRIMARY interface for user-facing plugins,
 * enabling template-first architecture where users build websites using template language.
 */

// Global template functions registry (part of The Lobby)
$haru_template_functions = [];

/**
 * Register a template function (Place function on Template Shelf)
 * 
 * @param string $name Function name (e.g., 'user_current', 'user_can')
 * @param callable $callback Function callback
 * @param array $options Options:
 *   - 'description' (string): Function description
 *   - 'arguments' (array): Expected arguments with types
 *   - 'required_capability' (string): Capability required to use this function
 * 
 * @return bool True on success
 */
function register_template_function($name, $callback, $options = []) {
    global $haru_template_functions;
    
    // Validate function name
    if (empty($name) || !is_string($name) || !preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
        error_log("Invalid template function name: " . $name);
        return false;
    }
    
    // Validate callback
    if (!is_callable($callback)) {
        error_log("Template function callback is not callable: " . $name);
        return false;
    }
    
    // Get plugin ID from backtrace (identify which Plugin Room)
    // Use a deeper trace because plugins may register functions from helper files.
    $plugin_id = null;
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
    $file = null;
    foreach ($backtrace as $frame) {
        if (!isset($frame['file'])) continue;
        $candidate = $frame['file'];
        if (strpos($candidate, DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR) !== false) {
            $file = $candidate;
            break;
        }
    }
    if ($file) {
        $fileReal = realpath($file) ?: $file;

        // Prefer the global plugin registry helper when available
        if (function_exists('get_all_plugins')) {
            foreach (get_all_plugins() as $pid => $plugin) {
                $plugin_path = $plugin['path'] ?? '';
                if (!$plugin_path) {
                    // Fallback: match by conventional plugin folder name in path
                    $needle = DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . $pid . DIRECTORY_SEPARATOR;
                    if (strpos($fileReal, $needle) !== false) {
                        $plugin_id = $pid;
                        break;
                    }
                    continue;
                }
                $pluginReal = realpath($plugin_path) ?: $plugin_path;
                if (str_starts_with($fileReal, $pluginReal)) {
                    $plugin_id = $pid;
                    break;
                }
            }
        } else {
            global $birkly_plugins;
            if (isset($birkly_plugins)) {
                foreach ($birkly_plugins as $pid => $plugin) {
                    $plugin_path = $plugin['path'] ?? '';
                    if (!$plugin_path) {
                        $needle = DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . $pid . DIRECTORY_SEPARATOR;
                        if (strpos($fileReal, $needle) !== false) {
                            $plugin_id = $pid;
                            break;
                        }
                        continue;
                    }
                    $pluginReal = realpath($plugin_path) ?: $plugin_path;
                    if (str_starts_with($fileReal, $pluginReal)) {
                        $plugin_id = $pid;
                        break;
                    }
                }
            }
        }
    }
    
    // Check capability if required (Door Policy)
    $required_capability = $options['required_capability'] ?? null;
    if ($required_capability && $plugin_id) {
        if (function_exists('has_plugin_capability') && !has_plugin_capability($plugin_id, $required_capability)) {
            error_log("Plugin '$plugin_id' does not have required capability '$required_capability' for template function '$name'");
            return false; // Permission denied
        }
    }
    
    // Place function on Template Shelf (register in The Lobby)
    $haru_template_functions[$name] = [
        'callback' => $callback,
        'plugin_id' => $plugin_id,
        'description' => $options['description'] ?? '',
        'arguments' => $options['arguments'] ?? [],
        'required_capability' => $required_capability,
        'registered_at' => date('c')
    ];
    
    return true;
}

/**
 * Get a template function (Core retrieves from Template Shelf)
 * 
 * @param string $name Function name
 * @return callable|null Function callback or null if not found/inactive
 */
function get_template_function($name) {
    global $haru_template_functions;
    
    if (!isset($haru_template_functions[$name])) {
        return null; // Function not on shelf
    }
    
    $func_data = $haru_template_functions[$name];
    $plugin_id = $func_data['plugin_id'];
    
    // Check if plugin is active (Door Policy)
    if ($plugin_id) {
        // Support both admin context ($birkly_plugins) and public context (get_all_plugins)
        $plugin_active = false;
        
        // Try admin context first
        global $birkly_plugins;
        if (isset($birkly_plugins) && isset($birkly_plugins[$plugin_id])) {
            $plugin_active = !empty($birkly_plugins[$plugin_id]['active']);
        } else {
            // Fall back to public bootstrap plugin system
            if (function_exists('get_all_plugins')) {
                $all_plugins = get_all_plugins();
                if (isset($all_plugins[$plugin_id])) {
                    $plugin_active = !empty($all_plugins[$plugin_id]['active']);
                }
            } else {
                // If neither system is available, assume plugin is active (graceful degradation)
                $plugin_active = true;
            }
        }
        
        if (!$plugin_active) {
            return null; // Plugin room is closed (inactive)
        }
    }
    
    return $func_data['callback'];
}

/**
 * Get all registered template functions (for discovery/debugging)
 * 
 * @return array All registered functions with metadata
 */
function get_all_template_functions() {
    global $haru_template_functions;
    
    // Initialize if not set
    if (!isset($haru_template_functions) || !is_array($haru_template_functions)) {
        return [];
    }
    
    // Filter by active plugins (only show functions from active Plugin Rooms)
    $active_functions = [];
    foreach ($haru_template_functions as $name => $func_data) {
        $plugin_id = $func_data['plugin_id'];
        
        if ($plugin_id) {
            // Check plugin state - support both admin context ($birkly_plugins) and public context (get_all_plugins)
            $plugin_active = false;
            
            // Try admin context first
            global $birkly_plugins;
            if (isset($birkly_plugins) && isset($birkly_plugins[$plugin_id])) {
                $plugin_active = !empty($birkly_plugins[$plugin_id]['active']);
            } else {
                // Fall back to public bootstrap plugin system
                if (function_exists('get_all_plugins')) {
                    $all_plugins = get_all_plugins();
                    if (isset($all_plugins[$plugin_id])) {
                        $plugin_active = !empty($all_plugins[$plugin_id]['active']);
                    }
                } else {
                    // If neither system is available, assume plugin is active (graceful degradation)
                    $plugin_active = true;
                }
            }
            
            if (!$plugin_active) {
                continue; // Skip inactive plugins
            }
        }
        
        $active_functions[$name] = $func_data;
    }
    
    return $active_functions;
}

/**
 * Check if a template function exists and is available
 * 
 * @param string $name Function name
 * @return bool True if function exists and is callable
 */
function template_function_exists($name) {
    return get_template_function($name) !== null;
}
