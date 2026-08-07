<?php
/**
 * Notifications + plugin activity bus integration (P61).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/notification_broadcast.php';
require_once dirname(__DIR__) . '/core/plugin_activity.php';

$failures = 0;

function npa_assert(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '  ' . $detail . "\n";
        }
    }
}

npa_assert('commerce actor is broadcast', isBroadcastNotificationActor('commerce', 'commerce_order_paid'));
npa_assert('events actor is broadcast', isBroadcastNotificationActor('events', 'events_registration'));
npa_assert('user email is not broadcast', !isBroadcastNotificationActor('admin@example.com', 'publish_entry'));
npa_assert('commerce event prefix is broadcast', isBroadcastNotificationActor('someone', 'commerce_order_created'));
npa_assert('skip commerce security.log when bus present', birkly_notifications_skip_security_log_for_bus('commerce_order_paid'));
npa_assert('do not skip publish_entry', !birkly_notifications_skip_security_log_for_bus('publish_entry'));

$dir = birkly_plugin_activity_dir();
$backupFiles = [];
if (is_dir($dir)) {
    foreach (glob($dir . '/*.jsonl') ?: [] as $f) {
        $backupFiles[$f] = file_get_contents($f);
    }
    foreach (glob($dir . '/*.jsonl') ?: [] as $f) {
        @unlink($f);
    }
}

try {
    $emitted = birkly_plugin_activity_emit([
        'plugin' => 'commerce',
        'event' => 'commerce_order_paid',
        'title' => 'Order paid test',
        'notify' => true,
        'nav_attention' => true,
    ]);
    npa_assert('emit ok', is_array($emitted));
    npa_assert('unread includes emit', birkly_plugin_activity_unread_notify_count() === 1);
    npa_assert(
        'extra read ids suppress unread',
        birkly_plugin_activity_unread_notify_count([(string) ($emitted['id'] ?? '')]) === 0
    );
    npa_assert('nav attention after emit', birkly_plugin_activity_has_nav_attention('commerce'));
} finally {
    if (is_dir($dir)) {
        foreach (glob($dir . '/*.jsonl') ?: [] as $f) {
            @unlink($f);
        }
    }
    foreach ($backupFiles as $f => $content) {
        if ($content !== false) {
            file_put_contents($f, $content);
        }
    }
}

exit($failures > 0 ? 1 : 0);
