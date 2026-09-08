<?php
/**
 * P83-M3 — Notification platform shelf tests.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = 0;

function ns_assert(string $name, bool $ok, string $detail = ''): void
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

require_once $root . '/core/plugins/notification_shelf.php';

// Simulate active plugin for registration APIs.
if (!function_exists('birkly_is_plugin_active')) {
    function birkly_is_plugin_active(string $plugin_id): bool
    {
        return $plugin_id === 'test_plugin';
    }
}

register_broadcast_actor('test_plugin', ['test_actor'], ['test_']);
register_notification_formatter('test_plugin', 'test_event', static function (array $entry, string $currentUserEmail): array {
    $details = $entry['details'] ?? [];
    return [
        'message' => (string) ($details['message'] ?? 'Test notification'),
        'icon' => '✅',
        'color' => 'green',
        'category' => 'test',
        'link' => '/admin/?page=test',
    ];
});

ns_assert('registered actor present', in_array('test_actor', birkly_notification_registered_actors(), true));
ns_assert('registered prefix matches', birkly_notification_event_matches_prefix('test_event'));
ns_assert('unknown prefix does not match', !birkly_notification_event_matches_prefix('publish_entry'));

$entry = [
    'event' => 'test_event',
    'user' => 'test_actor',
    'timestamp' => '2026-09-08T12:00:00+00:00',
    'details' => ['message' => 'Shelf formatter message'],
];
$notification = ['event' => 'test_event', 'details' => $entry['details']];
$handled = birkly_notification_apply_formatter($notification, $entry, 'admin@example.com');
ns_assert('formatter handled event', $handled);
ns_assert('formatter message applied', ($notification['message'] ?? '') === 'Shelf formatter message');
ns_assert('formatter category applied', ($notification['category'] ?? '') === 'test');

$activityMessage = birkly_notification_format_activity_message('test_event', ['message' => 'Activity via shelf']);
ns_assert('activity message from shelf', $activityMessage === 'Activity via shelf');

register_notification_formatter('test_plugin', 'test_skip_event', static function (): ?array {
    return null;
});
$skipNotification = ['event' => 'test_skip_event'];
$skipEntry = ['event' => 'test_skip_event', 'user' => 'test_actor', 'details' => []];
$skipHandled = birkly_notification_apply_formatter($skipNotification, $skipEntry, 'admin@example.com');
ns_assert('skip formatter handled', $skipHandled);
ns_assert('skip formatter leaves message unset', !isset($skipNotification['message']));

if ($failures === 0) {
    echo "notification_shelf_test: OK\n";
}

exit($failures > 0 ? 1 : 0);
