<?php
/**
 * Automation System Initialization
 * 
 * Loads all automation blocks and initializes the system.
 */

// Load block registry first
require_once __DIR__ . '/registry.php';

// Context helpers before action blocks (email defaults / template vars)
require_once __DIR__ . '/includes/context.php';
require_once __DIR__ . '/includes/setup_health.php';
require_once __DIR__ . '/includes/side_effects.php';

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
