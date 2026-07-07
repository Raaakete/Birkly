<?php
/**
 * P14-M4 — Etsy + Square live import tests (mocked HTTP).
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Commerce Etsy + Square import tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';
commerce_bootstrap_cart_services();

require_once $root . '/plugins/commerce/includes/CatalogIndexer.php';
require_once $root . '/plugins/commerce/includes/ImportService.php';
require_once $root . '/plugins/commerce/includes/ImportCollectionHelper.php';
require_once $root . '/plugins/commerce/includes/CommerceImportCredentialStore.php';
require_once $root . '/plugins/commerce/includes/ImportHttpClient.php';
require_once $root . '/plugins/commerce/includes/EtsyImportAdapter.php';
require_once $root . '/plugins/commerce/includes/SquareImportAdapter.php';
require_once $root . '/plugins/commerce/includes/CommerceLiveImportService.php';

class TestP14ImportCredentialStore extends CommerceImportCredentialStore
{
    public function __construct(string $settingsDir, PaymentsCredentialStore $crypto)
    {
        $ref = new ReflectionClass(CommerceImportCredentialStore::class);
        $settingsDirProp = $ref->getProperty('settingsDir');
        $settingsDirProp->setAccessible(true);
        $settingsDirProp->setValue($this, rtrim($settingsDir, '/'));
        $settingsFileProp = $ref->getProperty('settingsFile');
        $settingsFileProp->setAccessible(true);
        $settingsFileProp->setValue($this, rtrim($settingsDir, '/') . '/commerce_import.json');
        $cryptoProp = $ref->getProperty('crypto');
        $cryptoProp->setAccessible(true);
        $cryptoProp->setValue($this, $crypto);
    }
}

class TestP14PaymentsStore extends PaymentsCredentialStore
{
    public function __construct(string $dataDir)
    {
        $ref = new ReflectionClass(PaymentsCredentialStore::class);
        $baseDirProp = $ref->getProperty('baseDir');
        $baseDirProp->setAccessible(true);
        $baseDirProp->setValue($this, rtrim($dataDir, '/'));
        $keyProp = $ref->getProperty('encryptionKey');
        $keyProp->setAccessible(true);
        $keyProp->setValue($this, base64_encode(hash('sha256', 'p14-import-test', true)));
        $ensure = $ref->getMethod('ensureDirectories');
        $ensure->setAccessible(true);
        $ensure->invoke($this);
    }
}

$pid = getmypid();
$settingsDir = sys_get_temp_dir() . '/birkly-p14-import-settings-' . $pid;
$dataDir = sys_get_temp_dir() . '/birkly-p14-import-data-' . $pid;
$contentRoot = sys_get_temp_dir() . '/birkly-p14-import-content-' . $pid;
@mkdir($settingsDir, 0755, true);
@mkdir($dataDir, 0755, true);
@mkdir($contentRoot . '/collections', 0755, true);

$crypto = new TestP14PaymentsStore($dataDir);
$credentialStore = new TestP14ImportCredentialStore($settingsDir, $crypto);
$indexer = new CatalogIndexer($dataDir);
$import = new ImportService($indexer, $contentRoot);
$collectionHelper = new ImportCollectionHelper($contentRoot);
$collectionHelper->resolveForImport('etsy-live', ['create' => true, 'title' => 'Etsy Live']);
$collectionHelper->resolveForImport('square-live', ['create' => true, 'title' => 'Square Live']);

$etsyListings = [];
for ($i = 1; $i <= 3; $i++) {
    $etsyListings[] = [
        'listing_id' => 5000 + $i,
        'title' => 'Etsy Listing ' . $i,
        'sku' => 'ETSY-' . $i,
        'quantity' => $i + 2,
        'price' => ['amount' => (1000 + $i * 100), 'divisor' => 100],
        'description' => 'Etsy item ' . $i,
    ];
}

$squareObjects = [];
for ($i = 1; $i <= 4; $i++) {
    $squareObjects[] = [
        'type' => 'ITEM',
        'id' => 'ITEM_' . $i,
        'item_data' => [
            'name' => 'Square Item ' . $i,
            'variations' => [[
                'id' => 'VAR_' . $i,
                'item_variation_data' => [
                    'name' => 'Default',
                    'sku' => 'SQ-' . $i,
                    'price_money' => ['amount' => 1500 + $i * 50, 'currency' => 'USD'],
                ],
            ]],
        ],
    ];
}

$httpHandler = function (string $method, string $url, array $options = []) use ($etsyListings, $squareObjects): array {
    if (str_contains($url, 'openapi.etsy.com') && str_contains($url, '/shops/')) {
        if (str_contains($url, '/listings/active')) {
            return ['ok' => true, 'status' => 200, 'data' => ['results' => $etsyListings, 'count' => count($etsyListings)], 'headers' => []];
        }
        return ['ok' => true, 'status' => 200, 'data' => ['shop_name' => 'Mock Etsy'], 'headers' => []];
    }
    if (str_contains($url, 'connect.squareup.com/v2/catalog/list')) {
        return ['ok' => true, 'status' => 200, 'data' => ['objects' => $squareObjects], 'headers' => []];
    }
    return ['ok' => false, 'status' => 404, 'error' => 'Unexpected URL: ' . $url, 'data' => null, 'headers' => []];
};

$http = new ImportHttpClient($httpHandler);
$etsyAdapter = new EtsyImportAdapter($http);
$squareAdapter = new SquareImportAdapter($http);
$service = new CommerceLiveImportService($credentialStore, $import, null, null, $etsyAdapter, $squareAdapter);

test_assert('etsy in PLATFORMS', isset(CommerceLiveImportService::PLATFORMS['etsy']));
test_assert('square in PLATFORMS', isset(CommerceLiveImportService::PLATFORMS['square']));

$credentialStore->savePlatformConfig('etsy', [
    'shop_id' => '12345',
    'api_key' => 'etsy-key',
    'access_token' => 'etsy-token',
    'collection' => 'etsy-live',
    'default_currency' => 'usd',
    'enabled' => true,
]);
$credentialStore->savePlatformConfig('square', [
    'location_id' => 'LOC1',
    'access_token' => 'square-token',
    'collection' => 'square-live',
    'default_currency' => 'usd',
    'enabled' => true,
]);

$etsyTest = $service->testConnection('etsy');
test_assert('etsy connection test', ($etsyTest['ok'] ?? false) === true);

$squareTest = $service->testConnection('square');
test_assert('square connection test', ($squareTest['ok'] ?? false) === true);

$etsySync = $service->syncPlatform('etsy');
test_assert('etsy sync ok', ($etsySync['ok'] ?? false) === true);
test_assert('etsy imported 3', ($etsySync['imported_count'] ?? 0) === 3);

$squareSync = $service->syncPlatform('square');
test_assert('square sync ok', ($squareSync['ok'] ?? false) === true);
test_assert('square imported 4', ($squareSync['imported_count'] ?? 0) === 4);

$index = $indexer->loadIndex();
test_assert('catalog has 7 entries', count($index['entries'] ?? []) === 7);

@unlink($settingsDir . '/commerce_import.json');
@rmdir($settingsDir);

exit(test_finish('commerce_etsy_square_import_test'));
