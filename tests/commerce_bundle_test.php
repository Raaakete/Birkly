<?php
/**
 * P6.7 — Commerce bundle fieldtype, catalog, cart, and fulfillment tests.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/fixtures/commerce_scenarios.php';

test_reset_failures();
echo "Commerce bundle tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/fieldtypes/BaseField.php';
require_once $root . '/plugins/commerce/init.php';
require_once $root . '/plugins/commerce/includes/CommerceBundleSchema.php';
require_once $root . '/plugins/commerce/includes/CommerceSchema.php';
require_once $root . '/plugins/commerce/includes/CatalogIndexer.php';
require_once $root . '/plugins/commerce/includes/CommerceCatalog.php';
require_once $root . '/plugins/commerce/includes/DiscountEngine.php';
require_once $root . '/plugins/commerce/includes/CommerceCartSession.php';
require_once $root . '/plugins/commerce/includes/FulfillmentAdapterInterface.php';
require_once $root . '/plugins/commerce/includes/ManualFulfillmentAdapter.php';
require_once $root . '/plugins/commerce/includes/EventRegistrationStore.php';
require_once $root . '/plugins/commerce/includes/DigitalDeliveryStore.php';
require_once $root . '/plugins/commerce/includes/PurchaseMappingService.php';
require_once $root . '/plugins/commerce/includes/PostPurchaseRouter.php';
require_once $root . '/plugins/commerce/fieldtypes/CommerceBundle/CommerceBundle.php';

use Birkly\FieldTypes\CommerceBundle\CommerceBundle;

$dataDir = sys_get_temp_dir() . '/birkly-commerce-bundle-' . getmypid();
@mkdir($dataDir, 0755, true);
@mkdir($dataDir . '/fulfillment_exports', 0755, true);

$indexer = new CatalogIndexer($dataDir);
$shirtCommerce = commerce_scenario_yoga();
$shirtCommerce['fulfillment'] = 'ship';
$shirtCommerce['purchase_options'][0]['price_cents'] = 2000;

$indexer->indexEntry([
    'id' => 'shirt-1',
    'slug' => 'blue-shirt',
    'title' => 'Blue Shirt',
    'commerce' => $shirtCommerce,
], 'products');
$indexer->indexEntry([
    'id' => 'course-1',
    'slug' => 'course-1',
    'title' => 'Lighting Course',
    'commerce' => commerce_scenario_course(),
], 'courses');

$bundleValue = commerce_bundle_normalize_value([
    'items' => [
        ['collection' => 'products', 'entry_id' => 'shirt-1', 'quantity' => 1, 'title' => 'Blue Shirt'],
        ['collection' => 'courses', 'entry_id' => 'course-1', 'quantity' => 1, 'title' => 'Lighting Course'],
    ],
    'pricing_mode' => 'sum_with_discount',
    'bundle_discount_percent' => 10,
    'currency' => 'eur',
    'billing' => 'one_time',
    'quantity_available' => 50,
    'fulfillment' => 'ship',
]);

$field = new CommerceBundle(['name' => 'bundle', 'value' => $bundleValue]);
$processed = $field->processValue($bundleValue);
test_assert('fieldtype normalizes items', count($processed['items']) === 2);
test_assert('fieldtype pricing mode', ($processed['pricing_mode'] ?? '') === 'sum_with_discount');
test_assert('fieldtype billing one_time', ($processed['billing'] ?? '') === 'one_time');
test_assert('validate passes', empty($field->validate($processed)));

$sumCents = 2000 + 8900;
$expectedDiscounted = (int) round($sumCents * 0.9);
$index = $indexer->loadIndex();
$resolved = CommerceBundleSchema::resolveComponents($processed, $index['entries']);
test_assert('components resolve valid', $resolved['valid'] === true);
test_assert('computed bundle price with discount', CommerceBundleSchema::computePriceCents($processed, $resolved['items']) === $expectedDiscounted);

$indexer->indexEntry([
    'id' => 'bundle-1',
    'slug' => 'starter-pack',
    'title' => 'Starter Pack',
    'bundle' => $processed,
], 'bundles');

$index = $indexer->loadIndex();
$bundleKey = 'bundles:bundle-1';
test_assert('bundle indexed', isset($index['entries'][$bundleKey]));
test_assert('bundle is_bundle flag', ($index['entries'][$bundleKey]['is_bundle'] ?? false) === true);
test_assert('bundle indexed price', ($index['entries'][$bundleKey]['price_cents'] ?? 0) === $expectedDiscounted);

$catalog = new CommerceCatalog($indexer);
$cart = new CommerceCartSession();
$cart->addItem('bundles', 'bundle-1', 1);
$built = $catalog->buildCartLines($cart->getItems());
test_assert('cart builds bundle line', ($built['ok'] ?? false) === true);
test_assert('bundle line present', count($built['lines'] ?? []) === 1);
test_assert('bundle line is_bundle', ($built['lines'][0]['is_bundle'] ?? false) === true);
test_assert('bundle line total', ($built['lines'][0]['line_total_cents'] ?? 0) === $expectedDiscounted);
test_assert('bundle expands components', count($built['lines'][0]['components'] ?? []) === 2);

$fixedBundle = commerce_bundle_normalize_value([
    'items' => [['collection' => 'products', 'entry_id' => 'shirt-1', 'quantity' => 1]],
    'pricing_mode' => 'fixed',
    'fixed_price_cents' => 4999,
    'currency' => 'eur',
    'billing' => 'one_time',
    'fulfillment' => 'ship',
]);
$fixedResolved = CommerceBundleSchema::resolveComponents($fixedBundle, $index['entries']);
test_assert('fixed price mode', CommerceBundleSchema::computePriceCents($fixedBundle, $fixedResolved['items']) === 4999);

$indexer->removeEntry('shirt-1', 'products');
$broken = $catalog->buildCartLines([['collection' => 'bundles', 'entry_id' => 'bundle-1', 'quantity' => 1]]);
test_assert('missing component blocks checkout', ($broken['ok'] ?? true) === false);

$indexer->indexEntry([
    'id' => 'shirt-1',
    'slug' => 'blue-shirt',
    'title' => 'Blue Shirt',
    'commerce' => $shirtCommerce,
], 'products');

$router = new PostPurchaseRouter($dataDir);
$shipBundleLine = $built['lines'][0];
$shipBundleLine['components'] = array_map(function ($comp) {
    if (($comp['collection'] ?? '') === 'products') {
        $comp['fulfillment'] = 'ship';
    }
    return $comp;
}, $shipBundleLine['components']);

$order = [
    'id' => 'ord_bundle',
    'status' => 'paid',
    'customer_email' => 'buyer@example.com',
    'shipping_address' => ['line1' => '1 St', 'city' => 'Berlin', 'country' => 'DE'],
    'lines' => [$shipBundleLine],
];
$routeResult = $router->route($order);
test_assert('bundle routes expanded', ($routeResult[0]['ok'] ?? false) === true);
test_assert('bundle expansion count', count($routeResult[0]['components'] ?? []) === 2);

test_assert('getType commerce_bundle', $field->getType() === 'commerce_bundle');
test_assert('render includes picker', strpos($field->render(), 'commerce-bundle-add-btn') !== false);

$recurringBundle = commerce_bundle_normalize_value(array_merge($processed, ['billing' => 'recurring', 'interval' => 'month']));
$recurringErrors = CommerceBundleSchema::validate($recurringBundle);
test_assert('recurring bundle rejected in validate', !empty($recurringErrors));

@unlink($dataDir . '/catalog_index.json');
@unlink($dataDir . '/fulfillment_state.json');
array_map('unlink', glob($dataDir . '/fulfillment_exports/*.json') ?: []);
@rmdir($dataDir . '/fulfillment_exports');
@rmdir($dataDir);

exit(test_finish('Commerce bundle tests'));
