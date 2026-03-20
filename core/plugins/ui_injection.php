<?php
/**
 * UI Injection System for Birkly
 * 
 * Allows plugins to inject UI components into specific locations in the admin interface.
 */

require_once __DIR__ . '/capabilities.php';

// Global registry for UI injections
$haru_ui_injections = [];

/**
 * Register a UI injection
 * 
 * @param string $injection_point Injection point identifier (e.g., 'collection_editor_sidebar', 'entry_editor_tabs')
 * @param callable|string $component Component to inject (callback function or HTML string)
 * @param array $options Options array:
 *   - 'priority' (int): Priority for ordering (default: 10)
 *   - 'capability' (string): Required capability to show this injection
 *   - 'scope' (string): Scope for capability check (collection slug, entry id, or 'global')
 *   - 'position' (string): Position within injection point ('before', 'after', 'replace')
 *   - 'target' (string): Target element selector (for before/after positions)
 * 
 * @return bool True on success
 */
function register_ui_injection($injection_point, $component, $options = []) {
    global $haru_ui_injections;
    
    // Get plugin ID from backtrace
    $plugin_id = null;
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    if (isset($backtrace[1]['file'])) {
        $file = $backtrace[1]['file'];
        global $haru_plugins;
        if (isset($haru_plugins)) {
            foreach ($haru_plugins as $pid => $plugin) {
                if (strpos($file, $plugin['path'] ?? '') !== false) {
                    $plugin_id = $pid;
                    break;
                }
            }
        }
    }
    
    if (!isset($haru_ui_injections[$injection_point])) {
        $haru_ui_injections[$injection_point] = [];
    }
    
    $injection = [
        'component' => $component,
        'plugin_id' => $plugin_id,
        'priority' => $options['priority'] ?? 10,
        'capability' => $options['capability'] ?? null,
        'scope' => $options['scope'] ?? 'global',
        'position' => $options['position'] ?? 'append',
        'target' => $options['target'] ?? null
    ];
    
    $haru_ui_injections[$injection_point][] = $injection;
    
    // Sort by priority
    usort($haru_ui_injections[$injection_point], function($a, $b) {
        return $a['priority'] <=> $b['priority'];
    });
    
    return true;
}

/**
 * Render UI injections for a specific injection point
 * 
 * @param string $injection_point Injection point identifier
 * @param array $context Context data to pass to components
 * @return string Rendered HTML
 */
function render_ui_injections($injection_point, $context = []) {
    global $haru_ui_injections;
    
    if (!isset($haru_ui_injections[$injection_point])) {
        return '';
    }
    
    $output = '';
    
    foreach ($haru_ui_injections[$injection_point] as $injection) {
        $plugin_id = $injection['plugin_id'];
        $component = $injection['component'];
        $capability = $injection['capability'];
        $scope = $injection['scope'];
        
        // Check capability if required
        if ($capability && $plugin_id) {
            if (!has_plugin_capability($plugin_id, $capability, $scope)) {
                continue; // Skip this injection
            }
        }
        
        // Check if plugin is active
        if ($plugin_id) {
            global $haru_plugins;
            if (isset($haru_plugins[$plugin_id]) && !($haru_plugins[$plugin_id]['active'] ?? false)) {
                continue; // Skip inactive plugins
            }
        }
        
        // Render component
        try {
            if (is_callable($component)) {
                // Callback function
                ob_start();
                $result = call_user_func($component, $context);
                $component_output = ob_get_clean();
                
                // If callback returns a string, use it; otherwise use output buffer
                if (is_string($result)) {
                    $component_output = $result;
                }
                
                $output .= $component_output;
            } elseif (is_string($component)) {
                // HTML string
                $output .= $component;
            }
        } catch (Exception $e) {
            // Log error but continue with other injections
            error_log("UI injection error in '$injection_point' from plugin '$plugin_id': " . $e->getMessage());
        } catch (Error $e) {
            error_log("Fatal UI injection error in '$injection_point' from plugin '$plugin_id': " . $e->getMessage());
        }
    }
    
    return $output;
}

/**
 * Get all registered injection points
 * 
 * @return array List of injection point identifiers
 */
function get_injection_points() {
    global $haru_ui_injections;
    return array_keys($haru_ui_injections);
}

/**
 * Get injections for a specific point
 * 
 * @param string $injection_point Injection point identifier
 * @return array List of registered injections
 */
function get_ui_injections($injection_point) {
    global $haru_ui_injections;
    return $haru_ui_injections[$injection_point] ?? [];
}
