<?php
/**
 * P85-M1 — Admin i18n shelf aggregation tests.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/birkly_data_paths.php';

$tmpSettings = sys_get_temp_dir() . '/birkly-admin-i18n-' . getmypid();
@mkdir($tmpSettings, 0775, true);
$GLOBALS['__birkly_plugin_state_settings_dir'] = $tmpSettings;
$GLOBALS['__birkly_data_root'] = null;

require_once $root . '/core/i18n.php';
require_once $root . '/core/plugins.php';

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
    $GLOBALS['birkly_plugin_i18n_registry'] = [];
    $GLOBALS['haru_plugins_loaded'] = false;
    I18n::resetPluginCatalogMergeState();
    I18n::setLanguage('en');
    load_plugins($pluginsDir);
}

$pluginsDir = sys_get_temp_dir() . '/birkly-admin-i18n-plugins-' . getmypid();
@mkdir($pluginsDir . '/active_plugin/lang', 0775, true);
@mkdir($pluginsDir . '/inactive_plugin/lang', 0775, true);

file_put_contents($pluginsDir . '/active_plugin/plugin.php', "<?php\n");
file_put_contents($pluginsDir . '/active_plugin/plugin.json', json_encode([
    'name' => 'Active',
    'version' => '1.0.0',
    'admin' => [
        'i18n' => ['path' => 'lang'],
    ],
], JSON_THROW_ON_ERROR));
file_put_contents($pluginsDir . '/active_plugin/lang/en.json', json_encode([
    '_meta' => ['name' => 'English'],
    'plugins' => [
        'active_plugin' => [
            'hello' => 'Active hello',
        ],
    ],
], JSON_THROW_ON_ERROR));
file_put_contents($pluginsDir . '/active_plugin/lang/de.json', json_encode([
    '_meta' => ['name' => 'German'],
    'plugins' => [
        'active_plugin' => [
            'hello' => 'Aktiv hallo',
        ],
    ],
], JSON_THROW_ON_ERROR));

file_put_contents($pluginsDir . '/inactive_plugin/plugin.php', "<?php\n");
file_put_contents($pluginsDir . '/inactive_plugin/plugin.json', json_encode([
    'name' => 'Inactive',
    'version' => '1.0.0',
    'admin' => [
        'i18n' => true,
    ],
], JSON_THROW_ON_ERROR));
file_put_contents($pluginsDir . '/inactive_plugin/lang/en.json', json_encode([
    'plugins' => [
        'inactive_plugin' => [
            'secret' => 'Should not load',
        ],
    ],
], JSON_THROW_ON_ERROR));

setup_plugin_tree($pluginsDir, [
    'active_plugin' => ['active' => true, 'settings' => []],
    'inactive_plugin' => ['active' => false, 'settings' => []],
]);

$catalogs = birkly_get_registered_plugin_i18n_catalogs();
assert_true('active plugin catalog registered', count($catalogs) === 1);
assert_true('registered plugin id', ($catalogs[0]['plugin_id'] ?? '') === 'active_plugin');

$activeHello = __('plugins.active_plugin.hello');
assert_true('active plugin key resolves in en', $activeHello === 'Active hello');

$jsDictionary = I18n::getJsDictionary(null);
assert_true(
    'js dictionary includes every active plugin',
    ($jsDictionary['plugins.active_plugin.hello'] ?? '') === 'Active hello'
);
assert_true(
    'js dictionary omits inactive plugin',
    !array_key_exists('plugins.inactive_plugin.secret', $jsDictionary)
);

$inactiveSecret = __('plugins.inactive_plugin.secret');
assert_true('inactive plugin key not merged', $inactiveSecret === 'plugins.inactive_plugin.secret');

I18n::setLanguage('de');
$deHello = __('plugins.active_plugin.hello');
assert_true('active plugin key resolves in de', $deHello === 'Aktiv hallo');

$missing = __('plugins.active_plugin.missing_key');
assert_true('missing plugin key falls back to key string', $missing === 'plugins.active_plugin.missing_key');

// Filter hook
add_filter('birkly_i18n_translations', static function (array $translations, string $language): array {
    if ($language === 'en') {
        $translations['admin']['filter_probe'] = 'Filtered';
    }
    return $translations;
}, 10);

I18n::resetForTests();
I18n::setLanguage('en');
assert_true('birkly_i18n_translations filter applies', __('admin.filter_probe') === 'Filtered');

// @i18n: resolver
assert_true(
    'birkly_i18n_resolve_string passes through plain text',
    birkly_i18n_resolve_string('Plain label') === 'Plain label'
);

if ($failures !== []) {
    fwrite(STDERR, "\n" . count($failures) . " failure(s).\n");
    exit(1);
}

echo "\nAll admin i18n shelf tests passed.\n";
