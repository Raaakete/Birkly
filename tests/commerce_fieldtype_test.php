<?php
/**
 * P6.4-M1 — Commerce fieldtype tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Commerce fieldtype tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/fieldtypes/BaseField.php';
require_once $root . '/plugins/commerce/fieldtypes/Commerce/Commerce.php';

use Birkly\FieldTypes\Commerce\Commerce;

$field = new Commerce([
    'name' => 'commerce',
    'label' => 'Commerce',
    'value' => [
        'price_cents' => 2900,
        'currency' => 'eur',
        'billing' => 'one_time',
        'quantity_available' => 42,
        'sku' => 'SHIRT-BLU',
    ],
]);

test_assert('field type is commerce', $field->getType() === 'commerce');
test_assert('render contains price input', strpos($field->render(), 'commerce-price-input') !== false);
test_assert('validate passes for valid value', count($field->validate($field->getValue())) === 0);

$processed = $field->processValue([
    'price_cents' => 1500,
    'currency' => 'usd',
    'billing' => 'recurring',
    'interval' => 'month',
    'quantity_available' => '',
    'sku' => 'SUB-1',
]);
test_assert('processValue price_cents', ($processed['price_cents'] ?? 0) === 1500);
test_assert('processValue billing recurring', ($processed['billing'] ?? '') === 'recurring');
test_assert('processValue unlimited qty null', $processed['quantity_available'] === null);
test_assert('processValue sku', ($processed['sku'] ?? '') === 'SUB-1');

$invalid = new Commerce(['name' => 'c', 'required' => true]);
$processedBad = $invalid->processValue(['price_cents' => 100, 'billing' => 'invalid']);
test_assert('processValue normalizes invalid billing to one_time', ($processedBad['billing'] ?? '') === 'one_time');

exit(test_finish('Commerce fieldtype tests'));
