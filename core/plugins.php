<?php
// core/plugins.php: Enhanced Plugin loader and hook system for HaruCMS

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

$haru_hooks = [];
// Global registries for plugin system
$haru_collections = [];
$haru_api_endpoints = [];
$haru_admin_pages = [];
$haru_plugins = []; // Plugin metadata and state
$haru_plugin_settings = []; // Plugin-specific settings

// Plugin state management
function get_plugin_state($plugin_id) {
    global $haru_plugins;
    return $haru_plugins[$plugin_id]['active'] ?? false;
}

function set_plugin_state($plugin_id, $active) {
    global $haru_plugins;
    if (isset($haru_plugins[$plugin_id])) {
        $haru_plugins[$plugin_id]['active'] = $active;
        save_plugin_states();
        
        // Call activation/deactivation hooks
        if ($active) {
            do_action('plugin_activated', $plugin_id);
        } else {
            do_action('plugin_deactivated', $plugin_id);
        }
    }
}

function save_plugin_states() {
    global $haru_plugins;
    $states = [];
    foreach ($haru_plugins as $id => $plugin) {
        $states[$id] = [
            'active' => $plugin['active'] ?? false,
            'settings' => $plugin['settings'] ?? []
        ];
    }
    
    $storage = new PluginStorage(__DIR__ . '/../settings');
    $storage->write('plugins.json', $states);
}

function load_plugin_states() {
    global $haru_plugins;
    $storage = new PluginStorage(__DIR__ . '/../settings');
    $states = $storage->read('plugins.json') ?? [];
    
    foreach ($states as $id => $state) {
        if (isset($haru_plugins[$id])) {
            $haru_plugins[$id]['active'] = $state['active'] ?? false;
            $haru_plugins[$id]['settings'] = $state['settings'] ?? [];
        }
    }
}

// Plugin metadata management
function load_plugin_metadata($plugin_path) {
    $metadata_file = $plugin_path . '/plugin.json';
    if (file_exists($metadata_file)) {
        $metadata = json_decode(file_get_contents($metadata_file), true);
        if ($metadata) {
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
        'settings_schema' => []
    ];
}

function register_hook($event, $callback) {
    global $haru_hooks;
    if (!isset($haru_hooks[$event])) {
        $haru_hooks[$event] = [];
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
    
    $haru_hooks[$event][] = [
        'callback' => $callback,
        'plugin_id' => $plugin_id
    ];
}

function do_action($event, ...$args) {
    global $haru_hooks;
    if (isset($haru_hooks[$event])) {
        foreach ($haru_hooks[$event] as $hook_data) {
            $callback = $hook_data['callback'];
            $plugin_id = $hook_data['plugin_id'];
            
            // Only execute hooks from active plugins (or core hooks)
            $should_execute = true;
            if ($plugin_id !== null) {
                $plugin = get_all_plugins()[$plugin_id] ?? null;
                if ($plugin && !($plugin['active'] ?? false)) {
                    $should_execute = false;
                }
            }
            
            if ($should_execute) {
                // For dashboard_stats, handle the array reference specially
                if ($event === 'dashboard_stats' && !empty($args) && is_array($args[0])) {
                    $callback($args[0]); // Pass the array directly by reference
                } else {
                    call_user_func_array($callback, $args);
                }
            }
        }
    }
}

// Register a collection (id = slug or name, path = directory or file, options = array)
function register_collection($id, $path, $options = []) {
    global $haru_collections;
    $haru_collections[$id] = [
        'path' => $path,
        'options' => $options
    ];
}

// Register an API endpoint (route = string, file = PHP file to handle it)
function register_api_endpoint($route, $file) {
    global $haru_api_endpoints;
    $haru_api_endpoints[$route] = $file;
}

// Register an admin page (slug = string, file = HTML/JS file or handler)
function register_admin_page($slug, $file) {
    global $haru_admin_pages;
    $haru_admin_pages[$slug] = $file;
}

// Enhanced plugin loader: supports both standalone .php files and plugin folders with plugin.php
function load_plugins($plugins_dir = '../plugins') {
    global $haru_plugins;
    
    // Load plugin states first
    load_plugin_states();
    
    foreach (scandir($plugins_dir) as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $plugins_dir . '/' . $file;
        
        if (is_dir($path) && file_exists($path . '/plugin.php')) {
            // Load plugin from directory
            $plugin_id = $file;
            $metadata = load_plugin_metadata($path);
            
            // Initialize plugin in registry, preserving existing state
            $existing_state = $haru_plugins[$plugin_id] ?? [];
            $haru_plugins[$plugin_id] = array_merge($metadata, [
                'id' => $plugin_id,
                'path' => $path,
                'active' => $existing_state['active'] ?? true, // Default to active for new plugins
                'settings' => $existing_state['settings'] ?? []
            ]);
            
            // Only load plugin code if it's active
            if ($haru_plugins[$plugin_id]['active']) {
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
            
            // Initialize plugin in registry, preserving existing state
            $existing_state = $haru_plugins[$plugin_id] ?? [];
            $haru_plugins[$plugin_id] = array_merge($metadata, [
                'id' => $plugin_id,
                'path' => $path,
                'active' => $existing_state['active'] ?? true,
                'settings' => $existing_state['settings'] ?? []
            ]);
            
            if ($haru_plugins[$plugin_id]['active']) {
                include_once $path;
            }
        }
    }
}

// Plugin settings management
function get_plugin_setting($plugin_id, $key, $default = null) {
    global $haru_plugins;
    if (isset($haru_plugins[$plugin_id]['settings'][$key])) {
        return $haru_plugins[$plugin_id]['settings'][$key];
    }
    return $default;
}

function set_plugin_setting($plugin_id, $key, $value) {
    global $haru_plugins;
    if (isset($haru_plugins[$plugin_id])) {
        $haru_plugins[$plugin_id]['settings'][$key] = $value;
        save_plugin_states();
    }
}

// Get all plugins with their metadata and state
function get_all_plugins() {
    global $haru_plugins;
    
    // Always ensure we have the latest plugin states
    load_plugin_states();
    
    return $haru_plugins;
}

// Get only active plugins
function get_active_plugins() {
    global $haru_plugins;
    
    // Always ensure we have the latest plugin states
    load_plugin_states();
    
    return array_filter($haru_plugins, function($plugin) {
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

// Load core admin functionality
require_once __DIR__ . '/admin.php';

// Load core collections
require_once __DIR__ . '/pages.php';

// Load plugins at startup (only once)
if (!isset($GLOBALS['haru_plugins_loaded'])) {
    load_plugins();
    $GLOBALS['haru_plugins_loaded'] = true;
} 