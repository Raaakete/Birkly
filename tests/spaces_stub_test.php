<?php
/**
 * P18-M6 — Spaces v1 config stub and denylist integration tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Spaces stub tests\n\n";

$root = dirname(__DIR__);
$settingsDir = sys_get_temp_dir() . '/birkly-spaces-' . getmypid();
mkdir($settingsDir, 0755, true);
$GLOBALS['__birkly_spaces_settings_dir'] = $settingsDir;
$GLOBALS['__birkly_plugin_settings_dir'] = $settingsDir;

$spacesFile = $settingsDir . '/spaces.json';
file_put_contents($spacesFile, json_encode([
    'default_space_id' => 'default',
    'spaces' => [
        ['id' => 'default', 'label' => 'Default', 'default_collections' => []],
        ['id' => 'finance', 'label' => 'Finance', 'default_collections' => ['invoices', 'ledger']],
    ],
], JSON_PRETTY_PRINT));

$denylistFile = $settingsDir . '/plugin_denylist.json';
file_put_contents($denylistFile, json_encode([
    'resources' => ['space:finance'],
    'deny_collections' => [],
    'deny_spaces' => ['finance'],
], JSON_PRETTY_PRINT));

require_once $root . '/core/spaces.php';
require_once $root . '/core/plugins/permissions.php';
require_once $root . '/core/plugins/capabilities.php';

unset($GLOBALS['_plugin_denylist_cache'], $GLOBALS['__birkly_spaces_config']);

$config = birkly_spaces_config();
test_assert('spaces config loads', count($config['spaces'] ?? []) === 2);
test_assert('finance space resolves for invoices', birkly_space_id_for_collection('invoices') === 'finance');
test_assert('unknown collection uses default', birkly_space_id_for_collection('blog') === 'default');
test_assert('finance space denylisted', birkly_space_is_denylisted('finance') === true);
test_assert('default space not denylisted', birkly_space_is_denylisted('default') === false);
test_assert('collection in denylisted space', birkly_collection_in_denylisted_space('invoices') === true);
test_assert('open collection not denylisted', birkly_collection_in_denylisted_space('blog') === false);

$pluginId = 'spaces_test_plugin';
grant_plugin_capability($pluginId, 'modify_entries', 'invoices');
grant_plugin_capability($pluginId, 'modify_entries', 'blog');
test_assert('capability denied for denylisted space collection', has_plugin_capability($pluginId, 'modify_entries', 'invoices') === false);
test_assert('capability allowed for open collection', has_plugin_capability($pluginId, 'modify_entries', 'blog') === true);

foreach (array_diff(scandir($settingsDir) ?: [], ['.', '..']) as $item) {
    unlink($settingsDir . '/' . $item);
}
rmdir($settingsDir);
unset($GLOBALS['__birkly_spaces_settings_dir'], $GLOBALS['__birkly_plugin_settings_dir'], $GLOBALS['_plugin_denylist_cache'], $GLOBALS['_plugin_permissions_cache']);

exit(test_finish('spaces_stub_test'));
