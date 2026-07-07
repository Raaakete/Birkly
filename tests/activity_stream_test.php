<?php
/**
 * P18-M5 — Sub-entry activity stream (activity_stream.json).
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Activity stream tests\n\n";

$root = dirname(__DIR__);
$settingsDir = sys_get_temp_dir() . '/birkly-activity-' . getmypid();
mkdir($settingsDir, 0755, true);
$GLOBALS['__birkly_activity_settings_dir'] = $settingsDir;

require_once $root . '/core/sub_entries/activity.php';

log_sub_entry_activity('created', 'orders', 'ord_1', 'line_items', 'li_1', ['title' => 'Test']);
log_sub_entry_activity('updated', 'orders', 'ord_1', 'line_items', 'li_1', ['field' => 'qty']);

$streamPath = $settingsDir . '/activity_stream.json';
test_assert('activity_stream.json created', is_file($streamPath));

$decoded = json_decode((string) file_get_contents($streamPath), true);
test_assert('stream has two events', is_array($decoded) && count($decoded) === 2);
test_assert('latest action updated', ($decoded[1]['action'] ?? '') === 'updated');

$filtered = get_sub_entry_activities(['parent_entry_id' => 'ord_1'], ['limit' => 10]);
test_assert('get_sub_entry_activities reads stream', count($filtered) === 2);

$summary = get_sub_entry_activity_summary(['parent_entry_id' => 'ord_1']);
test_assert('summary counts actions', ($summary['by_action']['created'] ?? 0) === 1);

foreach (array_diff(scandir($settingsDir) ?: [], ['.', '..']) as $item) {
    unlink($settingsDir . '/' . $item);
}
rmdir($settingsDir);
unset($GLOBALS['__birkly_activity_settings_dir']);

exit(test_finish('activity_stream_test'));
