<?php
/**
 * Automation plugin bootstrap — load plugin code when active.
 */

function automation_plugin_active(): bool
{
    if (!function_exists('birkly_is_plugin_active')) {
        require_once __DIR__ . '/../plugins.php';
    }
    return birkly_is_plugin_active('automation');
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
