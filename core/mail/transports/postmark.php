<?php
/**
 * Thin HTTP adapter for Postmark (https://postmarkapp.com).
 */

declare(strict_types=1);

function birkly_mail_transport_postmark(array $payload, array $connection): array
{
    $secrets = is_array($connection['secrets'] ?? null) ? $connection['secrets'] : [];
    $apiKey = (string) ($secrets['api_key'] ?? '');
    $connectionId = $connection['id'] ?? null;
    $alias = $connection['alias'] ?? null;

    if ($apiKey === '') {
        return birkly_mail_result('failed', 'postmark', $connectionId, $alias, 'API key is missing.');
    }

    $to = $payload['to'] ?? [];
    $from = birkly_mail_encode_address($payload['from_name'] ?? '', $payload['from'] ?? '');

    $body = [
        'From' => $from,
        'To' => implode(', ', $to),
        'Subject' => $payload['subject'] ?? '',
    ];
    if (!empty($payload['html'])) {
        $body['HtmlBody'] = $payload['html'];
    }
    if (!empty($payload['text'])) {
        $body['TextBody'] = $payload['text'];
    }
    if (!empty($payload['reply_to'])) {
        $body['ReplyTo'] = $payload['reply_to'];
    }
    if (!empty($payload['tags'])) {
        $body['Tag'] = is_array($payload['tags']) ? $payload['tags'][0] : $payload['tags'];
    }
    $attachments = $payload['attachments'] ?? [];
    if (is_array($attachments) && $attachments !== []) {
        $body['Attachments'] = [];
        foreach ($attachments as $att) {
            $body['Attachments'][] = [
                'Name' => $att['filename'],
                'Content' => base64_encode($att['content']),
                'ContentType' => $att['mime_type'],
            ];
        }
    }

    $result = birkly_mail_http_post('https://api.postmarkapp.com/email', $body, [
        'X-Postmark-Server-Token: ' . $apiKey,
        'Content-Type: application/json',
    ]);

    return birkly_mail_map_http_status($result, 'postmark', $connectionId, $alias);
}
