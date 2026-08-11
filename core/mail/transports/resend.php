<?php
/**
 * Thin HTTP adapter for Resend (https://resend.com).
 */

declare(strict_types=1);

function birkly_mail_transport_resend(array $payload, array $connection): array
{
    $secrets = is_array($connection['secrets'] ?? null) ? $connection['secrets'] : [];
    $apiKey = (string) ($secrets['api_key'] ?? '');
    $config = is_array($connection['config'] ?? null) ? $connection['config'] : [];
    $region = (string) ($config['region'] ?? '');

    $connectionId = $connection['id'] ?? null;
    $alias = $connection['alias'] ?? null;

    if ($apiKey === '') {
        return birkly_mail_result('failed', 'resend', $connectionId, $alias, 'API key is missing.');
    }

    $to = $payload['to'] ?? [];
    $from = birkly_mail_encode_address($payload['from_name'] ?? '', $payload['from'] ?? '');

    $body = [
        'from' => $from,
        'to' => count($to) === 1 ? $to[0] : $to,
        'subject' => $payload['subject'] ?? '',
    ];
    if (!empty($payload['html'])) {
        $body['html'] = $payload['html'];
    }
    if (!empty($payload['text'])) {
        $body['text'] = $payload['text'];
    }
    if (!empty($payload['reply_to'])) {
        $body['reply_to'] = $payload['reply_to'];
    }
    if (!empty($payload['tags'])) {
        $body['tags'] = $payload['tags'];
    }

    $url = 'https://api.resend.com/emails';
    if ($region !== '') {
        // Resend does not use regional endpoints; this key is accepted for completeness.
        $url = 'https://api.resend.com/emails';
    }

    $result = birkly_mail_http_post($url, $body, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ]);

    return birkly_mail_map_http_status($result, 'resend', $connectionId, $alias);
}
