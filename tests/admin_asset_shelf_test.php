<?php
/**
 * P83-M1 — Admin asset shelf aggregation tests.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/birkly_data_paths.php';

$tmpSettings = sys_get_temp_dir() . '/birkly-admin-shelf-' . getmypid();
@mkdir($tmpSettings, 0775, true);
$GLOBALS['__birkly_plugin_state_settings_dir'] = $tmpSettings;
$GLOBALS['__birkly_data_root'] = null;

require_once $root . '/core/plugins.php';
require_once $root . '/core/plugins/admin_asset_shelf.php';

$failures = [];

function assert_true(string $label, bool $condition): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $label;
        fwrite(STDERR, "FAIL: $label\n");
        return;
    }
    echo "OK: $label\n";
}

function setup_plugin_tree(string $pluginsDir, array $states): void
{
    global $tmpSettings;

    @mkdir($pluginsDir, 0775, true);
    file_put_contents($tmpSettings . '/plugins.json', json_encode($states, JSON_THROW_ON_ERROR));
    $GLOBALS['birkly_plugins'] = [];
    $GLOBALS['haru_plugins_loaded'] = true;
    load_plugins($pluginsDir);
}

$pluginsDir = sys_get_temp_dir() . '/birkly-admin-plugins-' . getmypid();
@mkdir($pluginsDir . '/active_plugin/admin', 0775, true);
@mkdir($pluginsDir . '/inactive_plugin/admin', 0775, true);

file_put_contents($pluginsDir . '/active_plugin/plugin.php', "<?php\n");
file_put_contents($pluginsDir . '/active_plugin/plugin.json', json_encode([
    'name' => 'Active',
    'version' => '3.2.1',
    'admin' => [
        'scripts' => [[
            'path' => 'admin/dashboard-widget.js',
            'provides' => 'BirklyActiveDashboard',
            'load' => 'dashboard',
        ], [
            'path' => 'admin/shared.js',
            'provides' => 'BirklyActiveShared',
            'load' => 'always',
        ]],
    ],
], JSON_THROW_ON_ERROR));
file_put_contents($pluginsDir . '/active_plugin/admin/dashboard-widget.js', "// dashboard\n");
file_put_contents($pluginsDir . '/active_plugin/admin/shared.js', "// shared\n");

file_put_contents($pluginsDir . '/inactive_plugin/plugin.php', "<?php\n");
file_put_contents($pluginsDir . '/inactive_plugin/plugin.json', json_encode([
    'name' => 'Inactive',
    'version' => '1.0.0',
    'admin' => [
        'scripts' => [[
            'path' => 'admin/inactive.js',
            'provides' => 'BirklyInactive',
        ]],
    ],
], JSON_THROW_ON_ERROR));
file_put_contents($pluginsDir . '/inactive_plugin/admin/inactive.js', "// inactive\n");

setup_plugin_tree($pluginsDir, [
    'active_plugin' => ['active' => true, 'settings' => []],
    'inactive_plugin' => ['active' => false, 'settings' => []],
]);

$allScripts = birkly_get_admin_scripts(null);
assert_true('returns array of scripts', is_array($allScripts) && $allScripts !== []);
assert_true('always script included on any page', count(array_filter(
    $allScripts,
    static fn(array $s): bool => ($s['provides'] ?? '') === 'BirklyActiveShared'
)) === 1);
assert_true('dashboard-only script excluded without page filter', count(array_filter(
    $allScripts,
    static fn(array $s): bool => ($s['provides'] ?? '') === 'BirklyActiveDashboard'
)) === 0);

$dashboardScripts = birkly_get_admin_scripts('dashboard');
assert_true('dashboard page includes dashboard script', count(array_filter(
    $dashboardScripts,
    static fn(array $s): bool => ($s['provides'] ?? '') === 'BirklyActiveDashboard'
)) === 1);
assert_true('inactive plugin excluded', count(array_filter(
    $dashboardScripts,
    static fn(array $s): bool => ($s['plugin_id'] ?? '') === 'inactive_plugin'
)) === 0);
assert_true('version included', count(array_filter(
    $dashboardScripts,
    static fn(array $s): bool => ($s['plugin_id'] ?? '') === 'active_plugin'
        && ($s['version'] ?? '') === '3.2.1'
)) >= 1);
assert_true('url uses /plugins/{id}/ path', count(array_filter(
    $dashboardScripts,
    static fn(array $s): bool => ($s['url'] ?? '') === '/plugins/active_plugin/admin/dashboard-widget.js'
)) === 1);

// Dedupe by provides
@mkdir($pluginsDir . '/dup_plugin/admin', 0775, true);
file_put_contents($pluginsDir . '/dup_plugin/plugin.php', "<?php\n");
file_put_contents($pluginsDir . '/dup_plugin/plugin.json', json_encode([
    'name' => 'Dup',
    'version' => '1.0.0',
    'admin' => ['scripts' => [[
        'path' => 'admin/shared.js',
        'provides' => 'BirklyActiveShared',
    ]]],
], JSON_THROW_ON_ERROR));
file_put_contents($pluginsDir . '/dup_plugin/admin/shared.js', "// dup\n");

setup_plugin_tree($pluginsDir, [
    'active_plugin' => ['active' => true, 'settings' => []],
    'inactive_plugin' => ['active' => false, 'settings' => []],
    'dup_plugin' => ['active' => true, 'settings' => []],
]);

$deduped = birkly_get_admin_scripts(null);
$sharedEntries = array_values(array_filter(
    $deduped,
    static fn(array $s): bool => ($s['provides'] ?? '') === 'BirklyActiveShared'
));
assert_true('provides dedupes to first plugin', count($sharedEntries) === 1);
assert_true('first plugin wins dedupe', ($sharedEntries[0]['plugin_id'] ?? '') === 'active_plugin');

$tags = birkly_render_admin_script_tags(null);
assert_true('render emits script tags', str_contains($tags, '<script src='));
assert_true('render emits admin-plugins-ready event', str_contains($tags, 'birkly:admin-plugins-ready'));

@unlink($pluginsDir . '/active_plugin/admin/dashboard-widget.js');
@unlink($pluginsDir . '/active_plugin/admin/shared.js');
@unlink($pluginsDir . '/active_plugin/plugin.php');
@unlink($pluginsDir . '/active_plugin/plugin.json');
@rmdir($pluginsDir . '/active_plugin/admin');
@rmdir($pluginsDir . '/active_plugin');
@unlink($pluginsDir . '/inactive_plugin/admin/inactive.js');
@unlink($pluginsDir . '/inactive_plugin/plugin.php');
@unlink($pluginsDir . '/inactive_plugin/plugin.json');
@rmdir($pluginsDir . '/inactive_plugin/admin');
@rmdir($pluginsDir . '/inactive_plugin');
@unlink($pluginsDir . '/dup_plugin/admin/shared.js');
@unlink($pluginsDir . '/dup_plugin/plugin.php');
@unlink($pluginsDir . '/dup_plugin/plugin.json');
@rmdir($pluginsDir . '/dup_plugin/admin');
@rmdir($pluginsDir . '/dup_plugin');
@rmdir($pluginsDir);
@unlink($tmpSettings . '/plugins.json');
@rmdir($tmpSettings);

if ($failures !== []) {
    exit(1);
}

echo "admin_asset_shelf_test: OK\n";
