<?php
/**
 * P10-M1 — plugin_availability helper tests.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/plugins.php';

test_reset_failures();
echo "Plugin availability tests\n\n";

$root = dirname(__DIR__);

test_assert(
    'birkly_plugin_dir returns plugins path',
    realpath(birkly_plugin_dir('automation')) === realpath($root . '/plugins/automation')
);
test_assert('birkly_plugin_file_exists true for automation plugin.php', birkly_plugin_file_exists('automation', 'plugin.php'));
test_assert('birkly_plugin_file_exists false for missing plugin', birkly_plugin_file_exists('not-a-real-plugin', 'plugin.php') === false);
test_assert('birkly_plugin_file_exists false for missing relative file', birkly_plugin_file_exists('automation', 'missing.php') === false);

$loaded = birkly_require_plugin_file('automation', 'plugin.php');
test_assert('birkly_require_plugin_file loads existing file', $loaded === true);

$missingRequire = birkly_require_plugin_file('automation', 'does-not-exist.php');
test_assert('birkly_require_plugin_file false when missing', $missingRequire === false);

$pluginsJsonPath = $root . '/settings/plugins.json';
$pluginsJsonBackup = sys_get_temp_dir() . '/birkly-plugin-avail-' . getmypid() . '.json';
$hadPluginsJson = is_file($pluginsJsonPath);
if ($hadPluginsJson) {
    copy($pluginsJsonPath, $pluginsJsonBackup);
}

file_put_contents($pluginsJsonPath, json_encode([
    'automation' => ['active' => true, 'settings' => []],
], JSON_PRETTY_PRINT));

global $birkly_plugins;
$birkly_plugins['automation'] = array_merge($birkly_plugins['automation'] ?? [], [
    'id' => 'automation',
    'active' => true,
]);

test_assert('installed and active when files and settings present', birkly_plugin_is_installed_and_active('automation') === true);

file_put_contents($pluginsJsonPath, json_encode([
    'automation' => ['active' => false, 'settings' => []],
], JSON_PRETTY_PRINT));
$birkly_plugins['automation']['active'] = false;
test_assert('inactive plugin is not installed_and_active', birkly_plugin_is_installed_and_active('automation') === false);

test_assert('missing package is not installed_and_active', birkly_plugin_is_installed_and_active('not-a-real-plugin') === false);

if ($hadPluginsJson) {
    copy($pluginsJsonBackup, $pluginsJsonPath);
} else {
    @unlink($pluginsJsonPath);
}
@unlink($pluginsJsonBackup);

// Gate file returns JSON 404 when plugin package absent
$gateSource = file_get_contents($root . '/api/automation.php');
test_assert('api/automation.php uses plugin availability helpers', strpos($gateSource, 'birkly_plugin_is_installed_and_active') !== false);
test_assert('api/automation.php does not hard-require plugin path', strpos($gateSource, "require_once __DIR__ . '/../plugins/automation/api/automation.php'") === false);

exit(test_finish('plugin_availability_test'));
