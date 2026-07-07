<?php
/**
 * P17-M1 — Multi-warehouse stock and checkout allocation tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Commerce multi-warehouse tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';

$dataDir = sys_get_temp_dir() . '/birkly-mw-' . getmypid();
@mkdir($dataDir . '/orders', 0755, true);

require_once $root . '/plugins/commerce/includes/WarehouseStockStore.php';
require_once $root . '/plugins/commerce/includes/CatalogIndexer.php';
require_once $root . '/plugins/commerce/includes/CommerceCatalog.php';
require_once $root . '/plugins/commerce/includes/CommerceCartSession.php';
require_once $root . '/plugins/commerce/includes/CommerceOrderStore.php';
require_once $root . '/plugins/commerce/includes/DiscountEngine.php';
require_once $root . '/plugins/commerce/includes/PurchaseMappingService.php';
require_once $root . '/plugins/commerce/includes/FulfillmentAdapterInterface.php';
require_once $root . '/plugins/commerce/includes/ManualFulfillmentAdapter.php';
require_once $root . '/plugins/commerce/includes/ShipStationStubAdapter.php';
require_once $root . '/plugins/commerce/includes/PostPurchaseRouter.php';
require_once $root . '/plugins/commerce/includes/EventRegistrationStore.php';
require_once $root . '/plugins/commerce/includes/DigitalDeliveryStore.php';
require_once $root . '/plugins/commerce/includes/CommerceCheckoutService.php';
require_once $root . '/plugins/commerce/includes/CommerceSchema.php';

$warehouse = new WarehouseStockStore($dataDir);
$whEast = $warehouse->createWarehouse('East', 0);
$whWest = $warehouse->createWarehouse('West', 1);
test_assert('two warehouses', count($warehouse->listWarehouses()) === 3);

$warehouse->set('SKU-MW', 2, 0, $whEast['id']);
$warehouse->set('SKU-MW', 10, 0, $whWest['id']);
test_assert('west has stock for qty 5', $warehouse->allocateWarehouse('SKU-MW', 5) === $whWest['id']);

$warehouse->updateWarehouse($whEast['id'], 'East', 0);
$warehouse->updateWarehouse($whWest['id'], 'West', 1);
test_assert('east wins for qty 2 after reorder', $warehouse->allocateWarehouse('SKU-MW', 2) === $whEast['id']);

$transfer = $warehouse->transfer('SKU-MW', 2, $whWest['id'], $whEast['id']);
test_assert('transfer ok', ($transfer['ok'] ?? false) === true);

$indexer = new CatalogIndexer($dataDir);
$indexer->saveIndex([
    'entries' => [
        'products:mw-item' => [
            'entry_id' => 'mw-item',
            'collection_slug' => 'products',
            'entry_slug' => 'mw-item',
            'entry_title' => 'MW Item',
            'sku' => 'SKU-MW',
            'commerce' => commerce_normalize_value([
                'fulfillment' => 'ship',
                'purchase_options' => [['id' => 'default', 'billing' => 'one_time', 'price_cents' => 500, 'currency' => 'eur']],
                'quantity_available' => 100,
            ]),
            'price_cents' => 500,
            'currency' => 'eur',
            'billing' => 'one_time',
            'fulfillment' => 'ship',
            'quantity_available' => 100,
        ],
    ],
]);

$orders = new CommerceOrderStore($dataDir . '/orders');
$catalog = new CommerceCatalog($indexer);
$cart = new CommerceCartSession();
$checkout = new CommerceCheckoutService($orders, $catalog, $cart, $indexer, new DiscountEngine($dataDir), null, null, null, null, null, $warehouse);
$shipAddress = ['line1' => '1 Test St', 'city' => 'Berlin', 'country' => 'DE', 'postal_code' => '10115'];

$cart->clear();
$cart->addItem('products', 'mw-item', 3);
$pending = $checkout->createPendingOrder('mw@example.com', ['shipping_address' => $shipAddress]);
test_assert('checkout allocates from east', ($pending['ok'] ?? false) === true);
test_assert('warehouse_id on line', ($pending['order']['lines'][0]['warehouse_id'] ?? '') === $whEast['id']);

$orderId = $pending['order']['id'] ?? '';
$orders->update($orderId, ['payment_id' => 'pay_mw_test', 'status' => 'pending']);
$checkout->markPaidByPaymentId('pay_mw_test');
$eastAfter = null;
foreach ($warehouse->listAll($whEast['id']) as $row) {
    if (($row['sku'] ?? '') === 'SKU-MW') {
        $eastAfter = $row;
    }
}
test_assert('east decremented on pay', ($eastAfter['quantity_on_hand'] ?? -1) === 1);

exit(test_finish('commerce_multi_warehouse_test'));
