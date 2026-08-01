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
    'summary' => 'Workflow automation with triggers, conditions, actions, Setup (SMTP/queue), dry-run vs live execution, Slack/webhooks, and examples.',
    'badge' => 'Extension',
    'docs' => [
        'ai' => __DIR__ . '/ai-context.md',
        'help' => __DIR__ . '/help-overview.md',
        'help_topics' => [
            ['id' => 'automation-getting-started', 'title' => 'Getting started', 'file' => __DIR__ . '/help-getting-started.md', 'keywords' => ['start', 'first', 'tutorial', 'create']],
            ['id' => 'automation-setup', 'title' => 'Setup & connections', 'file' => __DIR__ . '/help-setup-connections.md', 'keywords' => ['setup', 'smtp', 'queue', 'cron', 'connection']],
            ['id' => 'automation-email', 'title' => 'Email delivery', 'file' => __DIR__ . '/help-email.md', 'keywords' => ['email', 'smtp', 'mail', 'inbox']],
            ['id' => 'automation-slack-webhooks', 'title' => 'Slack, Teams & webhooks', 'file' => __DIR__ . '/help-slack-webhooks.md', 'keywords' => ['slack', 'teams', 'webhook']],
            ['id' => 'automation-triggers', 'title' => 'Triggers & blocks', 'file' => __DIR__ . '/help-triggers.md', 'keywords' => ['trigger', 'condition', 'action', 'schedule']],
            ['id' => 'automation-workflows', 'title' => 'Workflow builder', 'file' => __DIR__ . '/help-workflows.md', 'keywords' => ['workflow', 'canvas', 'dry-run', 'graph']],
            ['id' => 'automation-examples', 'title' => 'Examples', 'file' => __DIR__ . '/help-examples.md', 'keywords' => ['example', 'recipe', 'template']],
            ['id' => 'automation-troubleshooting', 'title' => 'Troubleshooting', 'file' => __DIR__ . '/help-troubleshooting.md', 'keywords' => ['troubleshoot', 'debug', 'empty logs', 'no email']],
        ],
        'public_path' => '05-automation/automation-overview.html',
    ],
    'mcp_topic_slug' => 'automation',
    'keywords' => ['automation', 'workflow', 'trigger', 'webhook', 'schedule', 'action', 'smtp', 'email', 'slack'],
]);
