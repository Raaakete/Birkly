<?php

declare(strict_types=1);

/**
 * First-touch attribution helpers — parse landing URLs and merge into track payloads.
 */

/**
 * @return array{utm_source: string, utm_medium: string, utm_campaign: string, utm_term: string, utm_content: string, ref_source: string, ref_medium: string}
 */
function birkly_analytics_parse_attribution_query(string $pathOrQuery): array
{
    $empty = [
        'utm_source' => '',
        'utm_medium' => '',
        'utm_campaign' => '',
        'utm_term' => '',
        'utm_content' => '',
        'ref_source' => '',
        'ref_medium' => '',
    ];

    $query = $pathOrQuery;
    if (str_contains($pathOrQuery, '?')) {
        $parsed = parse_url($pathOrQuery);
        $query = is_array($parsed) ? (string) ($parsed['query'] ?? '') : '';
    }
    $query = ltrim($query, '?');
    if ($query === '') {
        return $empty;
    }

    parse_str($query, $params);
    if (!is_array($params)) {
        return $empty;
    }

    $out = $empty;
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $key) {
        $out[$key] = trim((string) ($params[$key] ?? ''));
    }

    $refKeys = ['utm_source', 'ref', 'via', 'source', 'from'];
    foreach ($refKeys as $key) {
        if ($key === 'utm_source' || !isset($params[$key])) {
            continue;
        }
        $value = trim((string) $params[$key]);
        if ($value === '') {
            continue;
        }
        $out['ref_source'] = $value;
        $out['ref_medium'] = $key === 'via' ? 'via' : 'referral';
        break;
    }

    return $out;
}

/**
 * Merge path/query attribution and session landing referrer into the inbound track payload.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function birkly_analytics_enrich_input_attribution(array $input): array
{
    $path = (string) ($input['path'] ?? '');
    $parsed = birkly_analytics_parse_attribution_query($path);

    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $key) {
        if (trim((string) ($input[$key] ?? '')) === '' && $parsed[$key] !== '') {
            $input[$key] = $parsed[$key];
        }
    }

    if (trim((string) ($input['utm_source'] ?? '')) === '' && $parsed['ref_source'] !== '') {
        $input['utm_source'] = birkly_analytics_normalize_ref_source($parsed['ref_source']);
        if (trim((string) ($input['utm_medium'] ?? '')) === '') {
            $input['utm_medium'] = $parsed['ref_medium'] !== '' ? $parsed['ref_medium'] : 'referral';
        }
    }

    $referrer = trim((string) ($input['referrer'] ?? ''));
    $landing = trim((string) ($input['landing_referrer'] ?? ''));
    if ($referrer === '' && $landing !== '') {
        $input['referrer'] = $landing;
    }

    return $input;
}

/**
 * Normalize shorthand ref/via values (e.g. chatgpt → a stable utm_source token).
 */
function birkly_analytics_normalize_ref_source(string $raw): string
{
    $value = strtolower(trim($raw));
    if ($value === '') {
        return '';
    }

    $map = [
        'chatgpt' => 'chatgpt',
        'gpt' => 'chatgpt',
        'openai' => 'openai',
        'perplexity' => 'perplexity',
        'claude' => 'claude',
        'anthropic' => 'anthropic',
        'copilot' => 'copilot',
        'gemini' => 'gemini',
        'bard' => 'gemini',
        'google-ai' => 'gemini',
        'cursor' => 'cursor',
        'grok' => 'grok',
        'meta-ai' => 'meta-ai',
        'phind' => 'phind',
        'poe' => 'poe',
        'you-com' => 'you.com',
        'you' => 'you.com',
        'kagi' => 'kagi',
        'duck-ai' => 'duck.ai',
    ];

    if (isset($map[$value])) {
        return $map[$value];
    }

    return preg_replace('/[^a-z0-9._-]+/', '-', $value) ?? $value;
}