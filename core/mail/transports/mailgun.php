<?php
/**
 * Thin HTTP adapter for Mailgun (https://www.mailgun.com).
 */

declare(strict_types=1);

function birkly_mail_transport_mailgun(array $payload, array $connection): array
{
    $secrets = is_array($connection['secrets'] ?? null) ? $connection['secrets'] : [];
    $config = is_array($connection['config'] ?? null) ? $connection['config'] : [];
    $apiKey = (string) ($secrets['api_key'] ?? '');
    $domain = (string) ($config['domain'] ?? '');
    $region = (string) ($config['region'] ?? '');
    $connectionId = $connection['id'] ?? null;
    $alias = $connection['alias'] ?? null;

    if ($apiKey === '') {
        return birkly_mail_result('failed', 'mailgun', $connectionId, $alias, 'API key is missing.');
    }
    if ($domain === '') {
        return birkly_mail_result('failed', 'mailgun', $connectionId, $alias, 'Domain is missing.');
    }

    $base = ($region === 'eu') ? 'https://api.eu.mailgun.net' : 'https://api.mailgun.net';
    $url = $base . '/v3/' . urlencode($domain) . '/messages';

    $to = $payload['to'] ?? [];
    $from = birkly_mail_encode_address($payload['from_name'] ?? '', $payload['from'] ?? '');

    $body = [
        'from' => $from,
        'to' => implode(', ', $to),
        'subject' => $payload['subject'] ?? '',
    ];
    if (!empty($payload['html'])) {
        $body['html'] = $payload['html'];
    }
    if (!empty($payload['text'])) {
        $body['text'] = $payload['text'];
    }
    if (!empty($payload['reply_to'])) {
        $body['h:Reply-To'] = $payload['reply_to'];
    }

    $result = birkly_mail_http_post($url, $body, [
        'Authorization: Basic ' . base64_encode('api:' . $apiKey),
    ], true);

    return birkly_mail_map_http_status($result, 'mailgun', $connectionId, $alias);
}
