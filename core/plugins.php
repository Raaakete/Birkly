<?php
// core/plugins.php: Enhanced Plugin loader and hook system for Birkly

// Simple Storage class for plugin state management
class PluginStorage {
    private $directory;
    
    public function __construct($directory) {
        $this->directory = $directory;
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
    
    public function read($filename) {
        $filepath = $this->directory . '/' . $filename;
        if (file_exists($filepath)) {
            $content = file_get_contents($filepath);
            return json_decode($content, true);
        }
        return null;
    }
    
    public function write($filename, $data) {
        $filepath = $this->directory . '/' . $filename;
        $content = json_encode($data, JSON_PRETTY_PRINT);
        return file_put_contents($filepath, $content) !== false;
    }
}

$birkly_hooks = [];
$birkly_filters = []; // Filter system (for modifying data)
// Global registries for plugin system
$birkly_collections = [];
$birkly_api_endpoints = [];
$birkly_admin_pages = [];
$birkly_plugins = []; // Plugin metadata and state
$haru_plugins = &$birkly_plugins;
$birkly_plugin_settings = []; // Plugin-specific settings

// Plugin state management
function get_plugin_state($plugin_id) {
    return birkly_is_plugin_active($plugin_id);
}

/**
 * Single source of truth for plugin active state (nav, page gate, API).
 */
function birkly_is_plugin_active(string $plugin_id): bool
{
    global $birkly_plugins;
    if (isset($birkly_plugins[$plugin_id])) {
        return (bool) ($birkly_plugins[$plugin_id]['active'] ?? false);
    }

    static $persistedStates = null;
    if ($persistedStates === null) {
        $storage = new PluginStorage(__DIR__ . '/../settings');
        $persistedStates = $storage->read('plugins.json') ?? [];
    }
    if (array_key_exists($plugin_id, $persistedStates)) {
        return (bool) ($persistedStates[$plugin_id]['active'] ?? false);
    }
    if ($plugin_id === 'automation') {
        return true;
    }
    return false;
}

function set_plugin_state($plugin_id, $active) {
    global $birkly_plugins;
    if (isset($birkly_plugins[$plugin_id])) {
        $birkly_plugins[$plugin_id]['active'] = $active;
        save_plugin_states();
        
        // Call activation/deactivation hooks
        if ($active) {
            do_action('plugin_activated', $plugin_id);
        } else {
            // Unregister plugin fieldtypes when deactivating
            if (function_exists('unregister_plugin_fieldtypes')) {
                unregister_plugin_fieldtypes($plugin_id);
            }
            do_action('plugin_deactivated', $plugin_id);
        }
    }
}

function save_plugin_states() {
    global $birkly_plugins;
    $states = [];
    foreach ($birkly_plugins as $id => $plugin) {
        $state = [
            'active' => $plugin['active'] ?? false,
            'settings' => $plugin['settings'] ?? [],
        ];
        if (!empty($plugin['marketplace_slug'])) {
            $state['marketplace_slug'] = $plugin['marketplace_slug'];
        }
        if (!empty($plugin['marketplace_version'])) {
            $state['marketplace_version'] = $plugin['marketplace_version'];
        }
        if (!empty($plugin['license_ref'])) {
            $state['license_ref'] = $plugin['license_ref'];
        }
        $states[$id] = $state;
    }
    
    $storage = new PluginStorage(__DIR__ . '/../settings');
    $storage->write('plugins.json', $states);
}

function load_plugin_states() {
    global $birkly_plugins;
    $storage = new PluginStorage(__DIR__ . '/../settings');
    $states = $storage->read('plugins.json') ?? [];

    if (birkly_migrate_removed_plugin_states($states)) {
        $storage->write('plugins.json', $states);
    }
    
    foreach ($states as $id => $state) {
        if (isset($birkly_plugins[$id])) {
            $birkly_plugins[$id]['active'] = $state['active'] ?? false;
            $birkly_plugins[$id]['settings'] = $state['settings'] ?? [];
            if (!empty($state['marketplace_slug'])) {
                $birkly_plugins[$id]['marketplace_slug'] = $state['marketplace_slug'];
            }
            if (!empty($state['marketplace_version'])) {
                $birkly_plugins[$id]['marketplace_version'] = $state['marketplace_version'];
            }
            if (!empty($state['license_ref'])) {
                $birkly_plugins[$id]['license_ref'] = $state['license_ref'];
            }
        }
    }
}

/**
 * Migrate legacy plugin ids removed in P7-M2 (payments, shopping_cart → commerce).
 */
function birkly_migrate_removed_plugin_states(array &$states): bool
{
    $changed = false;

    if (isset($states['payments'])) {
        $paymentsActive = !empty($states['payments']['active']);
        $paymentsSettings = $states['payments']['settings'] ?? [];
        unset($states['payments']);
        $changed = true;

        if ($paymentsActive) {
            $states['commerce'] = $states['commerce'] ?? ['active' => false, 'settings' => []];
            if (empty($states['commerce']['active'])) {
                $states['commerce']['active'] = true;
            }
            $states['commerce']['settings'] = array_merge(
                $paymentsSettings,
                $states['commerce']['settings'] ?? []
            );
        }
    }

    if (isset($states['shopping_cart'])) {
        $cartActive = !empty($states['shopping_cart']['active']);
        unset($states['shopping_cart']);
        $changed = true;

        if ($cartActive) {
            $states['commerce'] = $states['commerce'] ?? ['active' => false, 'settings' => []];
            if (empty($states['commerce']['active'])) {
                $states['commerce']['active'] = true;
            }
        }
    }

    return $changed;
}

// Plugin metadata management
function load_plugin_metadata($plugin_path) {
    $metadata_file = $plugin_path . '/plugin.json';
    if (file_exists($metadata_file)) {
        $metadata = json_decode(file_get_contents($metadata_file), true);
        if ($metadata) {
            // Ensure capabilities array exists (default to empty)
            if (!isset($metadata['capabilities'])) {
                $metadata['capabilities'] = [];
            }
            return $metadata;
        }
    }
    
    // Fallback metadata for plugins without plugin.json
    $plugin_name = basename($plugin_path);
    return [
        'name' => ucwords(str_replace(['_', '-'], ' ', $plugin_name)),
        'version' => '1.0.0',
        'description' => 'Plugin ' . $plugin_name,
        'author' => 'Unknown',
        'requires' => '1.0.0',
        'hooks' => [],
        'permissions' => [],
        'capabilities' => [], // Default: no capabilities declared
        'settings_schema' => []
    ];
}

function register_hook($event, $callback, $priority = 10) {
    global $birkly_hooks;
    if (!isset($birkly_hooks[$event])) {
        $birkly_hooks[$event] = [];
    }
    
    // Store the callback with plugin information
    $plugin_id = null;
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    if (isset($backtrace[1]['file'])) {
        $file = $backtrace[1]['file'];
        foreach (get_all_plugins() as $pid => $plugin) {
            if (strpos($file, $plugin['path']) !== false) {
                $plugin_id = $pid;
                break;
            }
        }
    }
    
    $birkly_hooks[$event][] = [
        'callback' => $callback,
        'plugin_id' => $plugin_id,
        'priority' => (int)$priority
    ];
    
    // Sort by priority (lower number = higher priority)
    usort($birkly_hooks[$event], function($a, $b) {
        return $a['priority'] <=> $b['priority'];
    });
}

function do_action($event, ...$args) {
    birkly_run_action_hooks($event, false, ...$args);
}

/**
 * Run action hooks and rethrow exceptions (for permission guard hooks).
 */
function do_action_strict($event, ...$args) {
    birkly_run_action_hooks($event, true, ...$args);
}

function birkly_run_action_hooks($event, $strict, ...$args) {
    global $birkly_hooks;
    if (!isset($birkly_hooks[$event])) {
        return;
    }
    foreach ($birkly_hooks[$event] as $hook_data) {
        $callback = $hook_data['callback'];
        $plugin_id = $hook_data['plugin_id'];

        $should_execute = true;
        if ($plugin_id !== null) {
            $plugin = get_all_plugins()[$plugin_id] ?? null;
            if ($plugin && !($plugin['active'] ?? false)) {
                $should_execute = false;
            }
        }

        if ($should_execute) {
            try {
                if ($event === 'dashboard_stats' && !empty($args) && is_array($args[0])) {
                    $callback($args[0]);
                } else {
                    call_user_func_array($callback, $args);
                }
            } catch (Exception $e) {
                error_log("Hook error in event '$event' from plugin '$plugin_id': " . $e->getMessage());
                if ($strict) {
                    throw $e;
                }
            } catch (Error $e) {
                error_log("Fatal hook error in event '$event' from plugin '$plugin_id': " . $e->getMessage());
                if ($strict) {
                    throw $e;
                }
            }
        }
    }
}

/**
 * Execute action with context (for future use)
 * 
 * @param string $event Event name
 * @param array $context Context data
 * @param mixed ...$args Additional arguments
 */
function do_action_with_context($event, $context = [], ...$args) {
    // Merge context into args
    array_unshift($args, $context);
    do_action($event, ...$args);
}

/**
 * Register a filter (for modifying data)
 * 
 * @param string $filter_name Filter name
 * @param callable $callback Filter callback (should return modified value)
 * @param int $priority Priority (lower = higher priority, default: 10)
 */
function add_filter($filter_name, $callback, $priority = 10) {
    global $birkly_filters;
    if (!isset($birkly_filters[$filter_name])) {
        $birkly_filters[$filter_name] = [];
    }
    
    // Store the callback with plugin information
    $plugin_id = null;
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    if (isset($backtrace[1]['file'])) {
        $file = $backtrace[1]['file'];
        foreach (get_all_plugins() as $pid => $plugin) {
            if (strpos($file, $plugin['path']) !== false) {
                $plugin_id = $pid;
                break;
            }
        }
    }
    
    $birkly_filters[$filter_name][] = [
        'callback' => $callback,
        'plugin_id' => $plugin_id,
        'priority' => (int)$priority
    ];
    
    // Sort by priority (lower number = higher priority)
    usort($birkly_filters[$filter_name], function($a, $b) {
        return $a['priority'] <=> $b['priority'];
    });
}

/**
 * Apply filters to a value
 * 
 * @param string $filter_name Filter name
 * @param mixed $value Value to filter
 * @param mixed ...$args Additional arguments to pass to filters
 * @return mixed Filtered value
 */
function apply_filters($filter_name, $value, ...$args) {
    return birkly_run_filters($filter_name, $value, false, ...$args);
}

/**
 * Apply filters and rethrow exceptions (for permission guard filters).
 */
function apply_filters_strict($filter_name, $value, ...$args) {
    return birkly_run_filters($filter_name, $value, true, ...$args);
}

function birkly_run_filters($filter_name, $value, $strict, ...$args) {
    global $birkly_filters;
    
    if (!isset($birkly_filters[$filter_name])) {
        return $value;
    }
    
    $filtered_value = $value;
    
    foreach ($birkly_filters[$filter_name] as $filter_data) {
        $callback = $filter_data['callback'];
        $plugin_id = $filter_data['plugin_id'];
        
        $should_execute = true;
        if ($plugin_id !== null) {
            $plugin = get_all_plugins()[$plugin_id] ?? null;
            if ($plugin && !($plugin['active'] ?? false)) {
                $should_execute = false;
            }
        }
        
        if ($should_execute) {
            try {
                $filtered_value = call_user_func_array($callback, array_merge([$filtered_value], $args));
            } catch (Exception $e) {
                error_log("Filter error in '$filter_name' from plugin '$plugin_id': " . $e->getMessage());
                if ($strict) {
                    throw $e;
                }
            } catch (Error $e) {
                error_log("Fatal filter error in '$filter_name' from plugin '$plugin_id': " . $e->getMessage());
                if ($strict) {
                    throw $e;
                }
            }
        }
    }
    
    return $filtered_value;
}

// Register a collection (id = slug or name, path = directory or file, options = array)
function register_collection($id, $path, $options = []) {
    global $birkly_collections;
    $birkly_collections[$id] = [
        'path' => $path,
        'options' => $options
    ];
}

// Register an API endpoint (route = string, file = PHP file to handle it)
// api/index.php reads birkly_api_endpoints; keep haru_api_endpoints for backward compatibility.
function register_api_endpoint($route, $file) {
    if (!isset($GLOBALS['birkly_api_endpoints']) || !is_array($GLOBALS['birkly_api_endpoints'])) {
        $GLOBALS['birkly_api_endpoints'] = [];
    }
    $GLOBALS['birkly_api_endpoints'][$route] = $file;
    if (!isset($GLOBALS['haru_api_endpoints'])) {
        $GLOBALS['haru_api_endpoints'] = [];
    }
    $GLOBALS['haru_api_endpoints'][$route] = $file;
}

// Register an admin page (slug = string, file = HTML/JS file or handler)
function register_admin_page($slug, $file) {
    global $birkly_admin_pages;
    $birkly_admin_pages[$slug] = $file;
}

// Enhanced plugin loader: supports both standalone .php files and plugin folders with plugin.php
function load_plugins($plugins_dir = null) {
    global $birkly_plugins;
    
    // Use absolute path if not provided
    if ($plugins_dir === null) {
        $plugins_dir = __DIR__ . '/../plugins';
    }
    
    // Load plugin states first
    load_plugin_states();
    $settingsStorage = new PluginStorage(__DIR__ . '/../settings');
    $persistedPluginStates = $settingsStorage->read('plugins.json') ?? [];
    if (birkly_migrate_removed_plugin_states($persistedPluginStates)) {
        $settingsStorage->write('plugins.json', $persistedPluginStates);
    }
    $automationDefaultActivated = false;
    
    // Check if plugins directory exists
    if (!is_dir($plugins_dir)) {
        error_log("Plugins directory not found: " . $plugins_dir);
        return;
    }
    
    foreach (scandir($plugins_dir) as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $plugins_dir . '/' . $file;
        
        if (is_dir($path) && file_exists($path . '/plugin.php')) {
            // Load plugin from directory
            $plugin_id = $file;
            $metadata = load_plugin_metadata($path);
            
            // Initialize plugin in registry, applying persisted state on first discovery
            $existing_state = $birkly_plugins[$plugin_id] ?? [];
            if (isset($persistedPluginStates[$plugin_id])) {
                $defaultActive = (bool) ($persistedPluginStates[$plugin_id]['active'] ?? false);
                $defaultSettings = $persistedPluginStates[$plugin_id]['settings'] ?? [];
            } else {
                $defaultActive = (bool) ($existing_state['active'] ?? false);
                $defaultSettings = $existing_state['settings'] ?? [];
                // Automation was core before P3-M6 — default active on first discovery
                if ($plugin_id === 'automation') {
                    $defaultActive = true;
                    $automationDefaultActivated = true;
                }
            }
            $birkly_plugins[$plugin_id] = array_merge($metadata, [
                'id' => $plugin_id,
                'path' => $path,
                'active' => $defaultActive,
                'settings' => $defaultSettings
            ]);
            
            // Only load plugin code if it's active
            if ($birkly_plugins[$plugin_id]['active']) {
                include_once $path . '/plugin.php';
            }
            
        } elseif (is_file($path) && substr($file, -4) === '.php' && $file !== 'plugins.php') {
            // Load standalone plugin file (but not the plugins.php API file)
            $plugin_id = substr($file, 0, -4);
            $metadata = [
                'name' => ucwords(str_replace(['_', '-'], ' ', $plugin_id)),
                'version' => '1.0.0',
                'description' => 'Standalone plugin: ' . $plugin_id,
                'author' => 'Unknown',
                'requires' => '1.0.0',
                'hooks' => [],
                'permissions' => [],
                'settings_schema' => []
            ];
            
            // Initialize plugin in registry, applying persisted state on first discovery
            $existing_state = $birkly_plugins[$plugin_id] ?? [];
            if (isset($persistedPluginStates[$plugin_id])) {
                $defaultActive = (bool) ($persistedPluginStates[$plugin_id]['active'] ?? false);
                $defaultSettings = $persistedPluginStates[$plugin_id]['settings'] ?? [];
            } else {
                $defaultActive = (bool) ($existing_state['active'] ?? false);
                $defaultSettings = $existing_state['settings'] ?? [];
            }
            $birkly_plugins[$plugin_id] = array_merge($metadata, [
                'id' => $plugin_id,
                'path' => $path,
                'active' => $defaultActive,
                'settings' => $defaultSettings
            ]);
            
            if ($birkly_plugins[$plugin_id]['active']) {
                include_once $path;
            }
        }
    }

    // Cleanup: remove empty legacy plugin folders (e.g. after renames)
    birkly_cleanup_empty_plugin_dir($plugins_dir . '/social_demo');
    birkly_cleanup_empty_plugin_dir($plugins_dir . '/payments');
    birkly_cleanup_empty_plugin_dir($plugins_dir . '/shopping_cart');

    if ($automationDefaultActivated) {
        save_plugin_states();
    }

    // Re-apply persisted state after discovery (covers reload paths)
    load_plugin_states();
}

// Plugin settings management
function get_plugin_setting($plugin_id, $key, $default = null) {
    global $birkly_plugins;
    if (isset($birkly_plugins[$plugin_id]['settings'][$key])) {
        return $birkly_plugins[$plugin_id]['settings'][$key];
    }
    return $default;
}

function set_plugin_setting($plugin_id, $key, $value) {
    global $birkly_plugins;
    if (isset($birkly_plugins[$plugin_id])) {
        $birkly_plugins[$plugin_id]['settings'][$key] = $value;
        save_plugin_states();
    }
}

// Get all plugins with their metadata and state
function get_all_plugins() {
    global $birkly_plugins;
    
    // Always ensure we have the latest plugin states
    load_plugin_states();
    
    return $birkly_plugins;
}

// Get only active plugins
function get_active_plugins() {
    global $birkly_plugins;
    
    // Always ensure we have the latest plugin states
    load_plugin_states();
    
    return array_filter($birkly_plugins, function($plugin) {
        return $plugin['active'] ?? false;
    });
}

// Plugin validation
function validate_plugin($plugin_path) {
    $errors = [];
    
    // Check if plugin directory exists
    if (!is_dir($plugin_path)) {
        $errors[] = "Plugin directory does not exist";
        return $errors;
    }
    
    // Check if plugin.php exists
    if (!file_exists($plugin_path . '/plugin.php')) {
        $errors[] = "plugin.php file is missing";
    }
    
    // Check plugin.json if it exists
    if (file_exists($plugin_path . '/plugin.json')) {
        $metadata = json_decode(file_get_contents($plugin_path . '/plugin.json'), true);
        if (!$metadata) {
            $errors[] = "Invalid plugin.json format";
        }
    }
    
    // Check file permissions
    if (!is_readable($plugin_path . '/plugin.php')) {
        $errors[] = "plugin.php is not readable";
    }
    
    return $errors;
}

// Best-effort cleanup: remove empty legacy plugin directories (if any files were deleted).
// This avoids confusing "ghost plugins" when a plugin was renamed.
// NOTE: No recursion, only removes the directory if it's already empty.
function birkly_cleanup_empty_plugin_dir($plugin_dir) {
    if (!is_dir($plugin_dir)) return;
    $items = array_values(array_filter(scandir($plugin_dir), function($x) { return $x !== '.' && $x !== '..'; }));
    if (count($items) === 0) {
        @rmdir($plugin_dir);
    }
}

/**
 * Installed plugin directory (marketplace zip or dev tree).
 */
function birkly_plugin_install_path(string $plugin_id): ?string
{
    global $birkly_plugins;
    if (!isset($birkly_plugins) || $birkly_plugins === []) {
        if (function_exists('load_plugins')) {
            load_plugins();
        }
    }
    $path = $birkly_plugins[$plugin_id]['path'] ?? null;
    if (is_string($path) && $path !== '' && is_dir($path)) {
        return $path;
    }
    return null;
}

/**
 * Require a file from another installed plugin (cross-package integration).
 */
function birkly_plugin_require(string $plugin_id, string $relative_path): bool
{
    $base = birkly_plugin_install_path($plugin_id);
    if ($base === null) {
        return false;
    }
    $file = $base . '/' . ltrim(str_replace('\\', '/', $relative_path), '/');
    if (!is_file($file)) {
        return false;
    }
    require_once $file;
    return true;
}

// Load plugin capability system
require_once __DIR__ . '/plugins/capabilities.php';
require_once __DIR__ . '/plugins/permissions.php';
require_once __DIR__ . '/plugins/ui_injection.php';
require_once __DIR__ . '/plugins/services.php';
require_once __DIR__ . '/plugins/events.php';
require_once __DIR__ . '/plugins/shared_data.php';
require_once __DIR__ . '/plugins/security.php';
require_once __DIR__ . '/plugins/validation.php';
require_once __DIR__ . '/plugins/template_functions.php';
require_once __DIR__ . '/plugins/docs_shelf.php';
require_once __DIR__ . '/plugins/admin_shelf.php';
require_once __DIR__ . '/plugins/fieldtype_shelf.php';

// Load core admin functionality
require_once __DIR__ . '/admin.php';

// Load core collections
require_once __DIR__ . '/pages.php';

// Load dependency checking
if (file_exists(__DIR__ . '/plugins/dependencies.php')) {
    require_once __DIR__ . '/plugins/dependencies.php';
}

// Load migrations system
if (file_exists(__DIR__ . '/plugins/migrations.php')) {
    require_once __DIR__ . '/plugins/migrations.php';
}

// Load scheduled tasks system
if (file_exists(__DIR__ . '/plugins/scheduled_tasks.php')) {
    require_once __DIR__ . '/plugins/scheduled_tasks.php';
}

require_once __DIR__ . '/plugin_availability.php';

// Load plugins at startup (only once)
if (!isset($GLOBALS['haru_plugins_loaded'])) {
    load_plugins();
    
    $GLOBALS['haru_plugins_loaded'] = true;
} 