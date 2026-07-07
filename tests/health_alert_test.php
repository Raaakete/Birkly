<?php
/**
 * P13-M4 — health alert dispatch on error/degraded status.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/health.php';
require_once dirname(__DIR__) . '/core/health_alerts.php';

test_reset_failures();
echo "Health alert tests\n\n";

$dispatched = [];
$GLOBALS['birkly_health_alert_mock'] = static function (array $report) use (&$dispatched): array {
    $dispatched[] = $report;
    return ['dispatched' => true, 'mock' => true];
};

$okResult = birkly_health_dispatch_alerts(['status' => 'ok', 'service' => 'birkly-cms']);
test_assert('ok status does not dispatch', ($okResult['dispatched'] ?? true) === false);

$errorResult = birkly_health_dispatch_alerts([
    'status' => 'error',
    'service' => 'birkly-cms',
    'version' => '0.1.0',
    'checks' => ['writable_dirs' => ['status' => 'error']],
    'writable' => ['settings' => ['writable' => false]],
]);
test_assert('error status dispatches via mock', ($errorResult['dispatched'] ?? false) === true);
test_assert('mock captured error report', count($dispatched) === 1 && ($dispatched[0]['status'] ?? '') === 'error');

$degradedResult = birkly_health_dispatch_alerts([
    'status' => 'degraded',
    'service' => 'birkly-cms',
    'disk' => ['low_space' => true],
]);
test_assert('degraded status dispatches via mock', ($degradedResult['dispatched'] ?? false) === true);

unset($GLOBALS['birkly_health_alert_mock']);

$GLOBALS['birkly_health_writable_override'] = [
    'settings' => ['path' => 'settings', 'exists' => true, 'writable' => false],
    'content' => ['path' => 'content', 'exists' => true, 'writable' => true],
    'cache' => ['path' => 'cache', 'exists' => true, 'writable' => true],
    'logs' => ['path' => 'logs', 'exists' => true, 'writable' => true],
    'media' => ['path' => 'media', 'exists' => true, 'writable' => true],
    'temp' => ['path' => 'temp', 'exists' => true, 'writable' => true],
];
putenv('BIRKLY_HEALTH_SKIP_MARKETPLACE=1');

$alertCalls = [];
$GLOBALS['birkly_health_alert_mock'] = static function (array $report) use (&$alertCalls): array {
    $alertCalls[] = $report;
    return ['dispatched' => true, 'mock' => true];
};

$failureReport = birkly_health_report();
test_assert('simulated writable failure is error', ($failureReport['status'] ?? '') === 'error');
test_assert('checks catalog marks writable_dirs error', ($failureReport['checks']['writable_dirs']['status'] ?? '') === 'error');

$dispatchFromReport = birkly_health_dispatch_alerts($failureReport);
test_assert('report dispatch invokes mock', ($dispatchFromReport['dispatched'] ?? false) === true);
test_assert('mock received failure report', count($alertCalls) === 1);

unset($GLOBALS['birkly_health_writable_override'], $GLOBALS['birkly_health_alert_mock']);
putenv('BIRKLY_HEALTH_SKIP_MARKETPLACE');

$GLOBALS['birkly_health_monitoring_override'] = [
    'alert_email' => 'ops@example.com',
    'alert_webhook_url' => '',
];
$noMockResult = birkly_health_dispatch_alerts(['status' => 'error', 'service' => 'birkly-cms']);
test_assert('configured email target dispatches', ($noMockResult['dispatched'] ?? false) === true);
unset($GLOBALS['birkly_health_monitoring_override']);

test_assert('health report includes checks key', isset(birkly_health_report()['checks']['disk']['status']));
test_assert('required response keys documented', in_array('checks', birkly_health_required_response_keys(), true));

exit(test_finish('Health alert tests'));
