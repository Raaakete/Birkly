<?php
/**
 * P17-M5 — PostPurchaseRouter idempotency and automation trigger tests.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/fixtures/commerce_scenarios.php';

test_reset_failures();
echo "Commerce post-purchase idempotency tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';
require_once $root . '/plugins/commerce/includes/CommerceSchema.php';
require_once $root . '/plugins/commerce/includes/FulfillmentAdapterInterface.php';
require_once $root . '/plugins/commerce/includes/ManualFulfillmentAdapter.php';
require_once $root . '/plugins/commerce/includes/EventRegistrationStore.php';
require_once $root . '/plugins/commerce/includes/DigitalDeliveryStore.php';
require_once $root . '/plugins/commerce/includes/PurchaseMappingService.php';
require_once $root . '/plugins/commerce/includes/PostPurchaseRouter.php';

$dataDir = sys_get_temp_dir() . '/birkly-pp-idem-' . getmypid();
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

function idemOrder(string $id, array $line): array
{
    return [
        'id' => $id,
        'status' => 'paid',
        'customer_email' => 'idem@example.com',
        'shipping_address' => ['line1' => '1 St', 'city' => 'Berlin', 'country' => 'DE'],
        'attendee_name' => 'Idem User',
        'lines' => [$line],
    ];
}

$shipLine = [
    'line_id' => 'products:ship-idem:default',
    'entry_id' => 'ship-idem',
    'collection_slug' => 'products',
    'quantity' => 1,
    'fulfillment' => 'ship',
    'commerce' => commerce_scenario_coffee_variant(),
    'purchase_option_id' => 'buy_once',
    'billing' => 'one_time',
];

$firstShip = $router->route(idemOrder('ord_idem_ship', $shipLine));
test_assert('first ship ok', ($firstShip[0]['ok'] ?? false) === true);
$exportCount1 = count(glob($dataDir . '/fulfillment_exports/*.json') ?: []);
$secondShip = $router->route(idemOrder('ord_idem_ship', $shipLine));
test_assert('second ship skipped', ($secondShip[0]['skipped'] ?? false) === true);
$exportCount2 = count(glob($dataDir . '/fulfillment_exports/*.json') ?: []);
test_assert('no duplicate export files', $exportCount2 === $exportCount1);

$deliverLine = [
    'line_id' => 'courses:course-idem:once',
    'entry_id' => 'course-idem',
    'collection_slug' => 'courses',
    'quantity' => 1,
    'fulfillment' => 'deliver',
    'commerce' => commerce_scenario_course(),
    'purchase_option_id' => 'course_once',
    'billing' => 'one_time',
];
$router->route(idemOrder('ord_idem_deliver', $deliverLine));
$token1 = $digital->getByOrderLine('ord_idem_deliver', $deliverLine['line_id']);
$router->route(idemOrder('ord_idem_deliver', $deliverLine));
$token2 = $digital->getByOrderLine('ord_idem_deliver', $deliverLine['line_id']);
test_assert('single digital token', ($token1['token'] ?? '') !== '' && ($token1['token'] ?? '') === ($token2['token'] ?? ''));

$registerLine = [
    'line_id' => 'classes:yoga-idem:ticket',
    'entry_id' => 'yoga-idem',
    'collection_slug' => 'classes',
    'quantity' => 1,
    'fulfillment' => 'register',
    'commerce' => commerce_scenario_yoga(),
    'purchase_option_id' => 'class_ticket',
    'billing' => 'one_time',
];
$router->route(idemOrder('ord_idem_reg', $registerLine));
$reg1 = $registrations->listAll();
$router->route(idemOrder('ord_idem_reg', $registerLine));
$reg2 = $registrations->listAll();
test_assert('single registration record', count($reg1) === 1 && count($reg2) === 1);

$eventsBefore = count($automationEvents);
$router->route(idemOrder('ord_idem_ship', $shipLine));
$eventsAfter = count($automationEvents);
test_assert('no duplicate automation on replay', $eventsAfter === $eventsBefore);

test_assert('processed state queryable', $router->isProcessed('ord_idem_ship:' . $shipLine['line_id']) === true);

array_map('unlink', glob($dataDir . '/fulfillment_exports/*.json') ?: []);
@unlink($dataDir . '/registrations.json');
@unlink($dataDir . '/digital_deliveries.json');
@unlink($dataDir . '/fulfillment_state.json');
@rmdir($dataDir . '/fulfillment_exports');
@rmdir($dataDir);

exit(test_finish('commerce_post_purchase_idempotency_test'));
