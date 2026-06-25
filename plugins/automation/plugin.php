<?php
/**
 * Automation Plugin — self-contained workflow automation for Birkly.
 */

$automation_plugin_dir = __DIR__;

require_once $automation_plugin_dir . '/automation.php';
require_once $automation_plugin_dir . '/init.php';

if (function_exists('register_admin_page')) {
    register_admin_page('automation', $automation_plugin_dir . '/admin/automation.html');
}

if (function_exists('register_hook')) {
    register_hook('plugin_activated', function ($plugin_id) {
        if ($plugin_id === 'automation' && function_exists('init_plugin_automation_blocks')) {
            init_plugin_automation_blocks();
        }
    });
}

if (function_exists('init_plugin_automation_blocks')) {
    init_plugin_automation_blocks();
}
