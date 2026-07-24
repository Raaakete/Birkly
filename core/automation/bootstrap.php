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

    $automationPhp = __DIR__ . '/../../plugins/automation/automation.php';
    $initPhp = __DIR__ . '/../../plugins/automation/init.php';
    if (!is_file($automationPhp) || !is_file($initPhp)) {
        error_log('automation_bootstrap: plugin marked active but files are missing under plugins/automation/');
        return false;
    }

    if (!$loaded) {
        require_once $automationPhp;
        require_once $initPhp;
        $loaded = true;
    }
    return true;
}
