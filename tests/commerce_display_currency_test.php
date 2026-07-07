<?php
/**
 * P17-M2 — Display currency and manual FX rate tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Commerce display currency tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';
require_once $root . '/core/plugins.php';

global $birkly_plugins;
if (!isset($birkly_plugins['commerce'])) {
    $birkly_plugins['commerce'] = ['id' => 'commerce', 'active' => true, 'settings' => []];
}
$birkly_plugins['commerce']['settings']['commerce_default_currency'] = 'eur';
$birkly_plugins['commerce']['settings']['commerce_display_currency'] = 'usd';
$birkly_plugins['commerce']['settings']['commerce_fx_rates'] = json_encode(['eur' => 1, 'usd' => 1.08, 'gbp' => 0.86]);

test_assert('display currency usd', commerce_get_display_currency() === 'usd');
test_assert('fx rates parsed', (commerce_get_fx_rates()['usd'] ?? 0) === 1.08);

$converted = commerce_convert_display_cents(10000, 'eur');
test_assert('eur to usd display cents', $converted === 10800);

$formatted = commerce_format_price_display(10000, 'eur');
test_assert('format includes USD', str_contains($formatted, 'USD') || str_contains($formatted, '$'));

$dataDir = sys_get_temp_dir() . '/birkly-dc-' . getmypid();
@mkdir($dataDir . '/orders', 0755, true);

require_once $root . '/plugins/commerce/includes/CatalogIndexer.php';
require_once $root . '/plugins/commerce/includes/CommerceCatalog.php';
require_once $root . '/plugins/commerce/includes/CommerceCartSession.php';
require_once $root . '/plugins/commerce/includes/CommerceOrderStore.php';
require_once $root . '/plugins/commerce/includes/DiscountEngine.php';
require_once $root . '/plugins/commerce/includes/CommerceSchema.php';
require_once $root . '/plugins/commerce/includes/PurchaseMappingService.php';
require_once $root . '/plugins/commerce/includes/PostPurchaseRouter.php';
require_once $root . '/plugins/commerce/includes/EventRegistrationStore.php';
require_once $root . '/plugins/commerce/includes/DigitalDeliveryStore.php';
require_once $root . '/plugins/commerce/includes/CommerceCheckoutService.php';

$indexer = new CatalogIndexer($dataDir);
$indexer->saveIndex([
    'entries' => [
        'products:eur-only' => [
            'entry_id' => 'eur-only',
            'collection_slug' => 'products',
            'entry_slug' => 'eur-only',
            'entry_title' => 'EUR Product',
            'sku' => 'EUR-ONLY',
            'commerce' => commerce_normalize_value([
                'fulfillment' => 'ship',
                'purchase_options' => [['id' => 'default', 'billing' => 'one_time', 'price_cents' => 2000, 'currency' => 'eur']],
                'quantity_available' => 5,
            ]),
            'price_cents' => 2000,
            'currency' => 'eur',
            'billing' => 'one_time',
            'fulfillment' => 'ship',
            'quantity_available' => 5,
        ],
    ],
]);

$catalog = new CommerceCatalog($indexer);
$cart = new CommerceCartSession();
$cart->clear();
$cart->addItem('products', 'eur-only', 1);
$built = $catalog->buildCartLines($cart->getItems());
test_assert('cart still charges eur', strtolower($built['currency'] ?? '') === 'eur');
test_assert('cart total unchanged 2000', ($built['total_cents'] ?? 0) === 2000);

$orders = new CommerceOrderStore($dataDir . '/orders');
$checkout = new CommerceCheckoutService($orders, $catalog, $cart, $indexer);
$shipAddress = ['line1' => '1 St', 'city' => 'Berlin', 'country' => 'DE', 'postal_code' => '10115'];
$pending = $checkout->createPendingOrder('dc@example.com', ['shipping_address' => $shipAddress]);
test_assert('pending order currency eur', strtolower($pending['order']['currency'] ?? '') === 'eur');
test_assert('pending total still 2000', (int) ($pending['order']['total_cents'] ?? 0) === 2000);

exit(test_finish('commerce_display_currency_test'));
