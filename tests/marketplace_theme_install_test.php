<?php
/**
 * P18-M4 — Theme/template_pack marketplace install path tests.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/marketplace.php';

test_reset_failures();
echo "Marketplace theme install tests\n\n";

$tmpThemes = sys_get_temp_dir() . '/birkly-themes-test-' . getmypid();
$tmpSettings = sys_get_temp_dir() . '/birkly-settings-test-' . getmypid();
mkdir($tmpThemes, 0755, true);
mkdir($tmpSettings, 0755, true);
$GLOBALS['__birkly_themes_dir'] = $tmpThemes;
$GLOBALS['__birkly_marketplace_settings_dir'] = $tmpSettings;

$zipPath = $tmpThemes . '/theme.zip';
$zip = new ZipArchive();
$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFromString('minimal-theme/index.html', '<html><body>Theme</body></html>');
$zip->addFromString('minimal-theme/theme.json', json_encode(['id' => 'minimal-theme', 'name' => 'Minimal']));
$zip->close();

$extract = birkly_marketplace_extract_theme_zip($zipPath, 'birkly-minimal-theme');
test_assert('theme zip extracts', !empty($extract['ok']));
test_assert('theme path under themes dir', is_dir($tmpThemes . '/birkly-minimal-theme'));
test_assert('index.html present', is_file($tmpThemes . '/birkly-minimal-theme/index.html'));

birkly_marketplace_remove_directory($tmpThemes . '/birkly-minimal-theme');

$GLOBALS['birkly_marketplace_mock_handler'] = function (string $method, string $path, ?array $body, array $query) use ($zipPath): array {
    if ($method === 'GET' && str_contains($path, '/api/v1/offers/birkly-minimal-theme/releases/1.0.0')) {
        return [
            'ok' => true,
            'status' => 200,
            'data' => [
                'version' => '1.0.0',
                'download_url' => 'file://' . $zipPath,
                'checksum_sha256' => hash_file('sha256', $zipPath),
            ],
        ];
    }
    return ['ok' => false, 'status' => 404, 'data' => null, 'error' => 'not_found'];
};

$installed = birkly_marketplace_install_offer('birkly-minimal-theme', '1.0.0', 'BIRKLY-LOCAL-FREE-TEST', 'theme');
test_assert('install_offer installs theme', !empty($installed['ok']));
test_assert('theme id returned', ($installed['theme_id'] ?? '') === 'birkly-minimal-theme');
require_once dirname(__DIR__) . '/api/storage.php';
$storage = new Storage($tmpSettings);
$installed = $storage->read('installed_themes.json');
test_assert('installed themes registry written', is_array($installed) && isset($installed['birkly-minimal-theme']));

$pluginRoute = birkly_marketplace_install_offer('some-plugin', '1.0.0', '', 'plugin');
test_assert('plugin route delegates (fails without mock)', empty($pluginRoute['ok']));

unset($GLOBALS['birkly_marketplace_mock_handler'], $GLOBALS['__birkly_themes_dir'], $GLOBALS['__birkly_marketplace_settings_dir']);
birkly_marketplace_remove_directory($tmpThemes);

exit(test_finish('marketplace_theme_install_test'));
