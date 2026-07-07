<?php
/**
 * P12-M3 — Live Shopify + WooCommerce REST API import tests (mocked HTTP).
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Commerce live import tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';
commerce_bootstrap_cart_services();

require_once $root . '/plugins/commerce/includes/CatalogIndexer.php';
require_once $root . '/plugins/commerce/includes/ImportService.php';
require_once $root . '/plugins/commerce/includes/ImportCollectionHelper.php';
require_once $root . '/plugins/commerce/includes/CommerceImportCredentialStore.php';
require_once $root . '/plugins/commerce/includes/ImportHttpClient.php';
require_once $root . '/plugins/commerce/includes/ShopifyImportAdapter.php';
require_once $root . '/plugins/commerce/includes/WooImportAdapter.php';
require_once $root . '/plugins/commerce/includes/CommerceLiveImportService.php';

class TestLiveImportCredentialStore extends CommerceImportCredentialStore
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

class TestLiveImportPaymentsStore extends PaymentsCredentialStore
{
    public function __construct(string $dataDir)
    {
        $ref = new ReflectionClass(PaymentsCredentialStore::class);
        $baseDirProp = $ref->getProperty('baseDir');
        $baseDirProp->setAccessible(true);
        $baseDirProp->setValue($this, rtrim($dataDir, '/'));

        $keyProp = $ref->getProperty('encryptionKey');
        $keyProp->setAccessible(true);
        $keyProp->setValue($this, base64_encode(hash('sha256', 'live-import-test-key', true)));

        $ensure = $ref->getMethod('ensureDirectories');
        $ensure->setAccessible(true);
        $ensure->invoke($this);
    }
}

$pid = getmypid();
$settingsDir = sys_get_temp_dir() . '/birkly-live-import-settings-' . $pid;
$dataDir = sys_get_temp_dir() . '/birkly-live-import-data-' . $pid;
$contentRoot = sys_get_temp_dir() . '/birkly-live-import-content-' . $pid;
@mkdir($settingsDir, 0755, true);
@mkdir($dataDir, 0755, true);
@mkdir($contentRoot . '/collections', 0755, true);

$crypto = new TestLiveImportPaymentsStore($dataDir);
$credentialStore = new TestLiveImportCredentialStore($settingsDir, $crypto);
$indexer = new CatalogIndexer($dataDir);
$import = new ImportService($indexer, $contentRoot);
$collectionHelper = new ImportCollectionHelper($contentRoot);
$collectionHelper->resolveForImport('shopify-live', ['create' => true, 'title' => 'Shopify Live']);
$collectionHelper->resolveForImport('woo-live', ['create' => true, 'title' => 'Woo Live']);

$shopifyProducts = [];
for ($i = 1; $i <= 10; $i++) {
    $shopifyProducts[] = [
        'id' => 1000 + $i,
        'title' => 'Shopify Product ' . $i,
        'handle' => 'shopify-product-' . $i,
        'body_html' => '<p>Description ' . $i . '</p>',
        'variants' => [[
            'id' => 2000 + $i,
            'sku' => 'SHOP-' . $i,
            'price' => number_format(10 + $i, 2, '.', ''),
            'inventory_quantity' => $i * 2,
            'title' => 'Default Title',
        ]],
        'images' => [['src' => 'https://cdn.example.com/p' . $i . '.jpg']],
    ];
}

$wooProducts = [];
for ($i = 1; $i <= 5; $i++) {
    $wooProducts[] = [
        'id' => 3000 + $i,
        'name' => 'Woo Product ' . $i,
        'slug' => 'woo-product-' . $i,
        'sku' => 'WOO-' . $i,
        'price' => number_format(5 + $i, 2, '.', ''),
        'regular_price' => number_format(5 + $i, 2, '.', ''),
        'stock_quantity' => $i + 1,
        'description' => 'Woo description ' . $i,
        'type' => 'simple',
        'images' => [['src' => 'https://woo.example.com/p' . $i . '.jpg']],
    ];
}

$httpHandler = function (string $method, string $url, array $options = []) use ($shopifyProducts, $wooProducts): array {
    if (str_contains($url, '/admin/api/') && str_contains($url, 'shop.json')) {
        return [
            'ok' => true,
            'status' => 200,
            'error' => null,
            'data' => ['shop' => ['name' => 'Mock Shopify Store']],
            'headers' => [],
        ];
    }
    if (str_contains($url, '/admin/api/') && str_contains($url, 'products.json')) {
        return [
            'ok' => true,
            'status' => 200,
            'error' => null,
            'data' => ['products' => $shopifyProducts],
            'headers' => [],
        ];
    }
    if (str_contains($url, '/wp-json/wc/v3/products') && !str_contains($url, '/variations')) {
        return [
            'ok' => true,
            'status' => 200,
            'error' => null,
            'data' => $wooProducts,
            'headers' => [],
        ];
    }
    if (str_contains($url, '/wp-json/wc/v3/system_status')) {
        return ['ok' => true, 'status' => 200, 'error' => null, 'data' => ['environment' => []], 'headers' => []];
    }

    return ['ok' => false, 'status' => 404, 'error' => 'Unexpected URL: ' . $url, 'data' => null, 'headers' => []];
};

$http = new ImportHttpClient($httpHandler);
$shopifyAdapter = new ShopifyImportAdapter($http);
$wooAdapter = new WooImportAdapter($http);
$service = new CommerceLiveImportService($credentialStore, $import, $shopifyAdapter, $wooAdapter);

$credentialStore->savePlatformConfig('shopify', [
    'shop_domain' => 'mock-store.myshopify.com',
    'access_token' => 'shpat_test_token',
    'collection' => 'shopify-live',
    'default_currency' => 'eur',
    'enabled' => true,
    'schedule_enabled' => true,
]);
$credentialStore->savePlatformConfig('woocommerce', [
    'site_url' => 'https://woo.example.com',
    'consumer_key' => 'ck_test',
    'consumer_secret' => 'cs_test',
    'collection' => 'woo-live',
    'default_currency' => 'usd',
    'enabled' => true,
    'schedule_enabled' => false,
]);

$public = $service->getPublicConfig();
test_assert('shopify connected flag', ($public['shopify']['connected'] ?? false) === true);
test_assert('woo connected flag', ($public['woocommerce']['connected'] ?? false) === true);
test_assert('credentials not exposed in public config', !isset($public['shopify']['access_token']));
test_assert('cron token generated', strlen((string) ($public['cron_token'] ?? '')) >= 32);

$settingsRaw = json_decode((string) file_get_contents($settingsDir . '/commerce_import.json'), true);
test_assert('shopify token encrypted at rest', !empty($settingsRaw['shopify']['access_token_enc']));
test_assert('woo key encrypted at rest', !empty($settingsRaw['woocommerce']['consumer_key_enc']));

$shopifyTest = $service->testConnection('shopify');
test_assert('shopify connection test', ($shopifyTest['ok'] ?? false) === true);

$wooTest = $service->testConnection('woocommerce');
test_assert('woo connection test', ($wooTest['ok'] ?? false) === true);

$shopifyPreview = $service->preview('shopify');
test_assert('shopify preview ok', ($shopifyPreview['ok'] ?? false) === true);
test_assert('shopify preview count', ($shopifyPreview['count'] ?? 0) === 10);

$shopifySync = $service->syncPlatform('shopify');
test_assert('shopify sync ok', ($shopifySync['ok'] ?? false) === true);
test_assert('shopify sync imported 10', ($shopifySync['imported_count'] ?? 0) === 10);

$shopifyEntries = glob($contentRoot . '/collections/shopify-live/entries/*.json') ?: [];
test_assert('shopify writes 10 collection entries', count($shopifyEntries) === 10);

$firstShopifyEntry = json_decode((string) file_get_contents($shopifyEntries[0]), true);
test_assert('shopify entry has commerce fieldtype', isset($firstShopifyEntry['pricing']['purchase_options']));
test_assert('shopify entry has title', !empty($firstShopifyEntry['title']));

$index = $indexer->loadIndex();
test_assert('catalog indexer has shopify products', count($index['entries'] ?? []) === 10);

$wooSync = $service->syncPlatform('woocommerce');
test_assert('woo sync ok', ($wooSync['ok'] ?? false) === true);
test_assert('woo sync imported 5', ($wooSync['imported_count'] ?? 0) === 5);

$wooEntries = glob($contentRoot . '/collections/woo-live/entries/*.json') ?: [];
test_assert('woo writes 5 collection entries', count($wooEntries) === 5);

$scheduled = $service->syncScheduled();
test_assert('scheduled sync runs shopify only', count($scheduled) === 1);
test_assert('scheduled sync platform shopify', ($scheduled[0]['platform'] ?? '') === 'shopify');

$cronToken = $credentialStore->getCronToken();
test_assert('cron token matches', $credentialStore->cronTokenMatches($cronToken) === true);
test_assert('cron token rejects invalid', $credentialStore->cronTokenMatches('bad-token') === false);

$shopifyFetch = $shopifyAdapter->fetchProductRows([
    'shop_domain' => 'mock-store.myshopify.com',
    'access_token' => 'shpat_test_token',
]);
test_assert('adapter fetch product count', ($shopifyFetch['product_count'] ?? 0) === 10);
test_assert('adapter fetch pages', ($shopifyFetch['pages_fetched'] ?? 0) === 1);

@unlink($settingsDir . '/commerce_import.json');
@rmdir($settingsDir);
@unlink($dataDir . '/catalog_index.json');
@array_map('unlink', glob($dataDir . '/entries/*.json') ?: []);
@rmdir($dataDir . '/entries');
@rmdir($dataDir);

$deleteDir = function (string $path) use (&$deleteDir): void {
    if (!file_exists($path)) {
        return;
    }
    if (is_file($path)) {
        @unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $deleteDir($path . '/' . $item);
    }
    @rmdir($path);
};
$deleteDir($contentRoot);

exit(test_finish('Commerce live import tests'));
