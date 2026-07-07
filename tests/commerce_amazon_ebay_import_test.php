<?php
/**
 * P17-M3 — Amazon + eBay live import tests (mocked HTTP).
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Commerce Amazon + eBay import tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';
commerce_bootstrap_cart_services();

require_once $root . '/plugins/commerce/includes/CatalogIndexer.php';
require_once $root . '/plugins/commerce/includes/ImportService.php';
require_once $root . '/plugins/commerce/includes/ImportCollectionHelper.php';
require_once $root . '/plugins/commerce/includes/CommerceImportCredentialStore.php';
require_once $root . '/plugins/commerce/includes/ImportHttpClient.php';
require_once $root . '/plugins/commerce/includes/AmazonImportAdapter.php';
require_once $root . '/plugins/commerce/includes/EbayImportAdapter.php';
require_once $root . '/plugins/commerce/includes/CommerceLiveImportService.php';

class TestP17ImportCredentialStore extends CommerceImportCredentialStore
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

class TestP17PaymentsStore extends PaymentsCredentialStore
{
    public function __construct(string $dataDir)
    {
        $ref = new ReflectionClass(PaymentsCredentialStore::class);
        $baseDirProp = $ref->getProperty('baseDir');
        $baseDirProp->setAccessible(true);
        $baseDirProp->setValue($this, rtrim($dataDir, '/'));
        $keyProp = $ref->getProperty('encryptionKey');
        $keyProp->setAccessible(true);
        $keyProp->setValue($this, base64_encode(hash('sha256', 'p17-import-test', true)));
        $ensure = $ref->getMethod('ensureDirectories');
        $ensure->setAccessible(true);
        $ensure->invoke($this);
    }
}

$pid = getmypid();
$settingsDir = sys_get_temp_dir() . '/birkly-p17-import-settings-' . $pid;
$dataDir = sys_get_temp_dir() . '/birkly-p17-import-data-' . $pid;
$contentRoot = sys_get_temp_dir() . '/birkly-p17-import-content-' . $pid;
@mkdir($settingsDir, 0755, true);
@mkdir($dataDir, 0755, true);
@mkdir($contentRoot . '/collections', 0755, true);

$crypto = new TestP17PaymentsStore($dataDir);
$credentialStore = new TestP17ImportCredentialStore($settingsDir, $crypto);
$indexer = new CatalogIndexer($dataDir);
$import = new ImportService($indexer, $contentRoot);
$collectionHelper = new ImportCollectionHelper($contentRoot);
$collectionHelper->resolveForImport('amazon-live', ['create' => true, 'title' => 'Amazon Live']);
$collectionHelper->resolveForImport('ebay-live', ['create' => true, 'title' => 'eBay Live']);

$amazonItems = [];
for ($i = 1; $i <= 2; $i++) {
    $amazonItems[] = [
        'asin' => 'B00' . $i,
        'sku' => 'AMZ-' . $i,
        'title' => 'Amazon Item ' . $i,
        'summaries' => [['itemName' => 'Amazon Item ' . $i, 'brand' => 'Brand']],
        'offers' => [['price' => ['amount' => 19.99 + $i]]],
        'inventory' => ['quantity' => $i + 1],
    ];
}

$ebayItems = [];
for ($i = 1; $i <= 3; $i++) {
    $ebayItems[] = [
        'sku' => 'EBAY-' . $i,
        'product' => ['title' => 'eBay Item ' . $i, 'description' => 'Desc'],
        'availability' => ['shipToLocationAvailability' => ['quantity' => $i]],
    ];
}

$httpHandler = function (string $method, string $url, array $options = []) use ($amazonItems, $ebayItems): array {
    if (str_contains($url, 'sellingpartnerapi-na.amazon.com')) {
        if (str_contains($url, 'pageSize=1')) {
            return ['ok' => true, 'status' => 200, 'data' => ['items' => [$amazonItems[0]]], 'headers' => []];
        }
        return ['ok' => true, 'status' => 200, 'data' => ['items' => $amazonItems, 'pagination' => []], 'headers' => []];
    }
    if (str_contains($url, 'api.ebay.com/sell/inventory/v1/inventory_item')) {
        return ['ok' => true, 'status' => 200, 'data' => ['inventoryItems' => $ebayItems, 'total' => count($ebayItems)], 'headers' => []];
    }
    return ['ok' => false, 'status' => 404, 'error' => 'Unexpected URL: ' . $url, 'data' => null, 'headers' => []];
};

$http = new ImportHttpClient($httpHandler);
$amazonAdapter = new AmazonImportAdapter($http);
$ebayAdapter = new EbayImportAdapter($http);
$service = new CommerceLiveImportService($credentialStore, $import, null, null, null, null, $amazonAdapter, $ebayAdapter);

test_assert('amazon in PLATFORMS', isset(CommerceLiveImportService::PLATFORMS['amazon']));
test_assert('ebay in PLATFORMS', isset(CommerceLiveImportService::PLATFORMS['ebay']));

$credentialStore->savePlatformConfig('amazon', [
    'seller_id' => 'SELLER1',
    'marketplace_id' => 'ATVPDKIKX0DER',
    'refresh_token' => 'amz-refresh',
    'collection' => 'amazon-live',
    'default_currency' => 'usd',
    'enabled' => true,
]);
$credentialStore->savePlatformConfig('ebay', [
    'access_token' => 'ebay-token',
    'marketplace_id' => 'EBAY_US',
    'collection' => 'ebay-live',
    'default_currency' => 'usd',
    'enabled' => true,
]);

$amazonTest = $service->testConnection('amazon');
test_assert('amazon connection test', ($amazonTest['ok'] ?? false) === true);

$ebayTest = $service->testConnection('ebay');
test_assert('ebay connection test', ($ebayTest['ok'] ?? false) === true);

$amazonSync = $service->syncPlatform('amazon');
test_assert('amazon sync ok', ($amazonSync['ok'] ?? false) === true);
test_assert('amazon imported 2', ($amazonSync['imported_count'] ?? 0) === 2);

$ebaySync = $service->syncPlatform('ebay');
test_assert('ebay sync ok', ($ebaySync['ok'] ?? false) === true);
test_assert('ebay imported 3', ($ebaySync['imported_count'] ?? 0) === 3);

$index = $indexer->loadIndex();
test_assert('catalog has 5 entries', count($index['entries'] ?? []) === 5);

@unlink($settingsDir . '/commerce_import.json');
@rmdir($settingsDir);

exit(test_finish('commerce_amazon_ebay_import_test'));
