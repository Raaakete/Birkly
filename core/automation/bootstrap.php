<?php
/**
 * Automation plugin bootstrap — load plugin code when active.
 */

function automation_plugin_active(): bool
{
    static $active = null;
    if ($active === null) {
        if (!function_exists('get_plugin_state')) {
            require_once __DIR__ . '/../plugins.php';
        }
        $active = function_exists('get_plugin_state') && get_plugin_state('automation');
    }
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
