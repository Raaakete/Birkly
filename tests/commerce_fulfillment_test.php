<?php
/**
 * P6.4-M5 — Fulfillment adapter tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Commerce fulfillment tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';
require_once $root . '/plugins/commerce/includes/FulfillmentAdapterInterface.php';
require_once $root . '/plugins/commerce/includes/ManualFulfillmentAdapter.php';
require_once $root . '/plugins/commerce/includes/ShipStationStubAdapter.php';

$dataDir = sys_get_temp_dir() . '/birkly-commerce-fulfill-' . getmypid();
@mkdir($dataDir, 0755, true);

$manual = new ManualFulfillmentAdapter($dataDir . '/exports');
test_assert('manual is configured', $manual->isConfigured() === true);
$test = $manual->testConnection();
test_assert('manual test ok', ($test['ok'] ?? false) === true);

$order = ['id' => 'cord_test1', 'lines' => [['sku' => 'SKU1', 'quantity' => 1]]];
$push = $manual->pushOrder($order);
test_assert('manual push ok', ($push['ok'] ?? false) === true);
test_assert('export file created', is_file($push['export_path'] ?? ''));

$stub = new ShipStationStubAdapter(['mock' => true]);
test_assert('shipstation stub configured with mock', $stub->isConfigured() === true);
$stubTest = $stub->testConnection();
test_assert('shipstation stub test ok', ($stubTest['ok'] ?? false) === true);
$stubPush = $stub->pushOrder($order);
test_assert('shipstation stub push ok', ($stubPush['ok'] ?? false) === true);

array_map('unlink', glob($dataDir . '/exports/*.json') ?: []);
@rmdir($dataDir . '/exports');
@rmdir($dataDir);

exit(test_finish('Commerce fulfillment tests'));
