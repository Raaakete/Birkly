<?php
/**
 * P6.4-M1 — Catalog indexer tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Commerce index tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';
require_once $root . '/plugins/commerce/includes/CatalogIndexer.php';

$dataDir = sys_get_temp_dir() . '/birkly-commerce-index-' . getmypid();
@mkdir($dataDir, 0755, true);

$indexer = new CatalogIndexer($dataDir);

$entry = [
    'id' => 'ent-1',
    'slug' => 'blue-shirt',
    'title' => 'Blue Shirt',
    'commerce' => [
        'price_cents' => 2900,
        'currency' => 'eur',
        'billing' => 'one_time',
        'quantity_available' => 10,
        'sku' => 'SHIRT-BLU',
    ],
];

$indexer->indexEntry($entry, 'products');
$index = $indexer->loadIndex();
$key = 'products:ent-1';

test_assert('entry indexed', isset($index['entries'][$key]));
test_assert('price round-trip', ($index['entries'][$key]['price_cents'] ?? 0) === 2900);
test_assert('quantity round-trip', ($index['entries'][$key]['quantity_available'] ?? -1) === 10);
test_assert('sku round-trip', ($index['entries'][$key]['sku'] ?? '') === 'SHIRT-BLU');

test_assert('reserve quantity ok', $indexer->reserveQuantity('products', 'ent-1', 5) === true);
test_assert('decrement quantity', $indexer->decrementQuantity('products', 'ent-1', 3) === true);
$index = $indexer->loadIndex();
test_assert('quantity after decrement', ($index['entries'][$key]['quantity_available'] ?? 0) === 7);

$entryNoCommerce = ['id' => 'ent-1', 'title' => 'No commerce field'];
$indexer->indexEntry($entryNoCommerce, 'products');
$index = $indexer->loadIndex();
test_assert('entry without commerce removed from index', !isset($index['entries']['products:ent-1']));

@unlink($dataDir . '/catalog_index.json');
@rmdir($dataDir);

exit(test_finish('Commerce index tests'));
