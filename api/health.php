<?php
/**
 * Public health check (no auth) — P12-M5 monitoring v1, P13-M4 alerts.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../core/health.php';
require_once __DIR__ . '/../core/health_alerts.php';

$report = birkly_health_report();
birkly_health_dispatch_alerts($report);
$httpStatus = match ($report['status'] ?? 'error') {
    'ok', 'degraded' => 200,
    default => 503,
};

http_response_code($httpStatus);
echo json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
