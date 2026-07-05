<?php
/**
 * P6.5-M1 — Commerce fieldtype v2 tests.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/fixtures/commerce_scenarios.php';

test_reset_failures();
echo "Commerce fieldtype v2 tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/fieldtypes/BaseField.php';
require_once $root . '/plugins/commerce/includes/CommerceSchema.php';
require_once $root . '/plugins/commerce/fieldtypes/Commerce/Commerce.php';

use Birkly\FieldTypes\Commerce\Commerce;

$yogaField = new Commerce([
    'name' => 'pricing',
    'value' => commerce_scenario_yoga(),
]);
$yogaProcessed = $yogaField->processValue($yogaField->getValue());
test_assert('yoga fulfillment register', ($yogaProcessed['fulfillment'] ?? '') === 'register');
test_assert('yoga price 2500', ($yogaProcessed['price_cents'] ?? 0) === 2500);
test_assert('yoga qty 20', ($yogaProcessed['quantity_available'] ?? -1) === 20);
test_assert('yoga has purchase_options', count($yogaProcessed['purchase_options'] ?? []) === 1);

$coffeeField = new Commerce([
    'name' => 'pricing',
    'value' => commerce_scenario_coffee_variant(),
]);
$coffeeProcessed = $coffeeField->processValue($coffeeField->getValue());
test_assert('coffee dual options', count($coffeeProcessed['purchase_options'] ?? []) === 2);
test_assert('coffee fulfillment ship', ($coffeeProcessed['fulfillment'] ?? '') === 'ship');
test_assert('coffee subscribe recurring', ($coffeeProcessed['purchase_options'][1]['billing'] ?? '') === 'recurring');

$courseField = new Commerce([
    'name' => 'pricing',
    'value' => commerce_scenario_course(),
]);
$courseProcessed = $courseField->processValue($courseField->getValue());
test_assert('course deliver', ($courseProcessed['fulfillment'] ?? '') === 'deliver');
test_assert('course asset path', strpos($courseProcessed['delivery_asset'] ?? '', 'lighting') !== false);

$legacy = commerce_scenario_legacy_p64();
$migrated = CommerceSchema::migrateLegacyValue($legacy);
test_assert('legacy migrates fulfillment ship', ($migrated['fulfillment'] ?? '') === 'ship');
test_assert('legacy migrates one purchase_option', count($migrated['purchase_options'] ?? []) === 1);
test_assert('legacy price preserved', ($migrated['purchase_options'][0]['price_cents'] ?? 0) === 1999);

$legacyField = new Commerce(['name' => 'commerce']);
$legacyOut = $legacyField->processValue($legacy);
test_assert('processValue migrates legacy billing', ($legacyOut['billing'] ?? '') === 'one_time');
test_assert('processValue migrates legacy price_cents', ($legacyOut['price_cents'] ?? 0) === 1999);

$configField = new Commerce(['name' => 'c']);
test_assert('getConfigUI returns commerce settings', strpos($configField->getConfigUI(0, []), 'commerce_settings') !== false);

exit(test_finish('Commerce fieldtype v2 tests'));
