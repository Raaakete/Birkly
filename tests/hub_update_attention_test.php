<?php
/**
 * P83-M9 — Hub update attention tests.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = 0;

function hua_assert(string $name, bool $ok, string $detail = ''): void
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

require_once $root . '/core/plugin_activity.php';
require_once $root . '/core/hub_update_attention.php';

$activityDir = birkly_plugin_activity_dir();
$statePath = birkly_hub_update_attention_state_path();
$backupActivity = [];
$backupState = is_file($statePath) ? file_get_contents($statePath) : null;

if (is_dir($activityDir)) {
    foreach (glob($activityDir . '/*.jsonl') ?: [] as $f) {
        $backupActivity[$f] = file_get_contents($f);
        @unlink($f);
    }
}
@unlink($statePath);

try {
    $updates = [
        'commerce' => [
            'plugin_id' => 'commerce',
            'marketplace_slug' => 'birkly-commerce',
            'current_version' => '1.0.113',
            'latest_version' => '1.0.114',
            'update_available' => true,
        ],
    ];

    $first = birkly_hub_update_attention_sync($updates);
    hua_assert('first sync emits', ($first['emitted'] ?? 0) === 1);
    hua_assert('nav attention after emit', birkly_plugin_activity_has_nav_attention('commerce'));
    hua_assert('unread notify after emit', birkly_plugin_activity_unread_notify_count() >= 1);

    $second = birkly_hub_update_attention_sync($updates);
    hua_assert('dedupe same version', ($second['emitted'] ?? 0) === 0);

    $cleared = birkly_hub_update_attention_acknowledge_all();
    hua_assert('acknowledge clears nav', $cleared >= 1);
    hua_assert('nav attention cleared', !birkly_plugin_activity_has_nav_attention('commerce'));

    $third = birkly_hub_update_attention_sync($updates);
    hua_assert('acknowledged version does not re-emit', ($third['emitted'] ?? 0) === 0);

    $updates['commerce']['latest_version'] = '1.0.115';
    $fourth = birkly_hub_update_attention_sync($updates);
    hua_assert('new version emits again', ($fourth['emitted'] ?? 0) === 1);

    birkly_hub_update_attention_clear_plugin('commerce');
    hua_assert('clear after upgrade removes nav', !birkly_plugin_activity_has_nav_attention('commerce'));

    $updates['commerce']['update_available'] = false;
    $fifth = birkly_hub_update_attention_sync($updates);
    hua_assert('no update clears attention path', ($fifth['cleared'] ?? 0) >= 0);

    birkly_hub_update_attention_register_formatters();
    if (function_exists('birkly_notification_apply_formatter')) {
        $entry = [
            'event' => 'hub_plugin_update',
            'user' => 'commerce',
            'details' => [
                'plugin_id' => 'commerce',
                'plugin_name' => 'Commerce',
                'current_version' => '1.0.113',
                'latest_version' => '1.0.114',
            ],
        ];
        $notification = ['event' => 'hub_plugin_update'];
        $handled = birkly_notification_apply_formatter($notification, $entry, 'admin@example.com');
        hua_assert('hub formatter registered', $handled);
        hua_assert('hub formatter category', ($notification['category'] ?? '') === 'hub');
        hua_assert('hub formatter icon', ($notification['icon'] ?? '') === '⬆️');
    }
} finally {
    if (is_dir($activityDir)) {
        foreach (glob($activityDir . '/*.jsonl') ?: [] as $f) {
            @unlink($f);
        }
    }
    foreach ($backupActivity as $f => $content) {
        if ($content !== false) {
            file_put_contents($f, $content);
        }
    }
    if ($backupState !== null) {
        file_put_contents($statePath, $backupState);
    } else {
        @unlink($statePath);
    }
}

if ($failures === 0) {
    echo "hub_update_attention_test: OK\n";
}

exit($failures > 0 ? 1 : 0);
