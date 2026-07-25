<?php
/**
 * Analytics enrichment and aggregation tests.
 */

require_once __DIR__ . '/../core/analytics_enrich.php';

function assert_true($condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

$ua = birkly_analytics_parse_user_agent('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1');
assert_true($ua['device_type'] === 'mobile', 'iPhone should be mobile');
assert_true($ua['browser'] === 'Safari', 'Safari browser detected');
assert_true($ua['os'] === 'iOS', 'iOS detected');

$event = birkly_analytics_build_event([
    'tracking_id' => 'example.com',
    'domain' => 'example.com',
    'path' => '/project/index.html',
    'referrer' => 'https://google.com/search',
    'language' => 'en-US',
    'timezone' => 'Europe/Copenhagen',
    'session_id' => 'sess_test',
    'utm_source' => 'newsletter',
    'utm_medium' => 'email',
], '203.0.113.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0', time());

assert_true($event['device_type'] === 'desktop', 'desktop device');
assert_true($event['browser'] === 'Chrome', 'chrome browser');
assert_true($event['country'] === 'DK', 'timezone maps to DK');
assert_true($event['utm_source'] === 'newsletter', 'utm source stored');
assert_true($event['referrer_host'] === 'google.com', 'referrer host parsed');

$report = birkly_analytics_aggregate_events([
    $event,
    array_merge($event, [
        'timestamp' => time() + 5,
        'path' => '/project/about.html',
        'event_type' => 'pageview',
    ]),
    [
        'event_type' => 'page_exit',
        'path' => '/project/about.html',
        'timestamp' => time() + 10,
    ],
]);

assert_true($report['page_views'] === 2, 'two page views counted');
assert_true($report['unique_visitors'] === 1, 'one visitor');
assert_true(!empty($report['entrance_pages']), 'entrance pages present');
assert_true(!empty($report['exit_pages']), 'exit pages present');
assert_true(!empty($report['devices']), 'devices present');
assert_true(!empty($report['countries']), 'countries present');

echo "analytics_enrich_test: OK\n";
