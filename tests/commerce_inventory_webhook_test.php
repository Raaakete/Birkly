<?php
/**
 * P14-M2 — Inventory webhook tests (mock payloads, HMAC).
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Commerce inventory webhook tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/includes/CatalogIndexer.php';
require_once $root . '/plugins/commerce/includes/WarehouseStockStore.php';
require_once $root . '/plugins/commerce/includes/CommerceInventoryWebhookService.php';

$dataDir = sys_get_temp_dir() . '/birkly-wh-' . getmypid();
$indexer = new CatalogIndexer($dataDir);
$warehouse = new WarehouseStockStore($dataDir);
$service = new CommerceInventoryWebhookService($indexer, $warehouse);

$secret = 'test-webhook-secret';
$body = json_encode(['sku' => 'WH-SKU-1', 'available' => 7]);
$validHmac = base64_encode(hash_hmac('sha256', $body, $secret, true));

test_assert('shopify HMAC valid', $service->verifyShopifyHmac($body, $validHmac, $secret) === true);
test_assert('shopify HMAC invalid', $service->verifyShopifyHmac($body, 'bad', $secret) === false);

$wooSig = base64_encode(hash_hmac('sha256', $body, $secret, true));
test_assert('woo signature valid', $service->verifyWooSignature($body, $wooSig, $secret) === true);

$indexer->saveIndex([
    'entries' => [
        'products:wh-1' => [
            'entry_id' => 'wh-1',
            'collection_slug' => 'products',
            'sku' => 'WH-SKU-1',
            'quantity_available' => 100,
        ],
    ],
]);

$result = $service->handleShopifyInventoryUpdate(['sku' => 'WH-SKU-1', 'available' => 12]);
test_assert('shopify stock update ok', ($result['ok'] ?? false) === true);
$index = $indexer->loadIndex();
test_assert('catalog qty updated', ($index['entries']['products:wh-1']['quantity_available'] ?? -1) === 12);

$warehouse->set('WH-SKU-2', 5);
$wooResult = $service->handleWooProductUpdated(['sku' => 'WH-SKU-2', 'stock_quantity' => 20, 'manage_stock' => true]);
test_assert('woo stock update ok', ($wooResult['ok'] ?? false) === true);
test_assert('warehouse qty updated', ($warehouse->getBySku('WH-SKU-2')['quantity_on_hand'] ?? 0) === 20);

$unknown = $service->applyStockUpdate('UNKNOWN-SKU', 3, 'shopify');
test_assert('unknown SKU ignored gracefully', ($unknown['ok'] ?? false) === true && !empty($unknown['ignored']));

@unlink($dataDir . '/catalog_index.json');
@unlink($dataDir . '/warehouse_stock.json');

exit(test_finish('commerce_inventory_webhook_test'));
