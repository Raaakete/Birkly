<?php
/**
 * P9-M6 — Marketplace client tests (installation_id, zip install, registry helpers).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/marketplace.php';

test_reset_failures();
echo "Marketplace client tests\n\n";

$settingsDir = sys_get_temp_dir() . '/birkly-mp-client-' . getmypid();
mkdir($settingsDir, 0755, true);
$installFile = $settingsDir . '/installation.json';
$marketplaceFile = $settingsDir . '/marketplace.json';

// Point storage at temp settings via installation_id internals
$origStoragePath = null;
birkly_marketplace_settings_reset();
birkly_installation_id_reset();

// Override installation_id storage by writing directly
file_put_contents($installFile, json_encode(['installation_id' => 'inst_test1234567890abcdef']));
$GLOBALS['__birkly_installation_id'] = 'inst_test1234567890abcdef';
test_assert('installation_id can be set stable', birkly_installation_id() === 'inst_test1234567890abcdef');

putenv('BIRKLY_MARKETPLACE_URL=https://marketplace.example.test');
birkly_marketplace_settings_reset();
test_assert('marketplace_url from env', birkly_marketplace_url() === 'https://marketplace.example.test');
putenv('BIRKLY_MARKETPLACE_URL');
birkly_marketplace_settings_reset();

test_assert('marketplace_url default', birkly_marketplace_url() === BIRKLY_MARKETPLACE_DEFAULT_URL);

test_assert('license ref last segment', birkly_marketplace_license_ref('BIRKLY-AAAA-BBBB-CCCC-DDDD') === 'DDDD');

$offers = [
    ['slug' => 'alpha', 'title' => 'Alpha Tools', 'summary' => 'Utility pack', 'offer_type' => 'plugin', 'category' => 'utilities', 'tags' => ['tools']],
    ['slug' => 'beta', 'title' => 'Beta Theme', 'summary' => 'A theme', 'offer_type' => 'theme', 'category' => 'themes', 'tags' => []],
];
$filtered = birkly_marketplace_filter_offers($offers, 'utility', '', 'plugin');
test_assert('filter by query and type', count($filtered) === 1 && ($filtered[0]['slug'] ?? '') === 'alpha');

// Zip install test
$zipPath = $settingsDir . '/test-plugin.zip';
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $zip->addFromString('plugin.json', json_encode([
        'name' => 'Test Marketplace Plugin',
        'version' => '1.2.0',
        'description' => 'Test plugin',
        'capabilities' => ['read_entries'],
    ], JSON_THROW_ON_ERROR));
    $zip->addFromString('plugin.php', "<?php\n// test\n");
    $zip->close();
}

$preview = birkly_marketplace_preview_zip_capabilities($zipPath);
test_assert('zip capability preview ok', !empty($preview['ok']));
test_assert('capabilities extracted', in_array('read_entries', $preview['capabilities'] ?? [], true));

$pluginsRoot = dirname(__DIR__) . '/plugins';
$testPluginId = 'mp_test_' . getmypid();
$dest = $pluginsRoot . '/' . $testPluginId;
if (is_dir($dest)) {
    birkly_marketplace_remove_directory($dest);
}

$extract = birkly_marketplace_extract_plugin_zip($zipPath, $testPluginId, false);
test_assert('zip extract succeeds', !empty($extract['ok']));
test_assert('plugin.php exists after extract', is_file($dest . '/plugin.php'));

if (is_dir($dest)) {
    birkly_marketplace_remove_directory($dest);
}

$fallbackCatalog = birkly_marketplace_fallback_catalog();
test_assert('bundled fallback catalog has offers', count($fallbackCatalog['offers'] ?? []) >= 2);

$GLOBALS['birkly_marketplace_mock_handler'] = function (string $method, string $path, ?array $body, array $query): array {
    return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'Marketplace request failed'];
};
$browseFallback = birkly_marketplace_browse('', '', 'plugin');
test_assert('browse uses fallback when registry fails', !empty($browseFallback['ok']) && !empty($browseFallback['fallback']) && count($browseFallback['offers']) >= 2);
$firstOffer = $browseFallback['offers'][0] ?? [];
test_assert('browse offers include install_state', isset($firstOffer['install_state']) && is_array($firstOffer['install_state']));
$unknownState = birkly_marketplace_offer_install_state('definitely-not-installed-offer-slug');
test_assert('unknown offer slug not installed', ($unknownState['installed'] ?? true) === false);

test_assert('official slug map includes commerce', (birkly_official_marketplace_slugs()['commerce'] ?? '') === 'birkly-commerce');
test_assert(
    'plugin offer slug resolves from official map',
    birkly_marketplace_plugin_offer_slug('events', ['version' => '1.0.0']) === 'birkly-events'
);
test_assert(
    'plugin offer slug prefers stored marketplace_slug',
    birkly_marketplace_plugin_offer_slug('events', ['marketplace_slug' => 'custom-slug']) === 'custom-slug'
);

unset($GLOBALS['birkly_marketplace_mock_handler']);

// Mock HTTP handler
$GLOBALS['birkly_marketplace_mock_handler'] = function (string $method, string $path, ?array $body, array $query): array {
    if ($method === 'GET' && $path === '/api/v1/registry') {
        return [
            'ok' => true,
            'status' => 200,
            'data' => [
                'api_version' => 'v1',
                'offers' => [
                    ['slug' => 'mock-plugin', 'title' => 'Mock', 'summary' => 'Mock plugin', 'offer_type' => 'plugin', 'category' => 'utilities'],
                ],
            ],
        ];
    }
    if ($method === 'POST' && $path === '/api/v1/licenses/activate') {
        return [
            'ok' => true,
            'status' => 200,
            'data' => [
                'ok' => true,
                'code' => 'activated',
                'offer_slug' => 'mock-plugin',
                'license' => ['offer_slug' => 'mock-plugin'],
            ],
        ];
    }
    if ($method === 'GET' && str_contains($path, '/api/v1/offers/mock-plugin')) {
        return [
            'ok' => true,
            'status' => 200,
            'data' => [
                'offer' => ['slug' => 'mock-plugin', 'title' => 'Mock'],
                'releases' => [
                    ['version' => '1.0.0'],
                    ['version' => '1.1.0'],
                ],
            ],
        ];
    }
    return ['ok' => false, 'status' => 404, 'data' => ['error' => 'not_found'], 'error' => 'not_found'];
};

$browse = birkly_marketplace_browse('', '', 'plugin');
test_assert('mock browse ok', !empty($browse['ok']) && count($browse['offers']) === 1);

$activate = birkly_marketplace_activate_license('BIRKLY-TEST-KEY');
test_assert('mock activate ok', !empty($activate['ok']) && ($activate['offer_slug'] ?? '') === 'mock-plugin');

$updateCheck = birkly_marketplace_check_plugin_update('mock-plugin', '1.0.0');
test_assert('update available detected', !empty($updateCheck['update_available']) && ($updateCheck['latest_version'] ?? '') === '1.1.0');

unset($GLOBALS['birkly_marketplace_mock_handler']);

// Cleanup temp settings
@unlink($installFile);
@unlink($marketplaceFile);
@unlink($zipPath);
@rmdir($settingsDir);

birkly_marketplace_settings_reset();
birkly_installation_id_reset();

exit(test_finish('marketplace_client_test'));
