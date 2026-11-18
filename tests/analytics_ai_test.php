<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/analytics_ai.php';
require_once dirname(__DIR__) . '/core/analytics_enrich.php';

$failures = 0;

function ai_assert(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '  ' . $detail . "\n";
        }
    }
}

$chat = birkly_analytics_detect_ai_attribution('https://chatgpt.com/', 'chatgpt.com', '', '', 'Mozilla/5.0');
ai_assert('chatgpt referrer', $chat['is_ai'] === true && $chat['ai_source'] === 'ChatGPT');

$bot = birkly_analytics_detect_ai_attribution('', '', '', '', 'Mozilla/5.0 (compatible; GPTBot/1.0)');
ai_assert('gptbot ua', $bot['is_ai'] === true && str_contains($bot['ai_source'], 'GPTBot'));

$built = birkly_analytics_build_event(
    [
        'tracking_id' => 'example.com',
        'path' => '/',
        'referrer' => 'https://www.perplexity.ai/search?q=test',
    ],
    '203.0.113.4',
    'Mozilla/5.0',
    time()
);
ai_assert('build event ai', ($built['ai_source'] ?? '') === 'Perplexity');

$events = [
    ['event_type' => 'page_view', 'ai_source' => 'ChatGPT', 'ai_channel' => 'referral', 'is_ai' => true],
    ['event_type' => 'page_view', 'ai_source' => 'ChatGPT', 'ai_channel' => 'referral', 'is_ai' => true],
    ['event_type' => 'page_view'],
];
$summary = birkly_analytics_summarize_ai_sources($events);
ai_assert('summarize ai', count($summary) === 1 && ($summary[0]['views'] ?? 0) === 2);

exit($failures > 0 ? 1 : 0);