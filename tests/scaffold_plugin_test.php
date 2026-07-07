<?php
/**
 * P15-M5 — Plugin scaffolding CLI tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Scaffold plugin CLI tests\n\n";

$root = dirname(__DIR__);
$script = $root . '/scripts/scaffold-plugin.php';

test_assert('scaffold-plugin.php exists', is_file($script));

$tmpBase = sys_get_temp_dir() . '/birkly-scaffold-test-' . getmypid();
mkdir($tmpBase, 0755, true);
$pluginId = 'scaffold-test-' . getmypid();
$title = 'Scaffold Test Plugin';

$cmd = 'php ' . escapeshellarg($script)
    . ' --id=' . escapeshellarg($pluginId)
    . ' --title=' . escapeshellarg($title)
    . ' --out=' . escapeshellarg($tmpBase);
exec($cmd . ' 2>&1', $outLines, $code);
$output = implode("\n", $outLines);

test_assert('scaffold CLI exits 0', $code === 0, $output);

$pluginDir = $tmpBase . '/' . $pluginId;
test_assert('plugin directory created', is_dir($pluginDir));
test_assert('plugin.json exists', is_file($pluginDir . '/plugin.json'));
test_assert('plugin.php exists', is_file($pluginDir . '/plugin.php'));
test_assert('admin html exists', is_file($pluginDir . '/admin/' . str_replace('-', '_', $pluginId) . '.html'));
test_assert('docs/help-overview.md exists', is_file($pluginDir . '/docs/help-overview.md'));
test_assert('README.md exists', is_file($pluginDir . '/README.md'));

$metadata = json_decode((string) file_get_contents($pluginDir . '/plugin.json'), true);
test_assert('plugin.json parses', is_array($metadata));
test_assert('navigation page set', !empty($metadata['navigation']['page']));

$pluginPhp = (string) file_get_contents($pluginDir . '/plugin.php');
test_assert('plugin.php checks get_plugin_state', strpos($pluginPhp, "get_plugin_state('{$pluginId}')") !== false);
test_assert('plugin.php registers admin page', strpos($pluginPhp, 'register_admin_page') !== false);
test_assert('plugin.php registers docs', strpos($pluginPhp, 'register_plugin_docs') !== false);

// Privileged checklist output
$cmdPriv = 'php ' . escapeshellarg($script)
    . ' --id=' . escapeshellarg($pluginId . '-priv')
    . ' --title=' . escapeshellarg('Privileged Test')
    . ' --privileged'
    . ' --out=' . escapeshellarg($tmpBase);
exec($cmdPriv . ' 2>&1', $privOut, $privCode);
$privText = implode("\n", $privOut);
test_assert('privileged scaffold exits 0', $privCode === 0, $privText);
test_assert('privileged prints checklist', stripos($privText, 'Privileged plugin checklist') !== false);
test_assert('privileged mentions denylist', stripos($privText, 'denylist') !== false);

$privMeta = json_decode((string) file_get_contents($tmpBase . '/' . $pluginId . '-priv/plugin.json'), true);
test_assert('privileged adds capabilities', !empty($privMeta['capabilities']));

// Activation discoverability
$pluginsJsonPath = $root . '/settings/plugins.json';
$pluginsBackup = sys_get_temp_dir() . '/birkly-scaffold-plugins-' . getmypid() . '.json';
$hadPlugins = is_file($pluginsJsonPath);
$priorPlugins = $hadPlugins ? (json_decode((string) file_get_contents($pluginsJsonPath), true) ?: []) : [];
if ($hadPlugins) {
    copy($pluginsJsonPath, $pluginsBackup);
}
if (!is_dir(dirname($pluginsJsonPath))) {
    mkdir(dirname($pluginsJsonPath), 0755, true);
}
$priorPlugins[$pluginId] = ['active' => true, 'settings' => []];
file_put_contents($pluginsJsonPath, json_encode($priorPlugins, JSON_PRETTY_PRINT));

$GLOBALS['haru_plugins_loaded'] = true;
global $birkly_plugins, $birkly_hooks, $birkly_admin_pages;
$birkly_plugins = [];
$birkly_hooks = [];
$birkly_admin_pages = [];

require_once $root . '/core/plugins.php';
load_plugins($tmpBase);

test_assert('scaffolded plugin in registry', isset($birkly_plugins[$pluginId]));
if (isset($birkly_plugins[$pluginId])) {
    $birkly_plugins[$pluginId]['active'] = true;
    include_once $pluginDir . '/plugin.php';
}
test_assert('scaffolded plugin active', ($birkly_plugins[$pluginId]['active'] ?? false) === true);

$pageSlug = str_replace('-', '_', $pluginId);
test_assert('admin page registered', isset($birkly_admin_pages[$pageSlug]));

if ($hadPlugins) {
    copy($pluginsBackup, $pluginsJsonPath);
    unlink($pluginsBackup);
}

// Cleanup
foreach (glob($tmpBase . '/*') ?: [] as $path) {
    if (!is_dir($path)) {
        continue;
    }
    foreach (glob($path . '/*') ?: [] as $sub) {
        is_dir($sub) ? array_map('unlink', glob($sub . '/*') ?: []) : unlink($sub);
        @rmdir($sub);
    }
    array_map('unlink', glob($path . '/*') ?: []);
    @rmdir($path);
}
@rmdir($tmpBase);

exit(test_finish('Scaffold plugin CLI tests'));
