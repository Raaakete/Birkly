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

$now = time();
$windowed = birkly_analytics_filter_events_time_window([
    ['timestamp' => $now - 100, 'event_type' => 'page_view'],
    ['timestamp' => $now - 90000, 'event_type' => 'page_view'],
], $now - 3600);
assert_true(count($windowed) === 1, 'time window keeps events in last hour');

$hourStart = $now - 7200;
$hourlyEvents = [
    ['timestamp' => $hourStart + 100, 'event_type' => 'page_view', 'ip_hash' => 'a', 'visitor_token' => 't1'],
    ['timestamp' => $hourStart + 3700, 'event_type' => 'page_view', 'ip_hash' => 'b', 'visitor_token' => 't2'],
];
$hourly = birkly_analytics_hourly_series($hourlyEvents, $hourStart, $hourStart + 7200);
assert_true(count($hourly) === 2, 'hourly series has two buckets for two hours');
assert_true(($hourly[0]['views'] ?? 0) === 1 && ($hourly[1]['views'] ?? 0) === 1, 'hourly views per bucket');

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

$outboundReferrer = birkly_analytics_aggregate_events([
    [
        'event_type' => 'outbound_click',
        'path' => '/',
        'referrer' => 'https://partner.example/docs',
        'session_id' => 'sess_ref',
        'timestamp' => time(),
    ],
]);
assert_true(
    ($outboundReferrer['outbound_targets'][0]['url'] ?? '') === 'https://partner.example/docs',
    'outbound ranking uses referrer href fallback'
);

$journey = birkly_analytics_build_journey_index([
    array_merge($event, ['event_type' => 'page_view', 'path' => '/']),
    array_merge($event, ['timestamp' => time() + 2, 'event_type' => 'page_view', 'path' => '/about']),
    array_merge($event, ['timestamp' => time() + 4, 'event_type' => 'form_submit', 'path' => '/about', 'event_data' => ['form_id' => 'contact']]),
]);
assert_true(!empty($journey['sessions']), 'journey sessions built');
assert_true(!empty($journey['paths']), 'journey paths built');
assert_true(count($journey['sessions'][0]['trail'] ?? []) >= 3, 'session trail has steps');
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

assert_true(birkly_analytics_is_bot_user_agent('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'), 'googlebot detected');
assert_true(birkly_analytics_traffic_class([
    'event_type' => 'page_view',
    'user_agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)',
    'browser' => 'Chrome',
    'device_type' => 'desktop',
]) === 'bot', 'stored chrome UA still bot when googlebot');

$suspected = birkly_analytics_traffic_class([
    'event_type' => 'page_view',
    'user_agent' => '',
    'browser' => 'Unknown',
    'os' => 'Unknown',
    'device_type' => 'unknown',
    'screen_width' => 0,
]);
assert_true($suspected === 'suspected', 'empty UA without session classified suspected');

$limited = birkly_analytics_traffic_class([
    'event_type' => 'page_view',
    'user_agent' => '',
    'session_id' => 'sess_privacy',
    'consent_state' => 'not_required',
    'browser' => 'Unknown',
    'os' => 'Unknown',
    'device_type' => 'unknown',
    'screen_width' => 0,
]);
assert_true($limited === 'limited', 'empty UA with session classified limited');

$anonLimited = birkly_analytics_traffic_class([
    'event_type' => 'page_view',
    'user_agent' => 'Mozilla/5.0',
    'consent_state' => 'anonymous',
]);
assert_true($anonLimited === 'limited', 'anonymous consent classified limited');

$sameIpMultiSession = birkly_analytics_aggregate_events([
    array_merge($event, ['session_id' => 'sess_a', 'path' => '/a', 'timestamp' => time()]),
    array_merge($event, ['session_id' => 'sess_b', 'path' => '/b', 'timestamp' => time() + 1]),
    array_merge($event, ['session_id' => 'sess_c', 'path' => '/c', 'timestamp' => time() + 2]),
]);
assert_true($sameIpMultiSession['unique_visitors'] === 3, 'same IP with distinct sessions counts as three visitors');
assert_true(($sameIpMultiSession['sessions'] ?? 0) === 3, 'three sessions recorded');

$tokenA = hash('sha256', 'example.com:vid_one');
$tokenB = hash('sha256', 'example.com:vid_two');
$tokenVisitors = birkly_analytics_aggregate_events([
    array_merge($event, ['visitor_token_hash' => $tokenA, 'session_id' => 's1', 'timestamp' => time()]),
    array_merge($event, ['visitor_token_hash' => $tokenA, 'session_id' => 's2', 'timestamp' => time() + 1]),
    array_merge($event, ['visitor_token_hash' => $tokenB, 'session_id' => 's3', 'timestamp' => time() + 2]),
]);
assert_true($tokenVisitors['unique_visitors'] === 2, 'visitor_token_hash dedupes across sessions');

$rawQuality = [];
for ($i = 0; $i < 8; $i++) {
    $rawQuality[] = array_merge($event, [
        'session_id' => 'sess_q_' . $i,
        'timestamp' => time() + $i,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
        'schema_version' => 2,
        'consent_state' => 'granted',
        'visitor_token_hash' => hash('sha256', 'example.com:vid_' . ($i % 3)),
    ]);
}
$aggregated = birkly_analytics_aggregate_events($rawQuality);
$finalized = birkly_analytics_finalize_report($rawQuality, $aggregated);
assert_true(is_array($finalized['quality']), 'quality block present');
assert_true(($finalized['quality']['confidence'] ?? '') === 'low', 'small sample stays low confidence');

$manySessions = [];
for ($j = 0; $j < 12; $j++) {
    $manySessions[] = array_merge($event, [
        'session_id' => 'sess_many_' . $j,
        'timestamp' => time() + $j,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36',
    ]);
}
$aggMany = birkly_analytics_aggregate_events($manySessions);
assert_true($aggMany['unique_visitors'] === 12, 'twelve sessions yield twelve visitors');
$finMany = birkly_analytics_finalize_report($manySessions, $aggMany);
assert_true(empty($finMany['pages_per_visitor_suppressed']), 'many sessions allow pages per visitor');

$oneVisitorManySessions = [];
for ($k = 0; $k < 6; $k++) {
    $oneVisitorManySessions[] = array_merge($event, [
        'session_id' => 'sess_dup_' . $k,
        'timestamp' => time() + $k,
        'user_agent' => '',
        'browser' => 'Unknown',
        'os' => 'Unknown',
        'device_type' => 'unknown',
    ]);
}
$aggOne = birkly_analytics_aggregate_events($oneVisitorManySessions);
$finOne = birkly_analytics_finalize_report($oneVisitorManySessions, $aggOne);
assert_true(!empty($finOne['pages_per_visitor_suppressed']), 'contradictory visitor math suppresses ratio');

echo "analytics_enrich_test: OK\n";
