<?php
/**
 * P9-M0 smoke tests — bootstrap, routing, health payload.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Marketplace smoke tests\n\n";

$root = dirname(__DIR__);

test_assert('MARKETPLACE_ROOT exists', is_dir($root));

require_once $root . '/core/bootstrap.php';

test_assert('bootstrap loads config', is_array(marketplace_load_config()));
test_assert('config has app_name', marketplace_config('app_name') !== null);
test_assert('data_dir is configured', is_string(marketplace_config('data_dir')) && marketplace_config('data_dir') !== '');

test_assert('health route matches', marketplace_route_key('GET', '/api/v1/health') === 'GET /api/v1/health');
test_assert('catalog route matches', marketplace_route_key('GET', '/api/v1/catalog') === 'GET /api/v1/catalog');
test_assert('landing route matches', marketplace_route_key('GET', '/') === 'GET /');
test_assert('unknown route returns null', marketplace_route_key('POST', '/api/v1/health') === null);

$health = marketplace_health_payload();
test_assert('health status ok', ($health['status'] ?? '') === 'ok');
test_assert('health service name', ($health['service'] ?? '') === 'birkly-marketplace');
test_assert('health includes php version', isset($health['php']) && $health['php'] === PHP_VERSION);

$catalog = marketplace_catalog_payload();
test_assert('catalog offers is array', is_array($catalog['offers'] ?? null));
test_assert('catalog offers empty in M0', ($catalog['offers'] ?? null) === []);
test_assert('catalog meta count zero', (int) ($catalog['meta']['count'] ?? -1) === 0);

$testCollection = 'smoke_test_' . getmypid();
test_assert('storage write', marketplace_storage_write($testCollection, ['ok' => true]));
$read = marketplace_storage_read($testCollection);
test_assert('storage read roundtrip', ($read['ok'] ?? false) === true);
marketplace_storage_delete($testCollection);

exit(test_finish('smoke_test'));
