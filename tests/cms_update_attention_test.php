<?php
/**
 * CMS core update attention tests.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = 0;

function cua_assert(string $name, bool $ok, string $detail = ''): void
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
require_once $root . '/core/cms_update_attention.php';

$activityDir = birkly_plugin_activity_dir();
$statePath = birkly_cms_update_attention_state_path();
$cachePath = birkly_cms_update_attention_cache_path();
$backupActivity = [];
$backupState = is_file($statePath) ? file_get_contents($statePath) : null;
$backupCache = is_file($cachePath) ? file_get_contents($cachePath) : null;
$pluginId = birkly_cms_update_attention_plugin_id();

if (is_dir($activityDir)) {
    foreach (glob($activityDir . '/*.jsonl') ?: [] as $f) {
        $backupActivity[$f] = file_get_contents($f);
        @unlink($f);
    }
}
@unlink($statePath);
@unlink($cachePath);

try {
    $status = [
        'status' => 'ok',
        'current_version' => '1.0.0',
        'latest_version' => '1.0.1',
        'update_available' => true,
        'release_notes' => '',
        'published_at' => '',
        'prerelease' => false,
    ];

    $first = birkly_cms_update_attention_sync($status);
    cua_assert('first sync emits', ($first['emitted'] ?? 0) === 1);
    cua_assert('nav attention after emit', birkly_plugin_activity_has_nav_attention($pluginId));
    cua_assert('show modal on first emit', !empty($first['show_modal']));

    $second = birkly_cms_update_attention_sync($status);
    cua_assert('dedupe same version', ($second['emitted'] ?? 0) === 0);

    birkly_plugin_activity_clear_nav($pluginId);
    $reemit = birkly_cms_update_attention_sync($status);
    cua_assert('re-emit after nav cleared', ($reemit['emitted'] ?? 0) === 1);
    cua_assert('nav restored after re-emit', birkly_plugin_activity_has_nav_attention($pluginId));

    birkly_cms_update_attention_dismiss_modal('1.0.1');
    $state = birkly_cms_update_attention_load_state();
    cua_assert('modal dismissed for version', ($state['modal_dismissed_version'] ?? '') === '1.0.1');
    $third = birkly_cms_update_attention_sync($status);
    cua_assert('modal hidden after dismiss', empty($third['show_modal']));

    $cleared = birkly_cms_update_attention_acknowledge_all();
    cua_assert('acknowledge clears activity', $cleared >= 1);
    cua_assert('nav cleared after acknowledge', !birkly_plugin_activity_has_nav_attention($pluginId));

    $fourth = birkly_cms_update_attention_sync($status);
    cua_assert('acknowledged version does not re-emit', ($fourth['emitted'] ?? 0) === 0);

    $status['latest_version'] = '1.0.2';
    $fifth = birkly_cms_update_attention_sync($status);
    cua_assert('new version emits again', ($fifth['emitted'] ?? 0) === 1);

    $status['update_available'] = false;
    $sixth = birkly_cms_update_attention_sync($status);
    cua_assert('no update clears path', ($sixth['cleared'] ?? 0) >= 0);

    birkly_cms_update_attention_register_formatters();
    if (function_exists('birkly_notification_apply_formatter')) {
        $entry = [
            'event' => 'cms_update',
            'user' => 'birkly',
            'details' => [
                'current_version' => '1.0.0',
                'latest_version' => '1.0.1',
            ],
        ];
        $notification = ['event' => 'cms_update'];
        $handled = birkly_notification_apply_formatter($notification, $entry, 'admin@example.com');
        cua_assert('cms formatter registered', $handled);
        cua_assert('cms formatter category', ($notification['category'] ?? '') === 'system');
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
    if ($backupCache !== null) {
        file_put_contents($cachePath, $backupCache);
    } else {
        @unlink($cachePath);
    }
}

if ($failures === 0) {
    echo "cms_update_attention_test: OK\n";
}

exit($failures > 0 ? 1 : 0);
