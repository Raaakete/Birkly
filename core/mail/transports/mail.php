<?php
/**
 * Native PHP mail() transport.
 */

declare(strict_types=1);

function birkly_mail_transport_mail(array $payload, array $connection): array
{
    $to = $payload['to'] ?? [];
    $subject = (string) ($payload['subject'] ?? '');
    $html = (string) ($payload['html'] ?? '');
    $text = (string) ($payload['text'] ?? '');
    $from = (string) ($payload['from'] ?? '');
    $fromName = trim((string) ($payload['from_name'] ?? ''));
    $replyTo = (string) ($payload['reply_to'] ?? $from);

    if (!function_exists('mail')) {
        return birkly_mail_result('failed', 'mail', $connection['id'] ?? null, $connection['alias'] ?? null, 'PHP mail() is not available.');
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . birkly_mail_encode_address($fromName, $from),
        'Reply-To: ' . $replyTo,
    ];

    $body = $html !== '' ? $html : $text;
    $ok = @mail($to[0], $subject, $body, implode("\r\n", $headers));

    if (!$ok) {
        return birkly_mail_result('failed', 'mail', $connection['id'] ?? null, $connection['alias'] ?? null, 'PHP mail() returned false.');
    }

    return birkly_mail_result('accepted', 'mail', $connection['id'] ?? null, $connection['alias'] ?? null);
}
