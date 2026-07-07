<?php
// api/plugins.php - Plugin management API

// Prevent direct execution if this file is being included
if (!defined('HARU_API_REQUEST')) {
    define('HARU_API_REQUEST', true);
}

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Only set headers if this file is being called directly (not included)
// When included via admin/index.php, headers are already sent, so skip
if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    header('Content-Type: application/json');
}

require_once 'auth.php';
require_once 'validate.php';
require_once '../core/csrf.php';
require_once 'log.php';
require_once '../core/plugins.php';
require_once '../core/plugins/security.php';
require_once '../core/plugins/validation.php';
require_once '../core/plugins/health.php';

// Use the PluginStorage class from core/plugins.php
$storage = new PluginStorage('../settings');

// Enhanced input sanitization with validation
$rawInput = json_decode(file_get_contents('php://input'), true);
$input = [];
if (is_array($rawInput)) {
    foreach ($rawInput as $key => $value) {
        // Basic sanitization
        if (is_string($value)) {
            $input[$key] = validate_plugin_input($value, 'string', ['max_length' => 10000]);
        } elseif (is_array($value)) {
            $input[$key] = validate_plugin_input($value, 'array');
        } else {
            $input[$key] = $value;
        }
    }
}
$input = array_merge($input, sanitize_input($input));
$action = $input['action'] ?? $_GET['action'] ?? '';

// All actions require authentication and manage_plugins permission
requireAuth('manage_plugins');

// Get plugin navigation
if ($action === 'get_navigation') {
    $navigation = [];
    $plugins = get_all_plugins();
    
    foreach ($plugins as $id => $plugin) {
        if ($plugin['active'] ?? false) {
            // Check if plugin has navigation defined
            if (isset($plugin['navigation'])) {
                $navigation[] = [
                    'id' => $id,
                    'name' => $plugin['navigation']['name'] ?? $plugin['name'],
                    'page' => $plugin['navigation']['page'] ?? $id,
                    'icon' => $plugin['navigation']['icon'] ?? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path></svg>'
                ];
            }
        }
    }
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'navigation' => $navigation
        ]
    ]);
    exit;
}

// Get all plugins
if ($action === 'list') {
    $plugins = get_all_plugins();
    $plugins_list = [];
    
    foreach ($plugins as $id => $plugin) {
        $health = birkly_plugin_health_check((string) $id);
        $plugins_list[] = [
            'id' => $id,
            'name' => $plugin['name'] ?? ucwords(str_replace(['_', '-'], ' ', $id)),
            'version' => $plugin['version'] ?? '1.0.0',
            'description' => $plugin['description'] ?? '',
            'author' => $plugin['author'] ?? 'Unknown',
            'active' => $plugin['active'] ?? false,
            'path' => $plugin['path'] ?? '',
            'has_settings' => !empty($plugin['settings_schema'] ?? []),
            'settings' => $plugin['settings'] ?? [],
            'marketplace_slug' => $plugin['marketplace_slug'] ?? null,
            'license_ref' => $plugin['license_ref'] ?? null,
            'from_marketplace' => !empty($plugin['marketplace_slug']),
            'health' => $health,
        ];
    }
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'plugins' => $plugins_list
        ]
    ]);
    exit;
}

if ($action === 'health') {
    $pluginId = trim((string) ($input['plugin_id'] ?? $_GET['plugin_id'] ?? ''));
    if ($pluginId !== '') {
        echo json_encode([
            'status' => 'ok',
            'data' => birkly_plugin_health_check($pluginId),
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'ok',
        'data' => ['plugins' => birkly_plugin_health_all()],
    ]);
    exit;
}

// Check plugin dependencies
if ($action === 'check_dependencies') {
    requireAuth('manage_plugins');
    
    $plugin_id = $input['plugin_id'] ?? $_GET['plugin_id'] ?? '';
    
    if (empty($plugin_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Plugin ID required']);
        exit;
    }
    
    $errors = check_plugin_dependencies($plugin_id);
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'valid' => empty($errors),
            'errors' => $errors
        ]
    ]);
    exit;
}

// Activate plugin
if ($action === 'activate') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_plugin_csrf($csrf)) {
        log_plugin_security_event('permission_denied', ['action' => 'plugin_activate', 'reason' => 'invalid CSRF']);
        log_security_event('permission_denied', ['action' => 'plugin_activate', 'reason' => 'invalid CSRF']);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    // Validate plugin_id input
    $plugin_id = validate_plugin_input($input['plugin_id'] ?? '', 'string', [
        'required' => true,
        'max_length' => 100,
        'pattern' => '/^[a-z0-9_-]+$/i'
    ]);
    
    if ($plugin_id === false || empty($plugin_id)) {
        log_plugin_security_event('invalid_input', ['action' => 'plugin_activate', 'field' => 'plugin_id']);
        echo json_encode(['status' => 'error', 'message' => 'Invalid plugin ID']);
        exit;
    }
    
    $plugins = get_all_plugins();
    if (!isset($plugins[$plugin_id])) {
        echo json_encode(['status' => 'error', 'message' => 'Plugin not found']);
        exit;
    }
    
    // Validate plugin before activation
    $errors = validate_plugin($plugins[$plugin_id]['path']);
    if (!empty($errors)) {
        echo json_encode(['status' => 'error', 'message' => 'Plugin validation failed: ' . implode(', ', $errors)]);
        exit;
    }
    
    // Check dependencies before activation
    if (function_exists('validate_plugin_activation')) {
        $validation = validate_plugin_activation($plugin_id);
        if (!$validation['valid']) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Plugin dependencies not met',
                'errors' => $validation['errors']
            ]);
            exit;
        }
    }
    
    // Run migrations if needed
    if (function_exists('plugin_needs_migration') && function_exists('run_plugin_migrations')) {
        global $birkly_plugins;
        $plugin = $birkly_plugins[$plugin_id] ?? null;
        if ($plugin) {
            $current_version = $plugin['version'] ?? '1.0.0';
            $last_migrated = function_exists('get_plugin_last_migrated_version') 
                ? get_plugin_last_migrated_version($plugin_id) 
                : null;
            
            if ($last_migrated === null || version_compare($current_version, $last_migrated, '>')) {
                // Run migrations
                $migration_result = run_plugin_migrations($plugin_id, $last_migrated ?? '0.0.0', $current_version);
                if (!$migration_result['success']) {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Plugin activation failed during migration',
                        'migration_errors' => $migration_result['messages']
                    ]);
                    exit;
                }
            }
        }
    }
    
    set_plugin_state($plugin_id, true);
    
    log_security_event('plugin_activated', ['plugin' => $plugin_id, 'user' => $_SESSION['user']['email'] ?? 'unknown']);
    echo json_encode([
        'status' => 'ok',
        'message' => 'Plugin activated successfully'
    ]);
    exit;
}

// Deactivate plugin
if ($action === 'deactivate') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        log_security_event('permission_denied', ['action' => 'plugin_deactivate', 'reason' => 'invalid CSRF']);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $plugin_id = $input['plugin_id'] ?? '';
    if (empty($plugin_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Plugin ID required']);
        exit;
    }
    
    $plugins = get_all_plugins();
    if (!isset($plugins[$plugin_id])) {
        echo json_encode(['status' => 'error', 'message' => 'Plugin not found']);
        exit;
    }
    
    set_plugin_state($plugin_id, false);
    
    // Reload plugin states to ensure fresh data
    load_plugin_states();
    
    log_security_event('plugin_deactivated', ['plugin' => $plugin_id, 'user' => $_SESSION['user']['email'] ?? 'unknown']);
    echo json_encode([
        'status' => 'ok',
        'message' => 'Plugin deactivated successfully'
    ]);
    exit;
}

// Get plugin settings
if ($action === 'get_settings') {
    $plugin_id = $input['plugin_id'] ?? $_GET['plugin_id'] ?? '';
    if (empty($plugin_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Plugin ID required']);
        exit;
    }
    
    $plugins = get_all_plugins();
    if (!isset($plugins[$plugin_id])) {
        echo json_encode(['status' => 'error', 'message' => 'Plugin not found']);
        exit;
    }
    
    $plugin = $plugins[$plugin_id];
    $settings_schema = $plugin['settings_schema'] ?? [];
    $current_settings = $plugin['settings'] ?? [];
    
    // Merge current settings with schema defaults
    $settings = [];
    foreach ($settings_schema as $key => $schema) {
        $settings[$key] = $current_settings[$key] ?? ($schema['default'] ?? null);
    }
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'plugin' => [
                'id' => $plugin_id,
                'name' => $plugin['name'] ?? ucwords(str_replace(['_', '-'], ' ', $plugin_id)),
                'description' => $plugin['description'] ?? ''
            ],
            'settings_schema' => $settings_schema,
            'settings' => $settings
        ]
    ]);
    exit;
}

// Save plugin settings
if ($action === 'save_settings') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        log_security_event('permission_denied', ['action' => 'plugin_settings_save', 'reason' => 'invalid CSRF']);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $plugin_id = $input['plugin_id'] ?? '';
    $settings = $input['settings'] ?? [];
    
    if (empty($plugin_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Plugin ID required']);
        exit;
    }
    
    $plugins = get_all_plugins();
    if (!isset($plugins[$plugin_id])) {
        echo json_encode(['status' => 'error', 'message' => 'Plugin not found']);
        exit;
    }
    
    $plugin = $plugins[$plugin_id];
    $settings_schema = $plugin['settings_schema'] ?? [];
    
    // Validate settings against schema
    $validated_settings = [];
    foreach ($settings as $key => $value) {
        if (isset($settings_schema[$key])) {
            $schema = $settings_schema[$key];
            
            // Type validation
            switch ($schema['type'] ?? 'string') {
                case 'boolean':
                    $validated_settings[$key] = (bool) $value;
                    break;
                case 'number':
                    $validated_settings[$key] = is_numeric($value) ? (float) $value : ($schema['default'] ?? 0);
                    break;
                case 'integer':
                    $validated_settings[$key] = is_numeric($value) ? (int) $value : ($schema['default'] ?? 0);
                    break;
                default:
                    $validated_settings[$key] = (string) $value;
            }
        }
    }
    
    // Save settings
    foreach ($validated_settings as $key => $value) {
        set_plugin_setting($plugin_id, $key, $value);
    }
    
    log_security_event('plugin_settings_saved', ['plugin' => $plugin_id, 'user' => $_SESSION['user']['email'] ?? 'unknown']);
    echo json_encode([
        'status' => 'ok',
        'message' => 'Plugin settings saved successfully'
    ]);
    exit;
}

// Delete plugin
if ($action === 'delete') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        log_security_event('permission_denied', ['action' => 'plugin_delete', 'reason' => 'invalid CSRF']);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $plugin_id = $input['plugin_id'] ?? '';
    if (empty($plugin_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Plugin ID required']);
        exit;
    }
    
    $plugins = get_all_plugins();
    if (!isset($plugins[$plugin_id])) {
        echo json_encode(['status' => 'error', 'message' => 'Plugin not found']);
        exit;
    }
    
    $plugin = $plugins[$plugin_id];
    $plugin_path = $plugin['path'];
    
    // Deactivate first
    set_plugin_state($plugin_id, false);
    
    // Delete plugin files
    if (is_dir($plugin_path)) {
        // Recursive directory deletion
        function deleteDirectory($dir) {
            if (!is_dir($dir)) return false;
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                if (is_dir($path)) {
                    deleteDirectory($path);
                } else {
                    unlink($path);
                }
            }
            return rmdir($dir);
        }
        
        $deleted = deleteDirectory($plugin_path);
    } else {
        $deleted = unlink($plugin_path);
    }
    
    if ($deleted) {
        log_security_event('plugin_deleted', ['plugin' => $plugin_id, 'user' => $_SESSION['user']['email'] ?? 'unknown']);
        echo json_encode([
            'status' => 'ok',
            'message' => 'Plugin deleted successfully'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to delete plugin files'
        ]);
    }
    exit;
}

// Check for plugin updates
if ($action === 'check_updates') {
    require_once __DIR__ . '/../core/marketplace.php';

    $plugin_id = $input['plugin_id'] ?? $_GET['plugin_id'] ?? '';
    $plugins = get_all_plugins();

    if (!empty($plugin_id)) {
        if (!isset($plugins[$plugin_id])) {
            echo json_encode(['status' => 'error', 'message' => 'Plugin not found']);
            exit;
        }

        $plugin = $plugins[$plugin_id];
        $current_version = $plugin['version'] ?? '1.0.0';
        $slug = (string) ($plugin['marketplace_slug'] ?? '');

        if ($slug === '') {
            echo json_encode([
                'status' => 'ok',
                'data' => [
                    'plugin_id' => $plugin_id,
                    'current_version' => $current_version,
                    'latest_version' => $current_version,
                    'update_available' => false,
                    'from_marketplace' => false,
                ],
            ]);
            exit;
        }

        $check = birkly_marketplace_check_plugin_update($slug, $current_version);
        echo json_encode([
            'status' => 'ok',
            'data' => [
                'plugin_id' => $plugin_id,
                'current_version' => $current_version,
                'latest_version' => $check['latest_version'] ?? $current_version,
                'update_available' => !empty($check['update_available']),
                'from_marketplace' => true,
                'marketplace_slug' => $slug,
                'check_ok' => !empty($check['ok']),
                'error' => $check['error'] ?? null,
            ],
        ]);
    } else {
        $updates = birkly_marketplace_installed_plugin_updates();
        foreach ($plugins as $id => $plugin) {
            if (!isset($updates[$id]) && empty($plugin['marketplace_slug'])) {
                $current_version = $plugin['version'] ?? '1.0.0';
                $updates[$id] = [
                    'plugin_id' => $id,
                    'current_version' => $current_version,
                    'latest_version' => $current_version,
                    'update_available' => false,
                    'from_marketplace' => false,
                ];
            }
        }

        echo json_encode([
            'status' => 'ok',
            'data' => [
                'updates' => $updates,
                'update_count' => count(array_filter($updates, fn ($u) => !empty($u['update_available']))),
            ],
        ]);
    }
    exit;
}

// Update plugin (marketplace-sourced one-click upgrade)
if ($action === 'update') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        log_security_event('permission_denied', ['action' => 'plugin_update', 'reason' => 'invalid CSRF']);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }

    $plugin_id = $input['plugin_id'] ?? '';
    if (empty($plugin_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Plugin ID required']);
        exit;
    }

    $plugins = get_all_plugins();
    if (!isset($plugins[$plugin_id])) {
        echo json_encode(['status' => 'error', 'message' => 'Plugin not found']);
        exit;
    }

    $plugin = $plugins[$plugin_id];
    $slug = (string) ($plugin['marketplace_slug'] ?? '');
    if ($slug === '') {
        echo json_encode(['status' => 'error', 'message' => 'Plugin was not installed from marketplace']);
        exit;
    }

    require_once __DIR__ . '/../core/marketplace.php';

    $current = (string) ($plugin['version'] ?? '0.0.0');
    $check = birkly_marketplace_check_plugin_update($slug, $current);
    if (empty($check['update_available'])) {
        echo json_encode(['status' => 'error', 'message' => 'No update available']);
        exit;
    }

    $wasActive = !empty($plugin['active']);
    if ($wasActive) {
        set_plugin_state($plugin_id, false);
    }

    $targetVersion = (string) ($check['latest_version'] ?? $current);
    $install = birkly_marketplace_install_plugin($slug, $targetVersion, '', true);

    if (!$install['ok']) {
        if ($wasActive) {
            set_plugin_state($plugin_id, true);
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'Upgrade failed: ' . implode('; ', $install['errors'] ?? []),
            'errors' => $install['errors'] ?? [],
        ]);
        exit;
    }

    load_plugins();
    if ($wasActive) {
        set_plugin_state($plugin_id, true);
    }

    log_security_event('plugin_updated', ['plugin' => $plugin_id, 'version' => $targetVersion, 'user' => $_SESSION['user']['email'] ?? 'unknown']);
    echo json_encode([
        'status' => 'ok',
        'message' => 'Plugin upgraded successfully',
        'data' => ['plugin_id' => $plugin_id, 'version' => $targetVersion],
    ]);
    exit;
}

// Default response
echo json_encode([
    'status' => 'error',
    'message' => 'Invalid action',
    'data' => []
]); 