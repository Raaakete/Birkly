<?php
/**
 * Plugin availability helpers (P10-M1) — gate core API paths when plugin packages are absent.
 */

function birkly_plugin_dir(string $plugin_id): string
{
    return __DIR__ . '/../plugins/' . $plugin_id;
}

function birkly_plugin_file_exists(string $plugin_id, string $relative_path = ''): bool
{
    $path = birkly_plugin_dir($plugin_id);
    if ($relative_path !== '') {
        $path .= '/' . ltrim($relative_path, '/');
    }
    return is_file($path);
}

/**
 * Require a plugin file when present; return false if missing (no fatal).
 */
function birkly_require_plugin_file(string $plugin_id, string $relative_path): bool
{
    $path = birkly_plugin_dir($plugin_id) . '/' . ltrim($relative_path, '/');
    if (!is_file($path)) {
        return false;
    }
    require_once $path;
    return true;
}

/**
 * True when plugin package is on disk and marked active in settings/registry.
 */
function birkly_plugin_is_installed_and_active(string $plugin_id): bool
{
    if (!birkly_plugin_file_exists($plugin_id, 'plugin.php')) {
        return false;
    }
    if (!function_exists('birkly_is_plugin_active')) {
        return false;
    }
    return birkly_is_plugin_active($plugin_id);
}
