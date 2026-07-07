<?php
/**
 * P17-M4 — Bundle validation/preview and shipping weight tier tests.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/fixtures/commerce_scenarios.php';

test_reset_failures();
echo "Commerce bundle + shipping tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';
require_once $root . '/plugins/commerce/includes/CommerceBundleSchema.php';
require_once $root . '/plugins/commerce/includes/CommerceSchema.php';
require_once $root . '/plugins/commerce/includes/CatalogIndexer.php';
require_once $root . '/plugins/commerce/includes/CommerceCatalog.php';
require_once $root . '/plugins/commerce/includes/CommerceCartSession.php';
require_once $root . '/plugins/commerce/includes/DiscountEngine.php';

$dataDir = sys_get_temp_dir() . '/birkly-bs-' . getmypid();
@mkdir($dataDir, 0755, true);

$indexer = new CatalogIndexer($dataDir);
$shirtCommerce = commerce_scenario_yoga();
$shirtCommerce['fulfillment'] = 'ship';
$shirtCommerce['weight_grams'] = 400;
$shirtCommerce['purchase_options'][0]['price_cents'] = 2000;

$indexer->indexEntry([
    'id' => 'shirt-bs',
    'slug' => 'shirt-bs',
    'title' => 'Shirt',
    'commerce' => $shirtCommerce,
], 'products');
$indexer->indexEntry([
    'id' => 'course-bs',
    'slug' => 'course-bs',
    'title' => 'Course',
    'commerce' => commerce_scenario_course(),
], 'courses');

$bundleValue = commerce_bundle_normalize_value([
    'items' => [
        ['collection' => 'products', 'entry_id' => 'shirt-bs', 'quantity' => 2, 'title' => 'Shirt'],
        ['collection' => 'courses', 'entry_id' => 'course-bs', 'quantity' => 1, 'title' => 'Course'],
    ],
    'pricing_mode' => 'sum_with_discount',
    'bundle_discount_percent' => 10,
    'currency' => 'eur',
    'billing' => 'one_time',
    'quantity_available' => 20,
    'fulfillment' => 'ship',
]);

$index = $indexer->loadIndex();
$resolved = CommerceBundleSchema::resolveComponents($bundleValue, $index['entries']);
test_assert('bundle components valid', $resolved['valid'] === true);
test_assert('bundle preview price', CommerceBundleSchema::computePriceCents($bundleValue, $resolved['items']) > 0);

$badBundle = commerce_bundle_normalize_value([
    'items' => [['collection' => 'products', 'entry_id' => 'missing', 'quantity' => 1]],
    'pricing_mode' => 'sum',
    'currency' => 'eur',
]);
$badResolved = CommerceBundleSchema::resolveComponents($badBundle, $index['entries']);
test_assert('invalid component flagged', $badResolved['valid'] === false);
test_assert('validation errors for missing', !empty($badResolved['issues']));

file_put_contents($dataDir . '/shipping_zones.json', json_encode([
    'zones' => [[
        'id' => 'weight-eu',
        'name' => 'EU weight',
        'countries' => ['DE'],
        'rate_type' => 'weight_tiers',
        'flat_rate_cents' => 999,
        'weight_tiers' => [
            ['max_grams' => 500, 'rate_cents' => 500],
            ['max_grams' => 1500, 'rate_cents' => 800],
            ['max_grams' => null, 'rate_cents' => 1200],
        ],
    ]],
], JSON_PRETTY_PRINT));

$catalog = new CommerceCatalog($indexer);
$lightLine = [[
    'fulfillment' => 'ship',
    'quantity' => 1,
    'commerce' => ['weight_grams' => 300],
]];
$heavyLine = [[
    'fulfillment' => 'ship',
    'quantity' => 1,
    'commerce' => ['weight_grams' => 2000],
]];
$addr = ['country' => 'DE'];
test_assert('light tier 500', $catalog->calculateShipping($lightLine, $addr) === 500);
test_assert('heavy tier 1200', $catalog->calculateShipping($heavyLine, $addr) === 1200);

$indexer->indexEntry([
    'id' => 'bundle-bs',
    'slug' => 'bundle-bs',
    'title' => 'Starter Bundle',
    'commerce_bundle' => $bundleValue,
    'is_bundle' => true,
], 'bundles');

$cart = new CommerceCartSession();
$cart->addItem('bundles', 'bundle-bs', 1);
$built = $catalog->buildCartLines($cart->getItems());
test_assert('bundle in cart', ($built['ok'] ?? false) === true && !empty($built['lines'][0]['is_bundle']));

exit(test_finish('commerce_bundle_shipping_test'));
