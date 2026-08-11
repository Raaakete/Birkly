<?php
/**
 * Notification helper for Birkly.
 *
 * Thin wrapper around birkly_send_mail for notification-style emails.
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_send_mail.php';

/**
 * Send a notification email using the central mail pipeline.
 *
 * @param array $notification {
 *   string|string[] $to
 *   string $subject
 *   string $message   Plain-text message body
 *   string|null $html  Optional HTML body
 *   string|null $from
 *   string|null $from_name
 *   string|null $reply_to
 *   string|null $connection_alias
 *   array $tags
 *   array $metadata
 * }
 * @return array{status: string, provider: string, connection_id: string|null, connection_alias: string|null, message_id: string|null, error: string|null, details: array}
 */
function birkly_notify(array $notification): array
{
    $to = $notification['to'] ?? [];
    $subject = trim((string) ($notification['subject'] ?? ''));
    $text = (string) ($notification['message'] ?? '');
    $html = (string) ($notification['html'] ?? '');

    if ($text === '' && $html === '') {
        return birkly_mail_result('failed', 'mail', null, null, 'Notification message or html is required.');
    }

    return birkly_send_mail([
        'to' => $to,
        'subject' => $subject,
        'html' => $html,
        'text' => $text,
        'from' => $notification['from'] ?? null,
        'from_name' => $notification['from_name'] ?? null,
        'reply_to' => $notification['reply_to'] ?? null,
        'tags' => is_array($notification['tags'] ?? null) ? $notification['tags'] : [],
        'metadata' => array_merge(
            ['source' => 'birkly_notify'],
            is_array($notification['metadata'] ?? null) ? $notification['metadata'] : []
        ),
    ], $notification['connection_alias'] ?? null);
}
