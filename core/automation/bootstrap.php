<?php
/**
 * Automation plugin bootstrap — load plugin code when active.
 */

function automation_plugin_active(): bool
{
    static $active = null;
    if ($active !== null) {
        return $active;
    }

    if (!function_exists('get_plugin_state')) {
        require_once __DIR__ . '/../plugins.php';
    }

    global $birkly_plugins;

    // API routes (e.g. api/automation.php) may run before load_plugins() — read persisted state.
    if (!isset($birkly_plugins['automation'])) {
        $storage = new PluginStorage(__DIR__ . '/../settings');
        $states = $storage->read('plugins.json') ?? [];
        if (array_key_exists('automation', $states)) {
            $active = (bool) ($states['automation']['active'] ?? false);
            return $active;
        }
        // Automation was core before P3-M6 — active when never persisted (matches load_plugins default).
        $active = true;
        return $active;
    }

    $active = function_exists('get_plugin_state') && get_plugin_state('automation');
    return $active;
}

function automation_bootstrap(): bool
{
    static $loaded = false;
    if (!automation_plugin_active()) {
        return false;
    }
    if (!$loaded) {
        require_once __DIR__ . '/../../plugins/automation/automation.php';
        require_once __DIR__ . '/../../plugins/automation/init.php';
        $loaded = true;
    }
    return true;
}
