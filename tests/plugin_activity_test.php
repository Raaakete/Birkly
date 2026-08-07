<?php
/**
 * Plugin activity bus tests (P61).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/plugin_activity.php';

$failures = 0;

function pa_assert(string $name, bool $ok, string $detail = ''): void
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

$dir = birkly_plugin_activity_dir();
$backupFiles = [];
if (is_dir($dir)) {
    foreach (glob($dir . '/*.jsonl') ?: [] as $f) {
        $backupFiles[$f] = file_get_contents($f);
    }
}

// Isolate
if (is_dir($dir)) {
    foreach (glob($dir . '/*.jsonl') ?: [] as $f) {
        @unlink($f);
    }
}

try {
    $emitted = birkly_plugin_activity_emit([
        'plugin' => 'commerce',
        'event' => 'order_created',
        'title' => 'New order #1',
        'body' => 'EUR 12.00',
        'link' => '/admin/?page=commerce',
        'meta' => ['order_id' => 'ord_1', 'api_key' => 'should-strip'],
    ]);
    pa_assert('emit returns activity', is_array($emitted) && ($emitted['id'] ?? '') !== '');
    pa_assert('meta secret stripped', is_array($emitted) && !isset($emitted['meta']['api_key']));
    pa_assert('notify default on', is_array($emitted) && !empty($emitted['notify']));
    pa_assert('nav_attention default on', is_array($emitted) && !empty($emitted['nav_attention']));

    $list = birkly_plugin_activity_list(10);
    pa_assert('list contains emit', count($list) >= 1 && ($list[0]['title'] ?? '') === 'New order #1');

    $counts = birkly_plugin_activity_nav_attention_counts();
    pa_assert('nav attention count', ($counts['commerce'] ?? 0) >= 1);

    $cleared = birkly_plugin_activity_clear_nav('commerce');
    pa_assert('clear nav', $cleared >= 1);
    pa_assert('nav cleared', birkly_plugin_activity_has_nav_attention('commerce') === false);

    $bad = birkly_plugin_activity_emit(['plugin' => '', 'event' => 'x', 'title' => 'y']);
    pa_assert('reject empty plugin', $bad === null);

    $merged = birkly_plugin_merge_activity_settings_schema([]);
    pa_assert('merge activity schema keys', isset($merged['activity_notify'], $merged['activity_nav_dot']));
    pa_assert('merge keeps empty-plugin has_settings', !empty($merged));
    $custom = birkly_plugin_merge_activity_settings_schema([
        'activity_notify' => [
            'type' => 'boolean',
            'default' => true,
            'label' => 'Custom notify label',
            'description' => 'Custom',
        ],
        'other' => ['type' => 'string', 'default' => 'x', 'label' => 'Other'],
    ]);
    pa_assert('merge preserves custom label', ($custom['activity_notify']['label'] ?? '') === 'Custom notify label');
    pa_assert('merge keeps other keys', isset($custom['other'], $custom['activity_nav_dot']));
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
