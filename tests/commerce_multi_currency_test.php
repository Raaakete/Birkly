<?php
/**
 * P14-M1 — Multi-currency checkout tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Commerce multi-currency tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';
require_once $root . '/core/plugins.php';

$dataDir = sys_get_temp_dir() . '/birkly-mc-' . getmypid();
@mkdir($dataDir . '/orders', 0755, true);

require_once $root . '/plugins/commerce/includes/CatalogIndexer.php';
require_once $root . '/plugins/commerce/includes/CommerceCatalog.php';
require_once $root . '/plugins/commerce/includes/CommerceCartSession.php';
require_once $root . '/plugins/commerce/includes/CommerceOrderStore.php';
require_once $root . '/plugins/commerce/includes/DiscountEngine.php';
require_once $root . '/plugins/commerce/includes/CommerceSchema.php';
require_once $root . '/plugins/commerce/includes/GiftCardStore.php';
require_once $root . '/plugins/commerce/includes/CommerceAnalyticsService.php';

global $birkly_plugins;
if (!isset($birkly_plugins['commerce'])) {
    $birkly_plugins['commerce'] = ['id' => 'commerce', 'active' => true, 'settings' => []];
}
$birkly_plugins['commerce']['settings']['commerce_default_currency'] = 'eur';

$indexer = new CatalogIndexer($dataDir);
$catalog = new CommerceCatalog($indexer);
$cart = new CommerceCartSession();

function mc_index_product(CatalogIndexer $indexer, string $slug, string $currency, int $cents): void
{
    $commerce = commerce_normalize_value([
        'fulfillment' => 'ship',
        'purchase_options' => [[
            'id' => 'default',
            'label' => 'Default',
            'billing' => 'one_time',
            'price_cents' => $cents,
            'currency' => $currency,
        ]],
        'quantity_available' => 10,
        'sku' => strtoupper($slug),
    ]);
    $indexer->saveIndex([
        'entries' => [
            'products:' . $slug => [
                'entry_id' => $slug,
                'collection_slug' => 'products',
                'entry_slug' => $slug,
                'entry_title' => ucfirst($slug),
                'sku' => strtoupper($slug),
                'commerce' => $commerce,
                'price_cents' => $cents,
                'currency' => $currency,
                'billing' => 'one_time',
                'fulfillment' => 'ship',
                'quantity_available' => 10,
            ],
        ],
    ]);
}

mc_index_product($indexer, 'eur-item', 'eur', 1000);
$index = $indexer->loadIndex();
$index['entries']['products:usd-item'] = [
    'entry_id' => 'usd-item',
    'collection_slug' => 'products',
    'entry_slug' => 'usd-item',
    'entry_title' => 'Usd-item',
    'sku' => 'USD-ITEM',
    'commerce' => commerce_normalize_value([
        'fulfillment' => 'ship',
        'purchase_options' => [['id' => 'default', 'billing' => 'one_time', 'price_cents' => 2000, 'currency' => 'usd']],
        'quantity_available' => 10,
    ]),
    'price_cents' => 2000,
    'currency' => 'usd',
    'billing' => 'one_time',
    'fulfillment' => 'ship',
    'quantity_available' => 10,
];
$indexer->saveIndex($index);

$cart->clear();
$cart->addItem('products', 'eur-item', 1);
$eurBuilt = $catalog->buildCartLines($cart->getItems());
test_assert('EUR cart ok', ($eurBuilt['ok'] ?? false) === true);
test_assert('EUR cart currency', strtolower($eurBuilt['currency'] ?? '') === 'eur');

$cart->clear();
$cart->addItem('products', 'usd-item', 1);
$usdBuilt = $catalog->buildCartLines($cart->getItems());
test_assert('USD cart ok', ($usdBuilt['ok'] ?? false) === true);
test_assert('USD cart currency', strtolower($usdBuilt['currency'] ?? '') === 'usd');

$cart->clear();
$cart->addItem('products', 'usd-item', 1);
$cart->addItem('products', 'eur-item', 1);
$mixed = $catalog->buildCartLines($cart->getItems());
test_assert('mixed currency cart blocked', ($mixed['ok'] ?? true) === false);

$orders = new CommerceOrderStore($dataDir . '/orders');
$orders->create(['status' => 'paid', 'total_cents' => 1000, 'currency' => 'eur', 'lines' => []]);
$orders->create(['status' => 'paid', 'total_cents' => 2000, 'currency' => 'usd', 'lines' => []]);
$summary = (new CommerceAnalyticsService($orders))->getSummary();
test_assert('analytics no cross-currency revenue sum', ($summary['revenue_cents'] ?? -1) === 0);
test_assert('analytics EUR bucket', ($summary['revenue_by_currency']['eur'] ?? 0) === 1000);
test_assert('analytics USD bucket', ($summary['revenue_by_currency']['usd'] ?? 0) === 2000);

$csv = (new CommerceAnalyticsService($orders))->exportOrdersCsv();
test_assert('analytics CSV currency column', strpos($csv, 'currency') !== false && strpos($csv, 'usd') !== false);

$giftCards = new GiftCardStore($dataDir . '/gift_cards');
@mkdir($dataDir . '/gift_cards', 0755, true);
@mkdir($dataDir . '/gift_cards', 0755, true);
$giftCards->issue(['code' => 'GC-EUR', 'balance_cents' => 500, 'currency' => 'eur']);
$discounts = new DiscountEngine($dataDir);
$gcMismatch = $discounts->applyGiftCardTender(1000, 'GC-EUR', $giftCards, 'usd');
test_assert('gift card currency mismatch rejected', ($gcMismatch['ok'] ?? true) === false);
$gcMatch = $discounts->applyGiftCardTender(1000, 'GC-EUR', $giftCards, 'eur');
test_assert('gift card same currency accepted', ($gcMatch['ok'] ?? false) === true);

exit(test_finish('commerce_multi_currency_test'));
