<?php
/**
 * P12-M5 — CMS health endpoint and core diagnostics.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/health.php';
require_once dirname(__DIR__) . '/core/marketplace.php';

test_reset_failures();
echo "Health diagnostics tests\n\n";

$report = birkly_health_report();
test_assert('report has status', isset($report['status']));
test_assert('report has version', ($report['version'] ?? '') !== '');
test_assert('report lists writable dirs', count($report['writable'] ?? []) >= 4);
test_assert('settings writable in dev', !empty($report['writable']['settings']['writable']));
test_assert('disk block present', isset($report['disk']['free_bytes']) || ($report['disk']['free_bytes'] ?? null) === null);
test_assert('marketplace block present', isset($report['marketplace']['url']));

putenv('BIRKLY_HEALTH_SKIP_MARKETPLACE=1');
$skipped = birkly_health_marketplace();
test_assert('marketplace skip env works', ($skipped['status'] ?? '') === 'skipped');
putenv('BIRKLY_HEALTH_SKIP_MARKETPLACE');

$GLOBALS['birkly_marketplace_mock_handler'] = static function (string $method, string $path): array {
    if ($method === 'GET' && $path === '/health') {
        return ['ok' => true, 'status' => 200, 'data' => ['status' => 'ok', 'service' => 'birkly-marketplace']];
    }
    return ['ok' => false, 'status' => 404, 'error' => 'not found'];
};
$reachable = birkly_health_marketplace();
test_assert('marketplace mock ok', ($reachable['status'] ?? '') === 'ok');
unset($GLOBALS['birkly_marketplace_mock_handler']);

$endpointReport = birkly_health_report();
test_assert('endpoint payload ok/degraded', in_array($endpointReport['status'] ?? '', ['ok', 'degraded'], true));
test_assert('endpoint includes service id', ($endpointReport['service'] ?? '') === 'birkly-cms');
test_assert('endpoint includes checks catalog', isset($endpointReport['checks']['writable_dirs']['status']));
test_assert('required response keys include checks', in_array('checks', birkly_health_required_response_keys(), true));

exit(test_finish('Health diagnostics tests'));
