<?php
/**
 * P82-M1 — Storefront script shelf aggregation tests.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/birkly_data_paths.php';

$tmpSettings = sys_get_temp_dir() . '/birkly-storefront-shelf-' . getmypid();
@mkdir($tmpSettings, 0775, true);
$GLOBALS['__birkly_plugin_state_settings_dir'] = $tmpSettings;
$GLOBALS['__birkly_data_root'] = null;

require_once $root . '/core/plugins.php';
require_once $root . '/core/plugins/storefront_shelf.php';

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

$tmpSettings = sys_get_temp_dir() . '/birkly-storefront-shelf-' . getmypid();
@mkdir($tmpSettings, 0775, true);
$GLOBALS['__birkly_plugin_state_settings_dir'] = $tmpSettings;
$GLOBALS['__birkly_data_root'] = null;

$pluginsDir = sys_get_temp_dir() . '/birkly-storefront-plugins-' . getmypid();
@mkdir($pluginsDir . '/active_plugin/assets', 0775, true);
@mkdir($pluginsDir . '/inactive_plugin/assets', 0775, true);

file_put_contents($pluginsDir . '/active_plugin/plugin.php', "<?php\n");
file_put_contents($pluginsDir . '/active_plugin/plugin.json', json_encode([
    'name' => 'Active',
    'version' => '2.3.4',
    'storefront' => [
        'scripts' => [[
            'path' => 'assets/active-storefront.js',
            'provides' => 'BirklyActive',
            'load' => 'always',
        ]],
    ],
], JSON_THROW_ON_ERROR));
file_put_contents($pluginsDir . '/active_plugin/assets/active-storefront.js', "// active\n");

file_put_contents($pluginsDir . '/inactive_plugin/plugin.php', "<?php\n");
file_put_contents($pluginsDir . '/inactive_plugin/plugin.json', json_encode([
    'name' => 'Inactive',
    'version' => '1.0.0',
    'storefront' => [
        'scripts' => [[
            'path' => 'assets/inactive-storefront.js',
            'provides' => 'BirklyInactive',
        ]],
    ],
], JSON_THROW_ON_ERROR));
file_put_contents($pluginsDir . '/inactive_plugin/assets/inactive-storefront.js', "// inactive\n");

setup_plugin_tree($pluginsDir, [
    'active_plugin' => ['active' => true, 'settings' => []],
    'inactive_plugin' => ['active' => false, 'settings' => []],
]);

$scripts = birkly_get_storefront_scripts();

assert_true('returns array of scripts', is_array($scripts) && $scripts !== []);
assert_true('active manifest script included', count(array_filter(
    $scripts,
    static fn(array $s): bool => ($s['plugin_id'] ?? '') === 'active_plugin'
        && ($s['provides'] ?? '') === 'BirklyActive'
)) === 1);
assert_true('inactive plugin excluded', count(array_filter(
    $scripts,
    static fn(array $s): bool => ($s['plugin_id'] ?? '') === 'inactive_plugin'
)) === 0);
assert_true('version included in manifest entry', count(array_filter(
    $scripts,
    static fn(array $s): bool => ($s['plugin_id'] ?? '') === 'active_plugin'
        && ($s['version'] ?? '') === '2.3.4'
)) === 1);
assert_true('url uses /plugins/{id}/ path', count(array_filter(
    $scripts,
    static fn(array $s): bool => ($s['url'] ?? '') === '/plugins/active_plugin/assets/active-storefront.js'
)) === 1);

// Commerce one-release back-compat (plugin id must be commerce)
$commerceDir = sys_get_temp_dir() . '/birkly-storefront-commerce-' . getmypid();
@mkdir($commerceDir . '/commerce/assets', 0775, true);
file_put_contents($commerceDir . '/commerce/plugin.php', "<?php\n");
file_put_contents($commerceDir . '/commerce/plugin.json', json_encode([
    'name' => 'Commerce',
    'version' => '1.0.111',
], JSON_THROW_ON_ERROR));
file_put_contents($commerceDir . '/commerce/assets/commerce-storefront.js', "// commerce\n");

setup_plugin_tree($commerceDir, [
    'commerce' => ['active' => true, 'settings' => []],
]);

$legacyScripts = birkly_get_storefront_scripts();
assert_true('commerce legacy back-compat when manifest missing', count(array_filter(
    $legacyScripts,
    static fn(array $s): bool => ($s['plugin_id'] ?? '') === 'commerce'
        && ($s['provides'] ?? '') === 'BirklyCommerce'
        && ($s['url'] ?? '') === '/plugins/commerce/assets/commerce-storefront.js'
)) === 1);

file_put_contents($commerceDir . '/commerce/plugin.json', json_encode([
    'name' => 'Commerce',
    'version' => '9.9.9',
    'storefront' => [
        'scripts' => [[
            'path' => 'assets/commerce-storefront.js',
            'provides' => 'BirklyCommerce',
        ]],
    ],
], JSON_THROW_ON_ERROR));
setup_plugin_tree($commerceDir, [
    'commerce' => ['active' => true, 'settings' => []],
]);
$manifestScripts = birkly_get_storefront_scripts();
$commerceEntries = array_values(array_filter(
    $manifestScripts,
    static fn(array $s): bool => ($s['provides'] ?? '') === 'BirklyCommerce'
));
assert_true('manifest dedupes legacy commerce entry', count($commerceEntries) === 1);
assert_true('manifest version used when present', ($commerceEntries[0]['version'] ?? '') === '9.9.9');

@unlink($pluginsDir . '/active_plugin/assets/active-storefront.js');
@unlink($pluginsDir . '/active_plugin/plugin.php');
@unlink($pluginsDir . '/active_plugin/plugin.json');
@unlink($pluginsDir . '/inactive_plugin/assets/inactive-storefront.js');
@unlink($pluginsDir . '/inactive_plugin/plugin.php');
@unlink($pluginsDir . '/inactive_plugin/plugin.json');
@rmdir($pluginsDir . '/active_plugin/assets');
@rmdir($pluginsDir . '/active_plugin');
@rmdir($pluginsDir . '/inactive_plugin/assets');
@rmdir($pluginsDir . '/inactive_plugin');
@rmdir($pluginsDir);

@unlink($commerceDir . '/commerce/assets/commerce-storefront.js');
@unlink($commerceDir . '/commerce/plugin.php');
@unlink($commerceDir . '/commerce/plugin.json');
@rmdir($commerceDir . '/commerce/assets');
@rmdir($commerceDir . '/commerce');
@rmdir($commerceDir);
@unlink($tmpSettings . '/plugins.json');
@rmdir($tmpSettings);

if ($failures !== []) {
    exit(1);
}

echo "storefront_shelf_test: OK\n";
