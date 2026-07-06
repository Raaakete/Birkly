<?php
/**
 * Commerce fieldtype profiles — plugin admin, dynamic registration.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Commerce fieldtype profile tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';
require_once $root . '/fieldtypes/BaseField.php';
require_once $root . '/plugins/commerce/includes/CommerceSchema.php';
require_once $root . '/plugins/commerce/includes/CommerceFieldtypeProfileStore.php';
require_once $root . '/plugins/commerce/fieldtypes/Commerce/Commerce.php';

$dataDir = sys_get_temp_dir() . '/birkly-commerce-profiles-' . getmypid();
@mkdir($dataDir, 0755, true);

$store = new CommerceFieldtypeProfileStore($dataDir);
$default = $store->getProfile('default');
test_assert('default profile exists', ($default['type_key'] ?? '') === 'commerce');
test_assert('default profile has interval options', !empty($default['settings']['interval_options']));

$events = $store->saveProfile([
    'name' => 'Events & Classes',
    'slug' => 'events',
    'settings' => [
        'default_fulfillment' => 'register',
        'enabled_fulfillments' => ['register'],
        'enable_weight' => false,
        'interval_options' => [
            ['value' => 'month', 'label' => 'Monthly'],
        ],
        'custom_fields' => [
            ['key' => 'room', 'label' => 'Room', 'type' => 'text'],
        ],
    ],
]);
test_assert('events profile type key', ($events['type_key'] ?? '') === 'commerce_events');
test_assert('events settings normalized', in_array('register', $events['settings']['enabled_fulfillments'], true));

$customInterval = $store->saveProfile([
    'name' => 'Subscription Coffee',
    'slug' => 'coffee_sub',
    'settings' => [
        'interval_options' => [
            ['value' => '3_days', 'label' => 'Every 3 days'],
            ['value' => '2_weeks', 'label' => 'Every 2 weeks'],
        ],
    ],
]);
$intervalValues = CommerceFieldtypeProfileStore::intervalValues($customInterval['settings']);
test_assert('custom interval 3_days saved', in_array('3_days', $intervalValues, true));
test_assert('custom interval 2_weeks saved', in_array('2_weeks', $intervalValues, true));

$settingsForEvents = $store->getSettingsForTypeKey('commerce_events');
test_assert('lookup by type key', ($settingsForEvents['default_fulfillment'] ?? '') === 'register');

use Birkly\FieldTypes\Commerce\Commerce;

$configField = new Commerce([
    'name' => 'pricing',
    'field_type' => 'commerce_events',
]);
$configUi = $configField->getConfigUI(0, []);
test_assert('getConfigUI points to plugin admin', strpos($configUi, 'Commerce → Fieldtypes') !== false);
test_assert('getConfigUI no collection interval checkboxes', strpos($configUi, 'interval_options') === false);

$eventsField = new Commerce([
    'name' => 'pricing',
    'field_type' => 'commerce_events',
    'commerce_settings' => $events['settings'],
    'value' => [
        'fulfillment' => 'register',
        'purchase_options' => [[
            'id' => 'ticket',
            'label' => 'Ticket',
            'billing' => 'one_time',
            'price_cents' => 2500,
            'currency' => 'eur',
        ]],
        'quantity_available' => 20,
    ],
]);
$render = $eventsField->render();
test_assert('events profile render has register qty', strpos($render, 'Quantity available') !== false);

$eventsFieldNoWeight = new Commerce([
    'name' => 'pricing',
    'commerce_settings' => array_merge($events['settings'], ['enable_weight' => false]),
    'value' => $eventsField->getValue(),
]);
test_assert('profile can hide weight panel', strpos($eventsFieldNoWeight->render(), 'Weight (grams') === false);

$normalized = commerce_normalize_value([
    'fulfillment' => 'none',
    'purchase_options' => [[
        'id' => 'sub',
        'label' => 'Subscribe',
        'billing' => 'recurring',
        'interval' => '3_days',
        'price_cents' => 999,
        'currency' => 'eur',
    ]],
]);
test_assert('schema accepts custom interval 3_days', ($normalized['purchase_options'][0]['interval'] ?? '') === '3_days');

test_assert('delete non-default profile', $store->deleteProfile($events['id']) === true);
test_assert('cannot delete default', $store->deleteProfile('default') === false);

exit(test_finish('Commerce fieldtype profile tests'));
