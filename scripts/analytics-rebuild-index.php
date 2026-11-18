#!/usr/bin/env php
<?php
/**
 * Rebuild analytics SQLite index from JSON daily logs (P108).
 *
 * Usage:
 *   php scripts/analytics-rebuild-index.php [--tracking-id=example.com] [--from=YYYY-MM-DD] [--to=YYYY-MM-DD]
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/analytics_enrich.php';
require_once $root . '/core/analytics_db.php';

$trackingId = null;
$startDate = date('Y-m-d', strtotime('-180 days'));
$endDate = date('Y-m-d');

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--tracking-id=')) {
        $trackingId = substr($arg, strlen('--tracking-id='));
    } elseif (str_starts_with($arg, '--from=')) {
        $startDate = substr($arg, strlen('--from='));
    } elseif (str_starts_with($arg, '--to=')) {
        $endDate = substr($arg, strlen('--to='));
    }
}

$result = birkly_analytics_db_rebuild_all($trackingId, $startDate, $endDate);

echo json_encode([
    'status' => 'ok',
    'start_date' => $startDate,
    'end_date' => $endDate,
    'tracking_id' => $trackingId ?? 'all',
    'events_indexed' => $result['events_indexed'],
    'domains' => $result['domains'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;