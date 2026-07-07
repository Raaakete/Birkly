<?php
/**
 * P11-M6 — Shop/Events → Commerce migration wizard tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Commerce migration wizard tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';
require_once $root . '/plugins/commerce/includes/CatalogIndexer.php';
require_once $root . '/plugins/commerce/includes/ImportCollectionHelper.php';
require_once $root . '/plugins/commerce/includes/MigrationHistoryStore.php';
require_once $root . '/plugins/commerce/includes/LegacyMigrationService.php';

$dataDir = sys_get_temp_dir() . '/birkly-commerce-migration-' . getmypid();
$contentRoot = sys_get_temp_dir() . '/birkly-commerce-migration-content-' . getmypid();
$cmsRoot = sys_get_temp_dir() . '/birkly-commerce-migration-cms-' . getmypid();
@mkdir($dataDir, 0755, true);
@mkdir($contentRoot . '/collections', 0755, true);
@mkdir($cmsRoot . '/plugins/events', 0755, true);

$indexer = new CatalogIndexer($dataDir);
$history = new MigrationHistoryStore($dataDir);
$migration = new LegacyMigrationService($indexer, $contentRoot, $cmsRoot, $history);

$shopDir = $contentRoot . '/collections/shop_products';
@mkdir($shopDir . '/entries', 0755, true);
file_put_contents($shopDir . '/schema.json', json_encode([
    'fields' => [
        ['name' => 'title', 'type' => 'text'],
        ['name' => 'slug', 'type' => 'text'],
        ['name' => 'price', 'type' => 'number'],
        ['name' => 'currency', 'type' => 'text'],
        ['name' => 'sku', 'type' => 'text'],
        ['name' => 'stock', 'type' => 'number'],
        ['name' => 'description', 'type' => 'textarea'],
    ],
], JSON_PRETTY_PRINT));
file_put_contents($shopDir . '/entries/demo-product-1.json', json_encode([
    'id' => 'demo-product-1',
    'title' => 'Demo T-Shirt',
    'slug' => 'demo-t-shirt',
    'price' => 29.99,
    'currency' => 'usd',
    'sku' => 'DEMO-TSHIRT',
    'stock' => 100,
    'description' => 'Legacy product',
    'status' => 'published',
], JSON_PRETTY_PRINT));

$eventsDir = $contentRoot . '/collections/events';
@mkdir($eventsDir . '/entries', 0755, true);
file_put_contents($eventsDir . '/schema.json', json_encode([
    'fields' => [
        ['name' => 'title', 'type' => 'text'],
        ['name' => 'slug', 'type' => 'text'],
        ['name' => 'start', 'type' => 'datetime'],
        ['name' => 'ticket_types', 'type' => 'textarea'],
        ['name' => 'capacity', 'type' => 'number'],
    ],
], JSON_PRETTY_PRINT));
file_put_contents($eventsDir . '/entries/demo-event-1.json', json_encode([
    'id' => 'demo-event-1',
    'title' => 'Community Meetup',
    'slug' => 'community-meetup',
    'start' => '2026-08-15T18:00:00',
    'capacity' => 50,
    'ticket_types' => json_encode([
        ['id' => 'vip', 'name' => 'VIP Ticket', 'price' => 49.99, 'currency' => 'usd'],
    ]),
    'status' => 'published',
], JSON_PRETTY_PRINT));

$scan = $migration->scan();
test_assert('scan detects shopping cart collection', ($scan['shopping_cart']['detected'] ?? false) === true);
test_assert('scan shopping cart entry count', ($scan['shopping_cart']['entry_count'] ?? 0) === 1);
test_assert('scan detects events collection', ($scan['events']['detected'] ?? false) === true);
test_assert('scan events entry count', ($scan['events']['entry_count'] ?? 0) === 1);
test_assert('scan detects events plugin dir', ($scan['plugin_dirs']['events'] ?? false) === true);

$preview = $migration->preview([
    'sources' => ['shopping_cart', 'events'],
    'products_collection' => 'products',
    'events_collection' => 'events-commerce',
    'create_collections' => true,
]);
test_assert('preview ok', ($preview['ok'] ?? false) === true);
test_assert('preview two rows', ($preview['count'] ?? 0) === 2);
test_assert('preview create count', ($preview['create_count'] ?? 0) === 2);

$productRow = null;
foreach ($preview['preview'] as $row) {
    if (($row['source'] ?? '') === 'shopping_cart') {
        $productRow = $row;
    }
}
test_assert('product preview fulfillment ship', ($productRow['fulfillment'] ?? '') === 'ship');
test_assert('product mapped price cents', ($productRow['mapped_entry']['pricing']['purchase_options'][0]['price_cents'] ?? 0) === 2999);

$import = $migration->import([
    'sources' => ['shopping_cart', 'events'],
    'products_collection' => 'products',
    'events_collection' => 'events-commerce',
    'create_collections' => true,
]);
test_assert('import ok', ($import['ok'] ?? false) === true);
test_assert('imported two entries', ($import['imported_count'] ?? 0) === 2);
test_assert('import id present', !empty($import['import_id']));

$productFile = glob($contentRoot . '/collections/products/entries/*.json') ?: [];
$eventFile = glob($contentRoot . '/collections/events-commerce/entries/*.json') ?: [];
test_assert('products collection created', count($productFile) === 1);
test_assert('events-commerce collection created', count($eventFile) === 1);

$productEntry = json_decode((string) file_get_contents($productFile[0]), true);
test_assert('product entry has pricing field', isset($productEntry['pricing']['purchase_options']));
test_assert('product sku preserved', ($productEntry['pricing']['sku'] ?? '') === 'DEMO-TSHIRT');

$eventEntry = json_decode((string) file_get_contents($eventFile[0]), true);
test_assert('event entry register fulfillment', ($eventEntry['pricing']['fulfillment'] ?? '') === 'register');
test_assert('event ticket option imported', ($eventEntry['pricing']['purchase_options'][0]['id'] ?? '') === 'vip');

$index = $indexer->loadIndex();
test_assert('catalog indexed migrated entries', count($index['entries'] ?? []) >= 2);

$secondImport = $migration->import([
    'sources' => ['shopping_cart', 'events'],
    'products_collection' => 'products',
    'events_collection' => 'events-commerce',
    'create_collections' => true,
]);
test_assert('idempotent re-import skips all', ($secondImport['imported_count'] ?? -1) === 0);

$rollback = $migration->rollback((string) $import['import_id']);
test_assert('rollback ok', ($rollback['ok'] ?? false) === true);
test_assert('rollback deletes entries', ($rollback['deleted'] ?? 0) === 2);
test_assert('product files removed', count(glob($contentRoot . '/collections/products/entries/*.json') ?: []) === 0);

$history = $migration->listHistory();
test_assert('history lists run', count($history) >= 1);
test_assert('history run rolled back', !empty($history[0]['rolled_back_at']));

@unlink($dataDir . '/migration_history.json');
@unlink($dataDir . '/catalog_index.json');
@rmdir($dataDir);

exit(test_finish('Commerce migration wizard tests'));
