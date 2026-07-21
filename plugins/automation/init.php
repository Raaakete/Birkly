<?php
/**
 * Automation System Initialization
 * 
 * Loads all automation blocks and initializes the system.
 */

// Load block registry first
require_once __DIR__ . '/registry.php';

// Load core blocks
require_once __DIR__ . '/blocks/triggers.php';
require_once __DIR__ . '/blocks/triggers_scheduled.php';
require_once __DIR__ . '/blocks/triggers_webhook.php';
require_once __DIR__ . '/blocks/conditions.php';
require_once __DIR__ . '/blocks/actions.php';
require_once __DIR__ . '/blocks/actions_slack.php';

// Load executor (which depends on blocks)
require_once __DIR__ . '/executor.php';

// Load queue system
require_once __DIR__ . '/queue.php';

// Load scheduler
require_once __DIR__ . '/scheduler.php';

// Load plugin integration
require_once __DIR__ . '/plugin_integration.php';

// Load caching (if available)
if (file_exists(__DIR__ . '/cache.php')) {
    require_once __DIR__ . '/cache.php';
}

/**
 * @return list<array<string, mixed>>
 */
function automation_dashboard_widgets(): array
{
    if (!function_exists('birkly_is_plugin_active') || !birkly_is_plugin_active('automation')) {
        return [];
    }

    return [[
        'id' => 'automation-queue',
        'title' => 'Automation',
        'size' => 'sm',
        'priority' => 70,
        'mount' => 'automationRuns',
        'roles' => ['admin', 'editor'],
        'plugin_id' => 'automation',
        'refresh' => 60,
    ]];
}
