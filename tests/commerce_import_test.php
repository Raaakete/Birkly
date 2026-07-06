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

$dataDir = sys_get_temp_dir() . '/birkly-commerce-import-' . getmypid();
$entriesDir = $dataDir . '/entries';
@mkdir($entriesDir, 0755, true);

$indexer = new CatalogIndexer($dataDir);
$import = new ImportService($indexer);

$csv = "title,price,currency,sku,quantity\nRed Shirt,29.99,eur,RED-M,10\nBlue Shirt,24.50,usd,BLU-L,5\nGreen Hat,9.99,eur,HAT-1,\n";

$parsed = $import->parseCsv($csv, 'generic');
test_assert('parse ok', ($parsed['ok'] ?? false) === true);
test_assert('three rows parsed', count($parsed['rows'] ?? []) === 3);
test_assert('first row price cents', ($parsed['rows'][0]['commerce']['price_cents'] ?? 0) === 2999);

$wooCsv = "Name,SKU,Regular price,Stock\nWoo Product,WOO-1,19.99,3\n";
$woo = $import->parseCsv($wooCsv, 'woocommerce');
test_assert('woocommerce preset ok', ($woo['ok'] ?? false) === true);
test_assert('woocommerce row title', ($woo['rows'][0]['title'] ?? '') === 'Woo Product');

$sources = ImportService::getImportSources();
test_assert('import sources include shopify', count(array_filter($sources, fn ($s) => $s['id'] === 'shopify')) === 1);
test_assert('import sources include etsy', count(array_filter($sources, fn ($s) => $s['id'] === 'etsy')) === 1);

$etsyCsv = "TITLE,SKU,PRICE,QUANTITY\nHandmade Mug,MUG-1,24.00,5\n";
$etsy = $import->parseCsv($etsyCsv, 'etsy');
test_assert('etsy preset ok', ($etsy['ok'] ?? false) === true);
test_assert('etsy row title', ($etsy['rows'][0]['title'] ?? '') === 'Handmade Mug');

$withCurrency = $import->applyDefaultCurrency($woo['rows'], 'usd');
test_assert('default currency applied', ($withCurrency[0]['commerce']['purchase_options'][0]['currency'] ?? '') === 'usd');

$dry = $import->dryRun($parsed['rows'], 'products');
test_assert('dry run ok', ($dry['ok'] ?? false) === true);
test_assert('dry run count 3', ($dry['count'] ?? 0) === 3);

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

array_map('unlink', glob($entriesDir . '/*.json') ?: []);
@unlink($dataDir . '/catalog_index.json');
@rmdir($entriesDir);
@rmdir($dataDir);

exit(test_finish('Commerce import tests'));
