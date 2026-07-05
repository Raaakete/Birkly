<?php
/**
 * P6.5-M3 — PostPurchaseRouter tests (all fulfillment types + idempotency).
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/fixtures/commerce_scenarios.php';

test_reset_failures();
echo "Commerce fulfillment router tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';
require_once $root . '/plugins/commerce/includes/CommerceSchema.php';
require_once $root . '/plugins/commerce/includes/FulfillmentAdapterInterface.php';
require_once $root . '/plugins/commerce/includes/ManualFulfillmentAdapter.php';
require_once $root . '/plugins/commerce/includes/ShipStationStubAdapter.php';
require_once $root . '/plugins/commerce/includes/EventRegistrationStore.php';
require_once $root . '/plugins/commerce/includes/DigitalDeliveryStore.php';
require_once $root . '/plugins/commerce/includes/PurchaseMappingService.php';
require_once $root . '/plugins/commerce/includes/PostPurchaseRouter.php';

$dataDir = sys_get_temp_dir() . '/birkly-commerce-router-' . getmypid();
@mkdir($dataDir, 0755, true);
@mkdir($dataDir . '/fulfillment_exports', 0755, true);

$router = new PostPurchaseRouter($dataDir);
$registrations = new EventRegistrationStore($dataDir);
$digital = new DigitalDeliveryStore($dataDir);

$automationEvents = [];
if (!function_exists('enqueue_automation')) {
    function enqueue_automation($event, $ctx) {
        global $automationEvents;
        $automationEvents[] = $event;
    }
}

function makeOrder(string $id, array $line): array
{
    return [
        'id' => $id,
        'status' => 'paid',
        'customer_email' => 'test@example.com',
        'shipping_address' => ['line1' => '1 St', 'city' => 'Berlin', 'country' => 'DE'],
        'attendee_name' => 'Jane Doe',
        'lines' => [$line],
    ];
}

$shipLine = [
    'line_id' => 'products:ship-1:default',
    'entry_id' => 'ship-1',
    'collection_slug' => 'products',
    'quantity' => 1,
    'fulfillment' => 'ship',
    'commerce' => commerce_scenario_coffee_variant(),
    'purchase_option_id' => 'buy_once',
    'billing' => 'one_time',
];
$shipResult = $router->route(makeOrder('ord_ship', $shipLine));
test_assert('ship routed ok', ($shipResult[0]['ok'] ?? false) === true);
test_assert('ship idempotent skip on second run', ($router->route(makeOrder('ord_ship', $shipLine))[0]['skipped'] ?? false) === true);

$deliverLine = [
    'line_id' => 'courses:course-1:course_once',
    'entry_id' => 'course-1',
    'collection_slug' => 'courses',
    'quantity' => 1,
    'fulfillment' => 'deliver',
    'commerce' => commerce_scenario_course(),
    'purchase_option_id' => 'course_once',
    'billing' => 'one_time',
];
$deliverResult = $router->route(makeOrder('ord_deliver', $deliverLine));
test_assert('deliver routed ok', ($deliverResult[0]['ok'] ?? false) === true);
test_assert('deliver token created', !empty($deliverResult[0]['token']));
$tokenRecord = $digital->getByOrderLine('ord_deliver', $deliverLine['line_id']);
test_assert('digital store has token', !empty($tokenRecord['token']));

$registerLine = [
    'line_id' => 'classes:yoga-1:class_ticket',
    'entry_id' => 'yoga-1',
    'collection_slug' => 'classes',
    'quantity' => 1,
    'fulfillment' => 'register',
    'commerce' => commerce_scenario_yoga(),
    'purchase_option_id' => 'class_ticket',
    'billing' => 'one_time',
];
$registerResult = $router->route(makeOrder('ord_register', $registerLine));
test_assert('register routed ok', ($registerResult[0]['ok'] ?? false) === true);
$reg = $registrations->getByOrderLine('ord_register', $registerLine['line_id']);
test_assert('registration stored', ($reg['attendee_name'] ?? '') === 'Jane Doe');

$noneLine = [
    'line_id' => 'memberships:mem-1:community_monthly',
    'entry_id' => 'mem-1',
    'collection_slug' => 'memberships',
    'quantity' => 1,
    'fulfillment' => 'none',
    'commerce' => commerce_scenario_community(),
    'purchase_option_id' => 'community_monthly',
    'billing' => 'recurring',
    'interval' => 'month',
];
$noneResult = $router->route(makeOrder('ord_none', $noneLine));
test_assert('none routed ok', ($noneResult[0]['ok'] ?? false) === true);
test_assert('none membership flag', ($noneResult[0]['membership'] ?? false) === true);

test_assert('router processed ship line', $router->isProcessed('ord_ship:' . $shipLine['line_id']) === true);

array_map('unlink', glob($dataDir . '/fulfillment_exports/*.json') ?: []);
@unlink($dataDir . '/registrations.json');
@unlink($dataDir . '/digital_deliveries.json');
@unlink($dataDir . '/fulfillment_state.json');
@rmdir($dataDir . '/fulfillment_exports');
@rmdir($dataDir);

exit(test_finish('Commerce fulfillment router tests'));
