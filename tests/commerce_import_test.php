<?php
/**
 * P6.4-M4 — CSV import tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Commerce import tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';
require_once $root . '/plugins/commerce/includes/CatalogIndexer.php';
require_once $root . '/plugins/commerce/includes/ImportService.php';
require_once $root . '/plugins/commerce/includes/ImportCollectionHelper.php';

$dataDir = sys_get_temp_dir() . '/birkly-commerce-import-' . getmypid();
$contentRoot = sys_get_temp_dir() . '/birkly-commerce-import-content-' . getmypid();
@mkdir($dataDir, 0755, true);
@mkdir($contentRoot . '/collections', 0755, true);

$indexer = new CatalogIndexer($dataDir);
$import = new ImportService($indexer, $contentRoot);
$collectionHelper = new ImportCollectionHelper($contentRoot);

$csv = "title,price,currency,sku,quantity\nRed Shirt,29.99,eur,RED-M,10\nBlue Shirt,24.50,usd,BLU-L,5\nGreen Hat,9.99,eur,HAT-1,\n";

$parsed = $import->parseCsv($csv, 'generic');
test_assert('parse ok', ($parsed['ok'] ?? false) === true);
test_assert('three rows parsed', count($parsed['rows'] ?? []) === 3);
test_assert('first row price cents', ($parsed['rows'][0]['commerce']['price_cents'] ?? 0) === 2999);

$shopifyCsv = "Handle,Title,Variant SKU,Variant Price,Option1 Value\n"
    . "gutschein,Gutschein,,10.00,\"€10,00\"\n"
    . "gutschein,,,25.00,\"€25,00\"\n"
    . "gutschein,,,50.00,\"€50,00\"\n"
    . "karotte-brot,Karotte-Brot,KB-1,6.50,Default\n";
$shopify = $import->parseCsv($shopifyCsv, 'shopify');
test_assert('shopify preset ok', ($shopify['ok'] ?? false) === true);
test_assert('shopify groups products and variants', count($shopify['rows'] ?? []) === 4);
test_assert('shopify first row is product', empty($shopify['rows'][0]['parent_sku'] ?? null));
test_assert('shopify second row is variant', !empty($shopify['rows'][1]['parent_sku'] ?? null));
$shopifyDry = $import->dryRun($shopify['rows'], 'products');
test_assert('shopify dry run product count', ($shopifyDry['product_count'] ?? 0) === 2);
test_assert('shopify dry run variant count', ($shopifyDry['variant_count'] ?? 0) === 2);

$wooCsv = "Name,SKU,Regular price,Stock\nWoo Product,WOO-1,19.99,3\n";
$woo = $import->parseCsv($wooCsv, 'woocommerce');
test_assert('woocommerce preset ok', ($woo['ok'] ?? false) === true);
test_assert('woocommerce row title', ($woo['rows'][0]['title'] ?? '') === 'Woo Product');

$sources = ImportService::getImportSources();
test_assert('import sources include shopify', count(array_filter($sources, fn ($s) => $s['id'] === 'shopify')) === 1);
test_assert('import sources include etsy', count(array_filter($sources, fn ($s) => $s['id'] === 'etsy')) === 1);
test_assert('import sources include inventory category', count(array_filter($sources, fn ($s) => ($s['category'] ?? '') === 'inventory')) >= 8);
test_assert('import sources count', count($sources) >= 20);

$magentoCsv = "sku,name,price,qty\nMAG-1,Magento Tee,34.99,12\n";
$magento = $import->parseCsv($magentoCsv, 'magento');
test_assert('magento preset ok', ($magento['ok'] ?? false) === true);
test_assert('magento row title', ($magento['rows'][0]['title'] ?? '') === 'Magento Tee');

$zohoCsv = "Item Name,SKU,Rate,Available Stock\nWidget Pro,WID-1,15.50,20\n";
$zoho = $import->parseCsv($zohoCsv, 'zoho_inventory');
test_assert('zoho inventory preset ok', ($zoho['ok'] ?? false) === true);
test_assert('zoho inventory row title', ($zoho['rows'][0]['title'] ?? '') === 'Widget Pro');

$etsyCsv = "TITLE,SKU,PRICE,QUANTITY\nHandmade Mug,MUG-1,24.00,5\n";
$etsy = $import->parseCsv($etsyCsv, 'etsy');
test_assert('etsy preset ok', ($etsy['ok'] ?? false) === true);
test_assert('etsy row title', ($etsy['rows'][0]['title'] ?? '') === 'Handmade Mug');

$withCurrency = $import->applyDefaultCurrency($woo['rows'], 'usd');
test_assert('default currency applied', ($withCurrency[0]['commerce']['purchase_options'][0]['currency'] ?? '') === 'usd');

$dry = $import->dryRun($parsed['rows'], 'products');
test_assert('dry run ok', ($dry['ok'] ?? false) === true);
test_assert('dry run count 3', ($dry['count'] ?? 0) === 3);

$entriesDir = $dataDir . '/entries';
@mkdir($entriesDir, 0755, true);
foreach ($parsed['rows'] as $i => $row) {
    $entry = [
        'id' => 'imp_' . $i,
        'title' => $row['title'],
        'slug' => $row['slug'],
        'commerce' => $row['commerce'],
    ];
    file_put_contents($entriesDir . '/' . $entry['id'] . '.json', json_encode($entry));
    $indexer->indexEntry($entry, 'products');
}

$index = $indexer->loadIndex();
test_assert('catalog has 3 purchasable entries', count($index['entries'] ?? []) === 3);

$historyDir = sys_get_temp_dir() . '/birkly-commerce-history-' . getmypid();
@mkdir($historyDir, 0755, true);
require_once $root . '/plugins/commerce/includes/ImportHistoryStore.php';
$historyStore = new ImportHistoryStore($historyDir);
$record = $historyStore->add([
    'status' => 'success',
    'preset' => 'woocommerce',
    'platform' => 'WooCommerce',
    'collection' => 'products',
    'imported_count' => 1,
    'created_count' => 1,
    'skipped_count' => 0,
]);
test_assert('history record saved', !empty($record['id']));
test_assert('history list returns record', count($historyStore->list()) === 1);
test_assert('history get by id', ($historyStore->get($record['id'])['platform'] ?? '') === 'WooCommerce');

$importResult = $import->importRows($parsed['rows'], 'imported-products', false, [
    'preset' => 'generic',
    'platform' => 'Generic CSV',
    'currency' => 'eur',
    'file_name' => 'test.csv',
    'create_collection' => true,
    'collection_title' => 'Imported Products',
]);
test_assert('import rows records history', !empty($importResult['import_id']));
test_assert('import status success', ($importResult['status'] ?? '') === 'success');
test_assert('import creates collection', ($importResult['collection_created'] ?? false) === true);
test_assert('import collection slug', ($importResult['collection'] ?? '') === 'imported-products');

$entryFiles = glob($contentRoot . '/collections/imported-products/entries/*.json') ?: [];
test_assert('import writes collection entries', count($entryFiles) === 3);
$firstEntry = json_decode((string) file_get_contents($entryFiles[0]), true);
test_assert('import entry uses commerce fieldtype key', isset($firstEntry['pricing']['purchase_options']));
test_assert('import entry has timestamps', !empty($firstEntry['created_at']) && !empty($firstEntry['updated_at']));
test_assert('import collection schema exists', is_file($contentRoot . '/collections/imported-products/schema.json'));

$list = $import->listImportCollections();
test_assert('list import collections includes imported', count(array_filter($list, fn ($c) => ($c['slug'] ?? '') === 'imported-products')) === 1);
test_assert('commerce field type helper', ImportCollectionHelper::isCommerceFieldType('commerce_events') === true);
test_assert('commerce bundle excluded', ImportCollectionHelper::isCommerceFieldType('commerce_bundle') === false);

$rollbackResult = $import->rollbackImport((string) $importResult['import_id'], true);
test_assert('rollback deletes entries', ($rollbackResult['deleted'] ?? 0) === 3);
test_assert('rollback deletes collection', ($rollbackResult['collection_deleted'] ?? false) === true);
test_assert('rollback collection removed', !is_dir($contentRoot . '/collections/imported-products'));
$secondRollback = $import->rollbackImport((string) $importResult['import_id'], true);
test_assert('rollback cannot run twice', ($secondRollback['ok'] ?? true) === false);

@unlink($historyDir . '/import_history.json');
@rmdir($historyDir);

@rmdir($contentRoot . '/collections');
@rmdir($contentRoot);

@unlink($dataDir . '/catalog_index.json');
@rmdir($entriesDir);
@rmdir($dataDir);

exit(test_finish('Commerce import tests'));
