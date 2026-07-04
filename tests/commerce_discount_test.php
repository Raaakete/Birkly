<?php
/**
 * P6.4-M3 — Discount engine tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Commerce discount tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';
require_once $root . '/plugins/commerce/includes/DiscountEngine.php';
require_once $root . '/plugins/commerce/includes/CommerceCartSession.php';
require_once $root . '/plugins/commerce/includes/CommerceCatalog.php';

$dataDir = sys_get_temp_dir() . '/birkly-commerce-discount-' . getmypid();
@mkdir($dataDir, 0755, true);

$discounts = new DiscountEngine($dataDir);
$discounts->saveRule([
    'type' => 'percent_off',
    'enabled' => true,
    'percent_off' => 20,
    'target' => 'collection:products',
]);
$discounts->saveRule([
    'type' => 'coupon',
    'enabled' => true,
    'code' => 'SUMMER10',
    'percent_off' => 10,
]);
$discounts->saveRule([
    'type' => 'affiliate',
    'enabled' => true,
    'affiliate_id' => 'partner1',
    'percent_off' => 15,
]);

$item = [
    'entry_id' => 'e1',
    'collection_slug' => 'products',
    'price_cents' => 1000,
    'currency' => 'eur',
];

$base = $discounts->applyToLine($item, 1, null, null);
test_assert('percent_off applied', ($base['effective_unit_cents'] ?? 0) === 800);

$coupon = $discounts->applyToLine($item, 1, 'SUMMER10', null);
test_assert('coupon reduces price', ($coupon['effective_unit_cents'] ?? 1000) < 1000);

$affiliate = $discounts->applyToLine($item, 1, null, 'partner1');
test_assert('affiliate reduces price', ($affiliate['effective_unit_cents'] ?? 1000) < 1000);

require_once $root . '/plugins/commerce/includes/CatalogIndexer.php';
$indexer = new CatalogIndexer($dataDir);
$indexer->indexEntry([
    'id' => 'e1', 'slug' => 'item', 'title' => 'Item',
    'commerce' => ['price_cents' => 1000, 'currency' => 'eur', 'billing' => 'one_time'],
], 'products');

$catalog = new CommerceCatalog($indexer);
$cart = new CommerceCartSession();
if (!isset($_SESSION)) {
    $_SESSION = [];
}
$cart->addItem('products', 'e1', 1);
$cart->setCouponCode('SUMMER10');
$built = $catalog->buildCartLines($cart->getItems(), $discounts, 'SUMMER10', null);
test_assert('checkout line has discount audit', ($built['lines'][0]['discount_amount_cents'] ?? 0) > 0);
test_assert('effective less than base', ($built['lines'][0]['effective_amount_cents'] ?? 0) < ($built['lines'][0]['base_amount_cents'] ?? 0));

@unlink($dataDir . '/discount_rules.json');
@unlink($dataDir . '/catalog_index.json');
@rmdir($dataDir);

exit(test_finish('Commerce discount tests'));
