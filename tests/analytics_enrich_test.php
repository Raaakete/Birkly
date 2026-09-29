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
assert_true(($report['sessions'] ?? 0) === 1, 'one session from session_id');
assert_true(isset($report['bounce_rate']), 'bounce rate present');
assert_true(isset($report['pages_per_visitor']), 'pages per visitor present');
assert_true(!empty($report['entrance_pages']), 'entrance pages present');
assert_true(!empty($report['exit_pages']), 'exit pages present');
assert_true(!empty($report['devices']), 'devices present');
assert_true(!empty($report['operating_systems']), 'operating systems present');
assert_true(!empty($report['countries']), 'countries present');

$advanced = birkly_analytics_aggregate_events([
    array_merge($event, ['event_type' => 'page_view']),
    [
        'event_type' => 'form_submit',
        'path' => '/contact',
        'ip_hash' => $event['ip_hash'],
        'session_id' => 'sess_forms',
        'timestamp' => time() + 20,
        'event_name' => 'contact',
        'event_data' => ['form_id' => 'contact'],
        'device_type' => 'mobile',
        'browser' => 'Safari',
        'os' => 'iOS',
    ],
    [
        'event_type' => 'outbound_click',
        'path' => '/',
        'ip_hash' => 'visitor-b',
        'session_id' => 'sess_out',
        'timestamp' => time() + 25,
        'event_data' => ['href' => 'https://example.com'],
        'device_type' => 'desktop',
        'browser' => 'Firefox',
        'os' => 'Linux',
    ],
    [
        'event_type' => 'plugin_event',
        'path' => '/',
        'ip_hash' => 'visitor-b',
        'session_id' => 'sess_out',
        'timestamp' => time() + 26,
        'event_name' => 'commerce.purchase',
        'device_type' => 'desktop',
        'browser' => 'Firefox',
        'os' => 'Linux',
    ],
]);
assert_true(($advanced['event_counts']['form_submit'] ?? 0) === 1, 'form submit counted');
assert_true(($advanced['event_counts']['outbound_click'] ?? 0) === 1, 'outbound counted');
assert_true(($advanced['event_counts']['plugin_event'] ?? 0) === 1, 'plugin event counted');
assert_true(!empty($advanced['forms']), 'forms ranking present');
assert_true(!empty($advanced['outbound_targets']), 'outbound ranking present');
assert_true(!empty($advanced['plugin_events']), 'plugin events ranking present');

$minimalEvent = [
    'event_type' => 'page_view',
    'path' => '/',
    'visitor_day_key' => 'visitor-day-a',
    'timestamp' => time(),
];
$minimalReport = birkly_analytics_aggregate_events([
    $minimalEvent,
    array_merge($minimalEvent, ['path' => '/about', 'timestamp' => time() + 5]),
    array_merge($minimalEvent, ['visitor_day_key' => 'visitor-day-b', 'timestamp' => time() + 10]),
]);
assert_true($minimalReport['page_views'] === 3, 'minimal page views counted');
assert_true($minimalReport['unique_visitors'] === 2, 'minimal visitors use visitor_day_key');

$pageOnlyTrend = birkly_analytics_aggregate_events([
    array_merge($event, ['event_type' => 'page_view']),
    [
        'event_type' => 'form_submit',
        'path' => '/contact',
        'ip_hash' => $event['ip_hash'],
        'timestamp' => time() + 30,
    ],
]);
assert_true(($pageOnlyTrend['daily_stats'][0]['views'] ?? 0) === 1, 'daily trend counts page views only');
assert_true(($pageOnlyTrend['daily_stats'][0]['interactions'] ?? 0) === 1, 'daily trend counts form, click and plugin events as interactions');

echo "analytics_enrich_test: OK\n";
