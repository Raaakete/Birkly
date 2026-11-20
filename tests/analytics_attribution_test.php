<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/analytics_attribution.php';
require_once dirname(__DIR__) . '/core/analytics_ai.php';
require_once dirname(__DIR__) . '/core/analytics_enrich.php';

$failures = 0;

function attr_assert(string $name, bool $ok): void
{
    global $failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
    }
}

$parsed = birkly_analytics_parse_attribution_query('/pricing?utm_source=newsletter&utm_campaign=launch');
attr_assert('parse utm from path', $parsed['utm_source'] === 'newsletter' && $parsed['utm_campaign'] === 'launch');

$ref = birkly_analytics_parse_attribution_query('/?ref=chatgpt');
attr_assert('parse ref param', $ref['ref_source'] === 'chatgpt');

$merged = birkly_analytics_enrich_input_attribution([
    'path' => '/docs?ref=perplexity',
    'referrer' => '',
    'landing_referrer' => 'https://www.perplexity.ai/search?q=birkly',
]);
attr_assert(
    'merge ref and landing referrer',
    $merged['utm_source'] === 'perplexity' && $merged['referrer'] !== ''
);

$event = birkly_analytics_build_event([
    'tracking_id' => 'birkly.io',
    'domain' => 'birkly.io',
    'path' => '/features',
    'referrer' => '',
    'landing_referrer' => 'https://chatgpt.com/',
    'utm_source' => 'newsletter',
    'utm_medium' => 'email',
    'utm_campaign' => 'winter',
    'session_id' => 'sess_attr',
], '203.0.113.8', 'Mozilla/5.0', time());

attr_assert('persisted utm on inner path', ($event['utm_campaign'] ?? '') === 'winter');
attr_assert('landing referrer stored', ($event['referrer_host'] ?? '') === 'chatgpt.com');
attr_assert('ai from landing referrer', ($event['ai_source'] ?? '') === 'ChatGPT');

$pathAi = birkly_analytics_build_event([
    'tracking_id' => 'birkly.io',
    'path' => '/?utm_source=cursor&utm_medium=ai',
    'referrer' => '',
], '203.0.113.9', 'Mozilla/5.0', time() + 1);
attr_assert('ai from utm path', ($pathAi['ai_source'] ?? '') === 'Cursor');

$report = birkly_analytics_aggregate_events([
    $event,
    array_merge($event, ['path' => '/pricing', 'referrer' => '', 'utm_source' => '', 'utm_medium' => '', 'utm_campaign' => '']),
]);
$campaignLabels = array_column($report['campaigns'] ?? [], 'campaign');
attr_assert('campaign includes named utm', in_array('winter (newsletter)', $campaignLabels, true));

exit($failures > 0 ? 1 : 0);