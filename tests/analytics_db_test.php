<?php
/**
 * Analytics SQLite index tests.
 */

require_once __DIR__ . '/../core/analytics_enrich.php';
require_once __DIR__ . '/../core/analytics_db.php';

function adb_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

$dbPath = sys_get_temp_dir() . '/birkly-analytics-db-' . bin2hex(random_bytes(4)) . '.sqlite';
putenv('BIRKLY_ANALYTICS_DB_PATH=' . $dbPath);

$event = birkly_analytics_build_event([
    'tracking_id' => 'example.com',
    'domain' => 'example.com',
    'path' => '/hello',
    'session_id' => 'sess_sqlite',
    'visitor_token' => 'vid_fixture',
    'consent_state' => 'granted',
], '198.51.100.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/122.0.0.0', time());

adb_assert(birkly_analytics_db_insert_event('example.com', $event), 'event inserted into sqlite');
adb_assert(is_file($dbPath), 'sqlite file created');

$pdo = birkly_analytics_db_connect();
adb_assert($pdo instanceof PDO, 'sqlite connection opens');

$count = (int) $pdo->query('SELECT COUNT(*) FROM analytics_events')->fetchColumn();
adb_assert($count === 1, 'one row stored');

$row = $pdo->query('SELECT traffic_class, session_id FROM analytics_events LIMIT 1')->fetch(PDO::FETCH_ASSOC);
adb_assert(is_array($row), 'row readable');
adb_assert(($row['session_id'] ?? '') === 'sess_sqlite', 'session id persisted');
adb_assert(in_array($row['traffic_class'] ?? '', ['human', 'suspected', 'bot'], true), 'traffic class stored');

$rebuild = birkly_analytics_db_replace_scope('example.com', date('Y-m-d'), date('Y-m-d'), [$event]);
adb_assert($rebuild === 1, 'replace scope inserts one row');

@unlink($dbPath);
putenv('BIRKLY_ANALYTICS_DB_PATH');

echo "analytics_db_test: OK\n";