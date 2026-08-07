<?php
/**
 * Helpers for admin bell notifications that are broadcast to every admin
 * (plugin shop activity, system actors) rather than owned by one user email.
 */

declare(strict_types=1);

/**
 * Plugin/system actors write security.log rows for every admin (not a single user email).
 * Without this, commerce_order_* rows with user="commerce" never reach the bell.
 */
function isBroadcastNotificationActor(string $entryUser, string $event = ''): bool
{
    $user = strtolower(trim($entryUser));
    if (in_array($user, [
        'commerce',
        'events',
        'automation',
        'studio',
        'user_management',
        'system',
        'plugin',
    ], true)) {
        return true;
    }

    $event = strtolower(trim($event));
    foreach (['commerce_', 'events_', 'automation_', 'studio_', 'user_'] as $prefix) {
        if ($event !== '' && str_starts_with($event, $prefix)) {
            return true;
        }
    }

    return false;
}

function birkly_notifications_load_plugin_activity(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $activityFile = dirname(__DIR__) . '/core/plugin_activity.php';
    if (is_file($activityFile)) {
        require_once $activityFile;
    }
    $loaded = true;
}

/**
 * When the P61 activity bus is available, plugin broadcasts are counted/shown
 * from the bus — skip the parallel security.log rows to avoid double badges.
 */
function birkly_notifications_skip_security_log_for_bus(string $event): bool
{
    birkly_notifications_load_plugin_activity();
    if (!function_exists('birkly_plugin_activity_list')) {
        return false;
    }
    $event = strtolower(trim($event));
    foreach (['commerce_', 'events_', 'automation_', 'studio_', 'user_'] as $prefix) {
        if (str_starts_with($event, $prefix)) {
            return true;
        }
    }

    return false;
}
