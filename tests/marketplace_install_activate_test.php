<?php
/**
 * Fresh Hub installs activate the plugin so admin sidebar nav appears on reload.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/birkly_data_paths.php';
require_once $root . '/core/marketplace.php';

$failures = [];

function assert_true(bool $cond, string $label): void
{
    global $failures;
    if ($cond) {
        echo "OK  $label\n";
        return;
    }
    echo "FAIL $label\n";
    $failures[] = $label;
}

$settingsDir = sys_get_temp_dir() . '/birkly-mp-install-act-' . bin2hex(random_bytes(4));
$pluginsDir = sys_get_temp_dir() . '/birkly-mp-plugins-act-' . bin2hex(random_bytes(4));
mkdir($settingsDir, 0755, true);
mkdir($pluginsDir, 0755, true);

$GLOBALS['__birkly_plugin_state_settings_dir'] = $settingsDir;
$GLOBALS['__birkly_plugins_dir'] = $pluginsDir;
$GLOBALS['birkly_plugins'] = [];

$zipPath = $pluginsDir . '/artifact.zip';
$zip = new ZipArchive();
assert_true($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'create test zip');
$zip->addFromString('plugin.json', json_encode([
    'id' => 'sample-hub',
    'name' => 'Sample Hub Plugin',
    'version' => '1.0.0',
    'requires' => '0.1.0',
    'description' => 'Install activation test',
], JSON_THROW_ON_ERROR));
$zip->addFromString('plugin.php', "<?php\n// sample hub plugin\n");
$zip->close();

$checksum = hash_file('sha256', $zipPath);
$slug = 'sample-hub-offer';
$version = '1.0.0';

$GLOBALS['birkly_marketplace_mock_handler'] = static function (string $method, string $path) use ($slug, $version, $checksum): array {
    if ($method === 'GET' && str_contains($path, '/releases/' . rawurlencode($version))) {
        return [
            'ok' => true,
            'status' => 200,
            'data' => [
                'version' => $version,
                'download_url' => 'https://marketplace.test/artifacts/' . $slug . '/' . $version . '/download',
                'checksum_sha256' => $checksum,
            ],
            'error' => null,
        ];
    }

    return ['ok' => false, 'status' => 404, 'data' => null, 'error' => 'not found'];
};

$GLOBALS['birkly_marketplace_http_download'] = static function (
    string $url,
    string $destinationPath
) use ($zipPath): array {
    if (!copy($zipPath, $destinationPath)) {
        return ['ok' => false, 'error' => 'copy failed'];
    }

    return ['ok' => true];
};

$install = birkly_marketplace_install_plugin($slug, $version, '', false, true);
assert_true(!empty($install['ok']), 'fresh install succeeds');
assert_true(!empty($install['activated']), 'fresh install reports activated');
assert_true(birkly_is_plugin_active('sample-hub'), 'plugin is active after fresh install');

$saved = json_decode((string) file_get_contents($settingsDir . '/plugins.json'), true);
assert_true(
    is_array($saved) && !empty($saved['sample-hub']['active']),
    'plugins.json active flag persisted after fresh install'
);

// Upgrade path must not force activation when plugin was left inactive.
set_plugin_state('sample-hub', false);
$upgrade = birkly_marketplace_install_plugin($slug, $version, '', true, true);
assert_true(!empty($upgrade['ok']), 'upgrade install succeeds');
assert_true(empty($upgrade['activated']), 'upgrade does not report activated');
assert_true(!birkly_is_plugin_active('sample-hub'), 'inactive plugin stays inactive after upgrade');

unset(
    $GLOBALS['birkly_marketplace_mock_handler'],
    $GLOBALS['birkly_marketplace_http_download'],
    $GLOBALS['__birkly_plugin_state_settings_dir'],
    $GLOBALS['__birkly_plugins_dir'],
    $GLOBALS['birkly_plugins']
);

@unlink($zipPath);
@unlink($pluginsDir . '/sample-hub/plugin.php');
@unlink($pluginsDir . '/sample-hub/plugin.json');
@rmdir($pluginsDir . '/sample-hub');
@rmdir($pluginsDir);
@unlink($settingsDir . '/plugins.json');
@rmdir($settingsDir);

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "marketplace_install_activate_test: OK\n";
