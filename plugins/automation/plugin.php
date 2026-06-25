<?php
/**
 * Automation plugin — cron helpers and sample custom action block.
 *
 * Core automation engine lives in core/automation/ until P3-M6 extraction is complete.
 * This plugin registers a sample action and documents queue/cron usage.
 */

require_once __DIR__ . '/../../core/automation/plugin_integration.php';

if (!function_exists('register_plugin_action')) {
    return;
}

// Sample custom action — plugins can register their own blocks this way.
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
