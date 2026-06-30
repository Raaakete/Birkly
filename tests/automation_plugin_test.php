<?php
/**
 * P3-M6: Automation plugin extraction tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Automation plugin tests\n\n";

$root = dirname(__DIR__);
$pluginJsonPath = $root . '/plugins/automation/plugin.json';
$pluginPhpPath = $root . '/plugins/automation/plugin.php';
$bootstrapPath = $root . '/core/automation/bootstrap.php';
$registryPath = $root . '/plugins/automation/registry.php';

test_assert('plugin.json exists', is_file($pluginJsonPath));
test_assert('plugin.php exists', is_file($pluginPhpPath));
test_assert('bootstrap stub exists', is_file($bootstrapPath));

$metadata = json_decode(file_get_contents($pluginJsonPath), true);
test_assert('plugin.json parses', is_array($metadata));
test_assert('navigation page is automation', ($metadata['navigation']['page'] ?? '') === 'automation');

$registrySource = file_get_contents($registryPath);
test_assert('registry uses birkly_automation_blocks', strpos($registrySource, '$birkly_automation_blocks') !== false);
test_assert('registry uses birkly_plugins', strpos($registrySource, '$birkly_plugins') !== false);
test_assert('registry does not use haru_automation_blocks', strpos($registrySource, '$haru_automation_blocks') === false);
test_assert('registry does not use haru_plugins', strpos($registrySource, '$haru_plugins') === false);

$pluginsJsonPath = $root . '/settings/plugins.json';
$pluginsJsonBackup = sys_get_temp_dir() . '/birkly-automation-plugins-' . getmypid() . '.json';
$hadPluginsJson = is_file($pluginsJsonPath);
if ($hadPluginsJson) {
    copy($pluginsJsonPath, $pluginsJsonBackup);
}
if (!is_dir(dirname($pluginsJsonPath))) {
    mkdir(dirname($pluginsJsonPath), 0755, true);
}
file_put_contents($pluginsJsonPath, json_encode([
    'automation' => ['active' => false, 'settings' => []],
], JSON_PRETTY_PRINT));
unset($GLOBALS['haru_plugins_loaded']);

require_once $bootstrapPath;
require_once $root . '/core/plugins.php';

global $birkly_plugins;
$birkly_plugins['automation'] = array_merge($metadata, [
    'id' => 'automation',
    'path' => $root . '/plugins/automation',
    'active' => false,
    'settings' => [],
]);

test_assert('automation_plugin_active false when inactive', automation_plugin_active() === false);
test_assert('automation_bootstrap false when inactive', automation_bootstrap() === false);
test_assert('enqueue_automation undefined when inactive', !function_exists('enqueue_automation'));

$apiSource = file_get_contents($root . '/plugins/automation/api/automation.php');
test_assert('plugin API checks automation_bootstrap', strpos($apiSource, 'automation_bootstrap') !== false);

require_once $root . '/plugins/automation/automation.php';
require_once $root . '/plugins/automation/init.php';
test_assert('get_automations available when loaded', function_exists('get_automations'));
test_assert('register_automation_block available when loaded', function_exists('register_automation_block'));

global $birkly_automation_blocks;
test_assert('birkly_automation_blocks registry initialized', is_array($birkly_automation_blocks));
test_assert('triggers bucket exists', isset($birkly_automation_blocks['triggers']));

$adminHtml = file_get_contents($root . '/plugins/automation/admin/automation.html');
test_assert('automation.js exists', is_file($root . '/plugins/automation/admin/automation.js'));
test_assert('automation.html references external JS', strpos($adminHtml, 'automation.js') !== false);
test_assert('automation.html has no inline script block', strpos($adminHtml, '<script>') === false);

test_assert('core automation dir only has bootstrap', is_file($root . '/core/automation/bootstrap.php') && !is_file($root . '/core/automation/init.php'));

// Single load_plugins() must honor settings/plugins.json (API path only loads once)
file_put_contents($pluginsJsonPath, json_encode([
    'automation' => ['active' => true, 'settings' => []],
], JSON_PRETTY_PRINT));
unset($GLOBALS['haru_plugins_loaded'], $birkly_plugins);
$birkly_plugins = [];
load_plugins();
test_assert('single load_plugins honors persisted automation active', ($birkly_plugins['automation']['active'] ?? false) === true);
test_assert('automation_plugin_active true after single load', automation_plugin_active() === true);
test_assert('automation_bootstrap true after single load', automation_bootstrap() === true);
if ($hadPluginsJson) {
    copy($pluginsJsonBackup, $pluginsJsonPath);
} else {
    @unlink($pluginsJsonPath);
}
@unlink($pluginsJsonBackup);

exit(test_finish('automation_plugin_test'));
