<?php
/**
 * Notification platform shelf (P83-M3)
 *
 * Plugins register broadcast actors and bell formatters during bootstrap.
 * Core notification transport stays generic — no official-plugin literals.
 */

declare(strict_types=1);

$GLOBALS['birkly_notification_formatters'] = [];
$GLOBALS['birkly_notification_broadcast_actors'] = [];
$GLOBALS['birkly_notification_broadcast_prefixes'] = [];

/**
 * Core platform actors that are not owned by a marketplace plugin.
 *
 * @return list<string>
 */
function birkly_notification_core_broadcast_actors(): array
{
    return ['system', 'plugin'];
}

/**
 * Ensure plugin bootstrap has run so shelf registrations exist.
 */
function birkly_bootstrap_plugins_for_notifications(): void
{
    if (!empty($GLOBALS['birkly_notifications_shelf_bootstrapped'])) {
        return;
    }

    if (function_exists('birkly_bootstrap_plugins_for_admin_assets')) {
        birkly_bootstrap_plugins_for_admin_assets();
    } elseif (function_exists('load_plugins')) {
        if (!function_exists('get_all_plugins')) {
            require_once dirname(__DIR__) . '/plugins.php';
        }
        if (!isset($GLOBALS['haru_plugins_loaded'])) {
            load_plugins();
            $GLOBALS['haru_plugins_loaded'] = true;
        }
    }

    $GLOBALS['birkly_notifications_shelf_bootstrapped'] = true;
}

function birkly_is_notification_plugin_active(string $plugin_id): bool
{
    if ($plugin_id === '' || !preg_match('/^[a-z0-9_-]+$/', $plugin_id)) {
        return false;
    }

    if (function_exists('birkly_is_plugin_active')) {
        return birkly_is_plugin_active($plugin_id);
    }

    if (function_exists('get_plugin_state')) {
        return (bool) get_plugin_state($plugin_id);
    }

    $all = function_exists('get_all_plugins') ? get_all_plugins() : [];
    return !empty($all[$plugin_id]['active']);
}

/**
 * Register security.log actors and event prefixes that broadcast to every admin.
 *
 * @param list<string> $actors
 * @param list<string> $event_prefixes
 */
function register_broadcast_actor(string $plugin_id, array $actors, array $event_prefixes = []): bool
{
    global $birkly_notification_broadcast_actors, $birkly_notification_broadcast_prefixes;

    if ($plugin_id === '' || !preg_match('/^[a-z0-9_-]+$/', $plugin_id)) {
        return false;
    }

    if (!birkly_is_notification_plugin_active($plugin_id)) {
        return false;
    }

    foreach ($actors as $actor) {
        $actor = strtolower(trim((string) $actor));
        if ($actor === '' || !preg_match('/^[a-z0-9_-]+$/', $actor)) {
            continue;
        }
        $birkly_notification_broadcast_actors[$actor] = $plugin_id;
    }

    foreach ($event_prefixes as $prefix) {
        $prefix = strtolower(trim((string) $prefix));
        if ($prefix === '') {
            continue;
        }
        $birkly_notification_broadcast_prefixes[] = $prefix;
    }

    $birkly_notification_broadcast_prefixes = array_values(array_unique($birkly_notification_broadcast_prefixes));

    return true;
}

/**
 * Register a formatter for one security.log / activity event name.
 *
 * Callable signature: fn(array $entry, string $currentUserEmail): ?array
 * Return fields: message, icon, color, category, link (subset ok). Return null to hide.
 */
function register_notification_formatter(string $plugin_id, string $event, callable $formatter): bool
{
    global $birkly_notification_formatters;

    if ($plugin_id === '' || $event === '' || !preg_match('/^[a-z0-9_-]+$/', $plugin_id)) {
        return false;
    }

    if (!birkly_is_notification_plugin_active($plugin_id)) {
        return false;
    }

    if (!is_callable($formatter)) {
        return false;
    }

    $event = strtolower(trim($event));
    $birkly_notification_formatters[$event] = [
        'plugin_id' => $plugin_id,
        'formatter' => $formatter,
    ];

    return true;
}

/**
 * @return list<string>
 */
function birkly_notification_registered_actors(): array
{
    birkly_bootstrap_plugins_for_notifications();
    global $birkly_notification_broadcast_actors;

    return array_keys($birkly_notification_broadcast_actors);
}

/**
 * @return list<string>
 */
function birkly_notification_registered_prefixes(): array
{
    birkly_bootstrap_plugins_for_notifications();
    global $birkly_notification_broadcast_prefixes;

    return array_values($birkly_notification_broadcast_prefixes);
}

function birkly_notification_event_matches_prefix(string $event): bool
{
    $event = strtolower(trim($event));
    if ($event === '') {
        return false;
    }

    foreach (birkly_notification_registered_prefixes() as $prefix) {
        if ($prefix !== '' && str_starts_with($event, $prefix)) {
            return true;
        }
    }

    return false;
}

/**
 * Register a core/platform formatter (no active plugin required).
 *
 * Callable signature: fn(array $entry, string $currentUserEmail): ?array
 */
function birkly_notification_register_core_formatter(string $event, callable $formatter): bool
{
    global $birkly_notification_formatters;

    if ($event === '' || !is_callable($formatter)) {
        return false;
    }

    $event = strtolower(trim($event));
    $birkly_notification_formatters[$event] = [
        'plugin_id' => '_core',
        'formatter' => $formatter,
    ];

    return true;
}

/**
 * Apply a registered formatter to a notification payload.
 *
 * @param array<string, mixed> $notification
 * @param array<string, mixed> $entry
 * @return bool True when a formatter handled the event (even if it returned null to skip).
 */
function birkly_notification_apply_formatter(array &$notification, array $entry, string $currentUserEmail): bool
{
    birkly_bootstrap_plugins_for_notifications();
    global $birkly_notification_formatters;

    $event = strtolower(trim((string) ($entry['event'] ?? '')));
    if ($event === '' || !isset($birkly_notification_formatters[$event])) {
        return false;
    }

    $registration = $birkly_notification_formatters[$event];
    $formatted = ($registration['formatter'])($entry, $currentUserEmail);
    if ($formatted === null) {
        return true;
    }

    if (!is_array($formatted)) {
        return true;
    }

    foreach (['message', 'icon', 'color', 'category', 'link'] as $key) {
        if (array_key_exists($key, $formatted)) {
            $notification[$key] = $formatted[$key];
        }
    }

    return true;
}

/**
 * Activity feed message from formatter shelf when available.
 *
 * @param array<string, mixed> $details
 */
function birkly_notification_format_activity_message(string $event, array $details, string $currentUserEmail = ''): ?string
{
    birkly_bootstrap_plugins_for_notifications();

    $entry = [
        'event' => $event,
        'user' => (string) ($details['user'] ?? ''),
        'details' => $details,
        'timestamp' => (string) ($details['timestamp'] ?? date('c')),
    ];

    $notification = [
        'event' => $event,
        'details' => $details,
    ];

    if (!birkly_notification_apply_formatter($notification, $entry, $currentUserEmail)) {
        return null;
    }

    if (!isset($notification['message']) || $notification['message'] === '') {
        return null;
    }

    return (string) $notification['message'];
}
