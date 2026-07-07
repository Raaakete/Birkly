<?php
/**
 * P14-M3 — Warehouse stock tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Commerce warehouse stock tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';

$dataDir = sys_get_temp_dir() . '/birkly-ws-' . getmypid();
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
$warehouse->set('SKU-A', 5, 2);
test_assert('warehouse set qty', ($warehouse->getBySku('SKU-A')['quantity_on_hand'] ?? 0) === 5);
test_assert('hasSufficient true', $warehouse->hasSufficient('SKU-A', 3) === true);
test_assert('hasSufficient false', $warehouse->hasSufficient('SKU-A', 10) === false);

$warehouse->decrement('SKU-A', 2);
test_assert('decrement on hand', ($warehouse->getBySku('SKU-A')['quantity_on_hand'] ?? 0) === 3);
$warehouse->increment('SKU-A', 2);
test_assert('increment restores', ($warehouse->getBySku('SKU-A')['quantity_on_hand'] ?? 0) === 5);

$indexer = new CatalogIndexer($dataDir);
$indexer->saveIndex([
    'entries' => [
        'products:sku-a' => [
            'entry_id' => 'sku-a',
            'collection_slug' => 'products',
            'entry_slug' => 'sku-a',
            'entry_title' => 'SKU A',
            'sku' => 'SKU-A',
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

$cart->clear();
$cart->addItem('products', 'sku-a', 10);
$blocked = $checkout->createPendingOrder('test@example.com');
test_assert('checkout blocked when warehouse insufficient', ($blocked['ok'] ?? true) === false);

$shipAddress = ['line1' => '1 Test St', 'city' => 'Berlin', 'country' => 'DE', 'postal_code' => '10115'];

$cart->clear();
$cart->addItem('products', 'sku-a', 2);
$pending = $checkout->createPendingOrder('test@example.com', ['shipping_address' => $shipAddress]);
test_assert('checkout allowed with sufficient stock', ($pending['ok'] ?? false) === true);
$orderId = $pending['order']['id'] ?? '';
$orders->update($orderId, ['payment_id' => 'pay_wh_test', 'status' => 'pending']);
$checkout->markPaidByPaymentId('pay_wh_test');
test_assert('warehouse decremented on pay', ($warehouse->getBySku('SKU-A')['quantity_on_hand'] ?? -1) === 3);

exit(test_finish('commerce_warehouse_stock_test'));
