<?php

declare(strict_types=1);

/**
 * AI traffic attribution — referrers, UTM hints, and known AI crawlers/user agents.
 */

/**
 * @return array<string, string> host fragment => product label
 */
function birkly_analytics_ai_referrer_map(): array
{
    return [
        'chatgpt.com' => 'ChatGPT',
        'chat.openai.com' => 'ChatGPT',
        'openai.com' => 'OpenAI',
        'perplexity.ai' => 'Perplexity',
        'claude.ai' => 'Claude',
        'anthropic.com' => 'Anthropic',
        'copilot.microsoft.com' => 'Copilot',
        'bing.com/chat' => 'Copilot',
        'gemini.google.com' => 'Gemini',
        'bard.google.com' => 'Gemini',
        'you.com' => 'You.com',
        'phind.com' => 'Phind',
        'poe.com' => 'Poe',
        'meta.ai' => 'Meta AI',
        'duck.ai' => 'Duck.ai',
        'kagi.com' => 'Kagi',
        'cursor.com' => 'Cursor',
        'cursor.sh' => 'Cursor',
        'grok.x.ai' => 'Grok',
        'x.ai' => 'xAI',
        'notebooklm.google.com' => 'NotebookLM',
        'edgeservices.bing.com' => 'Copilot',
        'search.brave.com' => 'Brave Search',
        'komo.ai' => 'Komo',
        'andisearch.com' => 'Andi',
    ];
}

/**
 * @return array{ai_source: string, ai_channel: string, is_ai: bool}
 */
function birkly_analytics_detect_ai_attribution(
    string $referrer,
    string $referrerHost,
    string $utmSource,
    string $utmMedium,
    string $userAgent
): array {
  $empty = ['ai_source' => '', 'ai_channel' => '', 'is_ai' => false];

    $host = strtolower($referrerHost);
    if ($host === '' && $referrer !== '') {
        $host = strtolower((string) (parse_url($referrer, PHP_URL_HOST) ?? ''));
    }

    foreach (birkly_analytics_ai_referrer_map() as $needle => $label) {
        if ($host !== '' && (str_contains($host, $needle) || str_ends_with($host, '.' . $needle))) {
            return ['ai_source' => $label, 'ai_channel' => 'referral', 'is_ai' => true];
        }
        if ($referrer !== '' && stripos($referrer, $needle) !== false) {
            return ['ai_source' => $label, 'ai_channel' => 'referral', 'is_ai' => true];
        }
    }

    $utm = strtolower(trim($utmSource));
    $medium = strtolower(trim($utmMedium));
    if ($utm !== '') {
        foreach (birkly_analytics_ai_referrer_map() as $needle => $label) {
            if (str_contains($utm, str_replace('.com', '', $needle)) || str_contains($utm, $needle)) {
                return ['ai_source' => $label, 'ai_channel' => 'campaign', 'is_ai' => true];
            }
        }
        if (str_contains($utm, 'chatgpt') || str_contains($utm, 'openai')) {
            return ['ai_source' => 'ChatGPT', 'ai_channel' => 'campaign', 'is_ai' => true];
        }
        if (str_contains($utm, 'perplexity')) {
            return ['ai_source' => 'Perplexity', 'ai_channel' => 'campaign', 'is_ai' => true];
        }
        if ($medium === 'ai' || $medium === 'llm' || str_contains($medium, 'chatbot')) {
            return ['ai_source' => $utm !== '' ? ucfirst($utm) : 'AI', 'ai_channel' => 'campaign', 'is_ai' => true];
        }
    }

    $ua = strtolower($userAgent);
    $uaPatterns = [
        'gptbot' => 'OpenAI GPTBot',
        'chatgpt-user' => 'ChatGPT User',
        'oai-searchbot' => 'OpenAI SearchBot',
        'claudebot' => 'ClaudeBot',
        'claude-web' => 'Claude Web',
        'anthropic-ai' => 'Anthropic',
        'perplexitybot' => 'PerplexityBot',
        'perplexity-user' => 'Perplexity User',
        'google-extended' => 'Google Extended',
        'bytespider' => 'ByteSpider',
        'cohere-ai' => 'Cohere',
        'meta-externalagent' => 'Meta AI',
        'applebot-extended' => 'Applebot Extended',
    ];
    foreach ($uaPatterns as $needle => $label) {
        if ($ua !== '' && str_contains($ua, $needle)) {
            $channel = str_contains($needle, 'bot') ? 'crawler' : 'referral';
            return ['ai_source' => $label, 'ai_channel' => $channel, 'is_ai' => true];
        }
    }

    $uaInApp = [
        'chatgpt' => 'ChatGPT',
        'openai' => 'ChatGPT',
        'perplexity' => 'Perplexity',
        'claude' => 'Claude',
        'anthropic' => 'Claude',
        'copilot' => 'Copilot',
        'bingandroid' => 'Copilot',
    ];
    foreach ($uaInApp as $needle => $label) {
        if ($ua !== '' && str_contains($ua, $needle) && !str_contains($ua, 'bot')) {
            return ['ai_source' => $label, 'ai_channel' => 'referral', 'is_ai' => true];
        }
    }

    return $empty;
}

/**
 * Infer AI product from landing path/query (ref=chatgpt, etc.).
 *
 * @return array{ai_source: string, ai_channel: string, is_ai: bool}
 */
function birkly_analytics_detect_ai_from_path(string $path): array
{
    $empty = ['ai_source' => '', 'ai_channel' => '', 'is_ai' => false];
    if (!function_exists('birkly_analytics_parse_attribution_query')) {
        $attributionPath = __DIR__ . '/analytics_attribution.php';
        if (is_file($attributionPath)) {
            require_once $attributionPath;
        }
    }
    if (!function_exists('birkly_analytics_parse_attribution_query')) {
        return $empty;
    }

    $parsed = birkly_analytics_parse_attribution_query($path);
    $utmSource = strtolower(trim($parsed['utm_source']));
    $refSource = strtolower(trim($parsed['ref_source'] !== '' ? $parsed['ref_source'] : ''));
    $candidate = $utmSource !== '' ? $utmSource : $refSource;
    if ($candidate === '') {
        return $empty;
    }

    foreach (birkly_analytics_ai_referrer_map() as $needle => $label) {
        $needleNorm = str_replace('.com', '', $needle);
        if (str_contains($candidate, $needle) || str_contains($candidate, $needleNorm)) {
            return ['ai_source' => $label, 'ai_channel' => 'campaign', 'is_ai' => true];
        }
    }

    $tokens = [
        'chatgpt' => 'ChatGPT',
        'openai' => 'ChatGPT',
        'perplexity' => 'Perplexity',
        'claude' => 'Claude',
        'anthropic' => 'Claude',
        'copilot' => 'Copilot',
        'gemini' => 'Gemini',
        'bard' => 'Gemini',
        'cursor' => 'Cursor',
        'grok' => 'Grok',
        'xai' => 'xAI',
        'meta-ai' => 'Meta AI',
        'phind' => 'Phind',
        'poe' => 'Poe',
        'you.com' => 'You.com',
        'kagi' => 'Kagi',
    ];
    foreach ($tokens as $token => $label) {
        if (str_contains($candidate, $token)) {
            return ['ai_source' => $label, 'ai_channel' => 'campaign', 'is_ai' => true];
        }
    }

    $medium = strtolower(trim($parsed['utm_medium']));
    if ($medium === 'ai' || $medium === 'llm' || str_contains($medium, 'chatbot')) {
        return ['ai_source' => ucfirst($candidate), 'ai_channel' => 'campaign', 'is_ai' => true];
    }

    return $empty;
}

/**
 * @param list<array<string, mixed>> $events
 * @return list<array{source: string, channel: string, views: int}>
 */
function birkly_analytics_summarize_ai_sources(array $events): array
{
    $counts = [];
    foreach ($events as $event) {
        if (!is_array($event)) {
            continue;
        }
        $src = trim((string) ($event['ai_source'] ?? ''));
        if ($src === '') {
            continue;
        }
        $channel = trim((string) ($event['ai_channel'] ?? 'referral'));
        $key = $channel . ':' . $src;
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }
    arsort($counts);
    $rows = [];
    foreach ($counts as $key => $views) {
        $parts = explode(':', $key, 2);
        $rows[] = [
            'channel' => $parts[0] ?? 'referral',
            'source' => $parts[1] ?? $key,
            'views' => $views,
        ];
        if (count($rows) >= 15) {
            break;
        }
    }

    return $rows;
}