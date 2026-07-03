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

// ---- P5.6-M3: sub-entry triggers ----
$triggersSource = file_get_contents($root . '/plugins/automation/blocks/triggers.php');
test_assert('sub_entry_created trigger registered', strpos($triggersSource, "'sub_entry_created'") !== false);
test_assert('sub_entry_updated trigger registered', strpos($triggersSource, "'sub_entry_updated'") !== false);
test_assert('sub_entry_deleted trigger registered', strpos($triggersSource, "'sub_entry_deleted'") !== false);
test_assert('sub_entry triggers use collection_picker', strpos($triggersSource, "'collection_picker'") !== false);
test_assert('sub_entry triggers use sub_collection_picker', strpos($triggersSource, "'sub_collection_picker'") !== false);

// Automation CSS file exists
test_assert('automation.css exists', is_file($root . '/plugins/automation/admin/automation.css'));

// automation.html references CSS
$adminHtml2 = file_get_contents($root . '/plugins/automation/admin/automation.html');
test_assert('automation.html references automation.css', strpos($adminHtml2, 'automation.css') !== false);
test_assert('automation.html has no inline script block', strpos($adminHtml2, '<script>') === false);

// API has get_collections and dry_run actions
$apiSource2 = file_get_contents($root . '/plugins/automation/api/automation.php');
test_assert('API has get_collections action', strpos($apiSource2, "'get_collections'") !== false);
test_assert('API has dry_run action', strpos($apiSource2, "'dry_run'") !== false);

// Executor handles sub-entry trigger collection filter
$executorSource = file_get_contents($root . '/plugins/automation/executor.php');
test_assert('executor filters sub_entry triggers by sub_collection', strpos($executorSource, "sub_entry_created") !== false);

// JS has collection picker builder
$jsSource = file_get_contents($root . '/plugins/automation/admin/automation.js');
test_assert('JS has buildCollectionPickerInput', strpos($jsSource, 'buildCollectionPickerInput') !== false);
test_assert('JS has buildSubCollectionPickerInput', strpos($jsSource, 'buildSubCollectionPickerInput') !== false);
test_assert('JS has dryRunAutomation', strpos($jsSource, 'dryRunAutomation') !== false);
test_assert('JS has duplicateAutomation', strpos($jsSource, 'duplicateAutomation') !== false);
test_assert('JS has moveAction (reorder)', strpos($jsSource, 'function moveAction') !== false);
test_assert('JS has applyTemplate (empty state)', strpos($jsSource, 'applyTemplate') !== false);
test_assert('JS has checkQueueHealth (health hint)', strpos($jsSource, 'checkQueueHealth') !== false);

require_once $root . '/plugins/automation/cache.php';

$emptyKey = automation_list_cache_key([]);
test_assert('automation list cache key uses filter hash', $emptyKey === AUTOMATION_LIST_CACHE_KEY . '_' . md5(json_encode([])));

$cacheSource = file_get_contents($root . '/plugins/automation/cache.php');
test_assert('invalidate clears hashed list cache key', strpos($cacheSource, 'automation_list_cache_key([])') !== false);
test_assert('invalidate tracks list cache index', strpos($cacheSource, 'AUTOMATION_LIST_CACHE_INDEX_KEY') !== false);
test_assert('cache_automation_list uses shared key helper', strpos($cacheSource, 'automation_list_cache_key($filters)') !== false);

exit(test_finish('automation_plugin_test'));
