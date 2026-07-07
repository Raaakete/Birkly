<?php
/**
 * P14-M5 — Plugin denylist spaces enforcement tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Plugin denylist spaces tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/core/plugins/permissions.php';
require_once $root . '/core/plugins/capabilities.php';
require_once $root . '/core/plugins/ui_injection.php';

$settingsDir = $root . '/settings';
$denylistFile = $settingsDir . '/plugin_denylist.json';
$denylistBackup = is_file($denylistFile) ? file_get_contents($denylistFile) : null;

$protectedSpace = 'finance_space_' . getmypid();
$pluginId = 'denylist_space_test_plugin';

set_plugin_denylist(['space:' . $protectedSpace], [
    'deny_collections' => [],
    'deny_spaces' => [$protectedSpace],
]);
unset($GLOBALS['_plugin_denylist_cache']);

test_assert('denylisted space is protected', is_resource_protected($protectedSpace) === true);
test_assert('space: prefix protected', is_resource_protected('space:' . $protectedSpace) === true);
test_assert('other space not protected', is_resource_protected('open_space') === false);

grant_plugin_capability($pluginId, 'modify_entries', 'global');
grant_plugin_capability($pluginId, 'modify_entries', $protectedSpace);

test_assert('capability denied on denylisted space', has_plugin_capability($pluginId, 'modify_entries', $protectedSpace) === false);
test_assert('capability allowed on open space', has_plugin_capability($pluginId, 'modify_entries', 'open_space') === true);

global $birkly_plugins, $haru_ui_injections;
$birkly_plugins = [
    $pluginId => ['id' => $pluginId, 'active' => true, 'path' => '/tmp'],
];
$haru_ui_injections = [];

$blockedOutput = '';
$allowedOutput = '';
register_ui_injection('collection_editor_sidebar_before', function () use (&$blockedOutput) {
    $blockedOutput = '<div id="blocked-panel">blocked</div>';
    return $blockedOutput;
}, ['capability' => 'modify_entries', 'scope' => $protectedSpace, 'plugin_id' => $pluginId]);

register_ui_injection('collection_editor_sidebar_before', function () use (&$allowedOutput) {
    $allowedOutput = '<div id="allowed-panel">allowed</div>';
    return $allowedOutput;
}, ['capability' => 'modify_entries', 'scope' => 'open_space', 'plugin_id' => $pluginId]);

// Re-register with correct plugin_id via backtrace simulation — override injection registry
$haru_ui_injections['collection_editor_sidebar_before'] = [
    [
        'component' => function () use (&$blockedOutput) {
            $blockedOutput = '<div id="blocked-panel">blocked</div>';
            return $blockedOutput;
        },
        'plugin_id' => $pluginId,
        'priority' => 10,
        'capability' => 'modify_entries',
        'scope' => $protectedSpace,
        'position' => 'append',
        'target' => null,
    ],
    [
        'component' => function () use (&$allowedOutput) {
            $allowedOutput = '<div id="allowed-panel">allowed</div>';
            return $allowedOutput;
        },
        'plugin_id' => $pluginId,
        'priority' => 20,
        'capability' => 'modify_entries',
        'scope' => 'open_space',
        'position' => 'append',
        'target' => null,
    ],
];

$rendered = render_ui_injections('collection_editor_sidebar_before', ['space' => $protectedSpace]);
test_assert('denylisted space blocks sidebar injection', strpos($rendered, 'blocked-panel') === false);
test_assert('open space allows sidebar injection', strpos($rendered, 'allowed-panel') !== false);

if ($denylistBackup !== null) {
    file_put_contents($denylistFile, $denylistBackup);
} elseif (is_file($denylistFile)) {
    unlink($denylistFile);
}
unset($GLOBALS['_plugin_denylist_cache']);

exit(test_finish('plugin_denylist_spaces_test'));
