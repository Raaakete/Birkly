<?php
/**
 * Automation Plugin — self-contained workflow automation for Birkly.
 */

$automation_plugin_dir = __DIR__;

require_once $automation_plugin_dir . '/automation.php';
require_once $automation_plugin_dir . '/init.php';

if (function_exists('register_admin_page')) {
    register_admin_page('automation', $automation_plugin_dir . '/admin/automation.html');
    register_admin_page('automation-editor', $automation_plugin_dir . '/admin/automation-editor.html');
}

if (function_exists('register_plugin_admin_meta')) {
    register_plugin_admin_meta('automation', [
        'title' => 'Automation',
        'root_page' => 'automation',
        'permission' => 'manage_content',
        'page_slugs' => ['automation', 'automation-editor'],
        'sub_pages' => [
            'automation-editor' => ['title' => 'Editor', 'permission' => 'manage_content'],
        ],
        'sidebar' => [
            'show' => true,
            'label' => 'Automation',
            'order' => 25,
            'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>',
        ],
    ]);
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

// Sample custom action for plugin authors
if (function_exists('register_plugin_action')) {
    register_plugin_action('sample_notify', [
        'name' => 'Sample Notify (Plugin)',
        'description' => 'Example plugin action: logs a message with template variables',
        'category' => 'Plugin Samples',
        'schema' => [
            'message' => [
                'type' => 'textarea',
                'label' => 'Message',
                'description' => 'Supports {{entry.title}} and other template variables',
                'required' => true,
                'rows' => 3,
            ],
        ],
        'handler' => function (array $config, array $context) {
            $message = $config['message'] ?? '';
            if (function_exists('render_automation_template')) {
                $message = render_automation_template($message, $context);
            }
            error_log('[automation sample_notify] ' . $message);
            return ['logged' => true, 'message' => $message];
        },
    ]);
}

require __DIR__ . '/docs/register_docs.php';

global $birkly_plugins;
if (isset($birkly_plugins['automation'])) {
    $birkly_plugins['automation']['dashboard_widgets_callback'] = 'automation_dashboard_widgets';
}
