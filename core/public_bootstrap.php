<?php
/**
 * core/public_bootstrap.php
 *
 * Lightweight plugin bootstrap for PUBLIC endpoints.
 *
 * Goals:
 * - Keep public endpoints fast and memory-safe
 * - Avoid loading admin UI / heavy core modules
 * - Still allow plugin-provided template functions to work (if plugins are active)
 *
 * Notes:
 * - Plugins are optional. This bootstrap discovers active plugins dynamically via settings/plugins.json.
 * - It does NOT hardcode any plugin IDs or behaviors.
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

if (!function_exists('birkly_public_plugin_states')) {
    function birkly_public_plugin_states(): array {
        static $states = null;
        if (is_array($states)) return $states;
        $path = __DIR__ . '/../settings/plugins.json';
        if (!file_exists($path)) {
            $states = [];
            return $states;
        }
        $decoded = json_decode(file_get_contents($path), true);
        $states = is_array($decoded) ? $decoded : [];
        return $states;
    }
}

if (!function_exists('get_plugin_state')) {
    function get_plugin_state($plugin_id) {
        $states = birkly_public_plugin_states();
        return !empty($states[$plugin_id]['active']);
    }
}

if (!function_exists('get_plugin_setting')) {
    function get_plugin_setting($plugin_id, $key, $default = null) {
        $states = birkly_public_plugin_states();
        $settings = $states[$plugin_id]['settings'] ?? [];
        if (is_array($settings) && array_key_exists($key, $settings)) return $settings[$key];
        return $default;
    }
}

if (!function_exists('get_all_plugins')) {
    function get_all_plugins(): array {
        static $plugins = null;
        if (is_array($plugins)) return $plugins;

        $plugins = [];
        $pluginsDir = __DIR__ . '/../plugins';
        if (!is_dir($pluginsDir)) return $plugins;

        $states = birkly_public_plugin_states();
        foreach (scandir($pluginsDir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $pluginsDir . '/' . $entry;
            if (!is_dir($path)) continue;
            if (!file_exists($path . '/plugin.php')) continue;
            $meta = [
                'id' => $entry,
                'path' => $path,
                'active' => !empty($states[$entry]['active']),
                'settings' => $states[$entry]['settings'] ?? [],
            ];
            $jsonPath = $path . '/plugin.json';
            if (file_exists($jsonPath)) {
                $m = json_decode(file_get_contents($jsonPath), true);
                if (is_array($m)) $meta = array_merge($m, $meta);
            }
            $plugins[$entry] = $meta;
        }

        return $plugins;
    }
}

// Template function registry (core)
require_once __DIR__ . '/plugins/template_functions.php';

/**
 * Load active plugins (public context)
 */
if (!function_exists('birkly_public_load_active_plugins')) {
    function birkly_public_load_active_plugins(): void {
        static $loaded = false;
        if ($loaded) return;
        $loaded = true;

        foreach (get_all_plugins() as $pid => $plugin) {
            if (empty($plugin['active'])) continue;
            $file = $plugin['path'] . '/plugin.php';
            if (file_exists($file)) {
                include_once $file;
            }
        }
    }
}

/**
 * Load only plugins needed for a specific template function (M2-T4).
 * Falls back to loading all active plugins when $name is null.
 */
if (!function_exists('birkly_public_load_plugins_for_template_function')) {
    function birkly_public_load_plugins_for_template_function(?string $name = null): void {
        if ($name === null || $name === '') {
            birkly_public_load_active_plugins();
            return;
        }

        if (function_exists('get_template_function') && get_template_function($name)) {
            return;
        }

        foreach (get_all_plugins() as $pid => $plugin) {
            if (empty($plugin['active'])) continue;
            $file = $plugin['path'] . '/plugin.php';
            if (file_exists($file)) {
                include_once $file;
            }
            if (function_exists('get_template_function') && get_template_function($name)) {
                return;
            }
        }
    }
}

