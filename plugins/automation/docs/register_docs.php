<?php
if (!function_exists('register_plugin_docs') || (function_exists('get_plugin_docs') && get_plugin_docs('automation') !== null)) {
    return;
}
if (!function_exists('automation_plugin_active')) {
    require_once dirname(__DIR__, 3) . '/core/automation/bootstrap.php';
}
if (!automation_plugin_active()) {
    return;
}
register_plugin_docs('automation', [
    'title' => 'Automation',
    'summary' => 'Workflow automation: triggers (entry, schedule, webhook), conditions, ordered actions, dry-run test, queue + cron, extensible via automation_blocks_register.',
    'badge' => 'Extension',
    'docs' => [
        'ai' => __DIR__ . '/ai-context.md',
        'help' => __DIR__ . '/help-overview.md',
        'help_topics' => [
            ['id' => 'automation-triggers', 'title' => 'Triggers & blocks', 'file' => __DIR__ . '/help-triggers.md', 'keywords' => ['trigger', 'webhook', 'schedule']],
            ['id' => 'automation-workflows', 'title' => 'Workflow builder', 'file' => __DIR__ . '/help-workflows.md', 'keywords' => ['workflow', 'dry-run', 'cron']],
        ],
        'public_path' => '05-automation/automation-overview.html',
    ],
    'mcp_topic_slug' => 'automation',
    'keywords' => ['automation', 'workflow', 'trigger', 'webhook', 'schedule', 'action'],
]);
