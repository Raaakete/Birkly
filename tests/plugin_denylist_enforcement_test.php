<?php
/**
 * P12-M4 — Plugin denylist enforcement tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Plugin denylist enforcement tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/core/plugins/permissions.php';
require_once $root . '/core/plugins/capabilities.php';
require_once $root . '/core/plugins/events.php';

$settingsDir = $root . '/settings';
$denylistFile = $settingsDir . '/plugin_denylist.json';
$permissionsFile = $settingsDir . '/plugin_permissions.json';
$denylistBackup = is_file($denylistFile) ? file_get_contents($denylistFile) : null;
$permissionsBackup = is_file($permissionsFile) ? file_get_contents($permissionsFile) : null;

$protectedCollection = 'denylist_test_' . getmypid();
$pluginId = 'denylist_test_plugin';

set_plugin_denylist([$protectedCollection], ['deny_collections' => [$protectedCollection], 'deny_spaces' => []]);
grant_plugin_capability($pluginId, 'modify_entries', 'global');
grant_plugin_capability($pluginId, 'modify_entries', $protectedCollection);

test_assert('denylisted collection is protected', is_resource_protected($protectedCollection) === true);
test_assert('global capability denied on denylisted collection', has_plugin_capability($pluginId, 'modify_entries', $protectedCollection) === false);
test_assert('global capability still granted without scope', has_plugin_capability($pluginId, 'modify_entries', 'other_collection') === true);
test_assert('require_plugin_capability throws on denylisted scope', (function () use ($pluginId, $protectedCollection) {
    try {
        require_plugin_capability($pluginId, 'modify_entries', $protectedCollection);
        return false;
    } catch (Exception $e) {
        return strpos($e->getMessage(), 'does not have capability') !== false;
    }
})());

$eventReceived = false;
global $birkly_plugins, $birkly_event_subscribers;
$birkly_plugins = [
    $pluginId => ['id' => $pluginId, 'active' => true, 'path' => '/tmp'],
];
$birkly_event_subscribers['denylist_test_event'] = [[
    'callback' => function () use (&$eventReceived) {
        $eventReceived = true;
    },
    'plugin_id' => $pluginId,
    'priority' => 10,
    'capability' => 'modify_entries',
    'scope' => $protectedCollection,
]];

$notified = broadcast_event('denylist_test_event', ['collection' => $protectedCollection], 'other_plugin');
test_assert('event subscriber blocked on denylisted collection', $eventReceived === false && $notified === 0);

$eventReceived = false;
$birkly_event_subscribers['denylist_test_event_open'] = [[
    'callback' => function () use (&$eventReceived) {
        $eventReceived = true;
    },
    'plugin_id' => $pluginId,
    'priority' => 10,
    'capability' => 'modify_entries',
    'scope' => 'open_collection',
]];

$notifiedOpen = broadcast_event('denylist_test_event_open', [], 'other_plugin');
test_assert('event subscriber allowed on non-denylisted collection', $eventReceived === true && $notifiedOpen === 1);

if ($denylistBackup !== null) {
    file_put_contents($denylistFile, $denylistBackup);
} elseif (is_file($denylistFile)) {
    unlink($denylistFile);
}
unset($GLOBALS['_plugin_denylist_cache']);

if ($permissionsBackup !== null) {
    file_put_contents($permissionsFile, $permissionsBackup);
} elseif (is_file($permissionsFile)) {
    unlink($permissionsFile);
}
unset($GLOBALS['_plugin_permissions_cache']);

exit(test_finish('Plugin denylist enforcement tests'));
