<?php
/**
 * Plugin activation state + smoke-load tests.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/birkly_data_paths.php';

$tmpSettings = sys_get_temp_dir() . '/birkly-plugin-activation-test-' . getmypid();
@mkdir($tmpSettings, 0775, true);
$GLOBALS['__birkly_plugin_state_settings_dir'] = $tmpSettings;
$GLOBALS['__birkly_data_root'] = null;

$pluginsDir = $tmpSettings . '/../plugins-test-' . getmypid();
@mkdir($pluginsDir . '/sample', 0775, true);
file_put_contents($pluginsDir . '/sample/plugin.php', "<?php\n// sample plugin\n");
file_put_contents($pluginsDir . '/sample/plugin.json', json_encode([
    'name' => 'Sample',
    'version' => '1.0.0',
    'requires' => '1.0.0',
]));
file_put_contents($tmpSettings . '/plugins.json', json_encode([
    'sample' => ['active' => false, 'settings' => []],
], JSON_PRETTY_PRINT));

$GLOBALS['haru_plugins_loaded'] = true;
require_once $root . '/core/plugins.php';
require_once $root . '/core/plugins/health.php';

$GLOBALS['birkly_plugins'] = [];

load_plugins($pluginsDir);

$failures = [];

if (birkly_is_plugin_active('sample')) {
    $failures[] = 'inactive sample plugin must not report active';
}

if (!isset($GLOBALS['birkly_plugins']['sample'])) {
    $failures[] = 'sample plugin not discovered';
}

$smoke = birkly_plugin_smoke_load('sample');
if (!$smoke['ok']) {
    $failures[] = 'smoke load failed: ' . ($smoke['error'] ?? '?');
}

if (!set_plugin_state('sample', true)) {
    $failures[] = 'set_plugin_state(true) failed';
}

$saved = json_decode((string) file_get_contents($tmpSettings . '/plugins.json'), true);
if (empty($saved['sample']['active'])) {
    $failures[] = 'plugins.json active flag not persisted';
}

if (!birkly_is_plugin_active('sample')) {
    $failures[] = 'sample should be active after set_plugin_state';
}

if (!set_plugin_state('missing-plugin', true)) {
    // expected
} else {
    $failures[] = 'set_plugin_state for missing plugin should fail';
}

@unlink($pluginsDir . '/sample/plugin.php');
@unlink($pluginsDir . '/sample/plugin.json');
@rmdir($pluginsDir . '/sample');
@rmdir($pluginsDir);
@unlink($tmpSettings . '/plugins.json');
@rmdir($tmpSettings);

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "plugin_activation_test: OK\n";
