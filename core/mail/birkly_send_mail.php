<?php
/**
 * Central Birkly mail pipeline (Phase 65).
 *
 * Sends email through connection-registry connections with honest status reporting:
 * queued, accepted, logged, failed, suppressed.
 */

declare(strict_types=1);

require_once __DIR__ . '/../connections/registry.php';
require_once __DIR__ . '/transports/http.php';
require_once __DIR__ . '/transports/log.php';
require_once __DIR__ . '/transports/mail.php';
require_once __DIR__ . '/transports/smtp.php';
require_once __DIR__ . '/transports/resend.php';
require_once __DIR__ . '/transports/postmark.php';
require_once __DIR__ . '/transports/ses.php';
require_once __DIR__ . '/transports/mailgun.php';

/**
 * Send an email using a configured connection.
 *
 * @param array $envelope {
 *   string|string[] $to
 *   string $subject
 *   string $html
 *   string|null $text
 *   string|null $from
 *   string|null $from_name
 *   string|null $reply_to
 *   array $headers
 *   array $tags
 *   array $metadata
 * }
 * @param string|null $connectionAlias Optional alias; uses default email connection when null.
 * @return array{status: string, provider: string, connection_id: string|null, connection_alias: string|null, message_id: string|null, error: string|null, details: array}
 */
function birkly_send_mail(array $envelope, ?string $connectionAlias = null): array
{
    $to = $envelope['to'] ?? [];
    if (is_string($to)) {
        $to = array_filter(array_map('trim', explode(',', $to)));
    }
    $to = array_values(array_filter($to, 'is_string'));

    if (empty($to)) {
        return birkly_mail_result('failed', 'mail', null, null, 'Recipient (to) is required.');
    }

    foreach ($to as $addr) {
        if (!filter_var($addr, FILTER_VALIDATE_EMAIL)) {
            return birkly_mail_result('failed', 'mail', null, null, 'Invalid recipient email: ' . $addr);
        }
    }

    $subject = trim((string) ($envelope['subject'] ?? ''));
    if ($subject === '') {
        return birkly_mail_result('failed', 'mail', null, null, 'Subject is required.');
    }

    $html = (string) ($envelope['html'] ?? '');
    $text = (string) ($envelope['text'] ?? '');
    if ($html === '' && $text === '') {
        return birkly_mail_result('failed', 'mail', null, null, 'Message body (html or text) is required.');
    }

    $connection = null;
    if ($connectionAlias !== null && $connectionAlias !== '') {
        $connection = birkly_connection_get_by_alias($connectionAlias, true);
        if ($connection === null) {
            return birkly_mail_result('failed', 'mail', null, null, 'Connection alias not found: ' . $connectionAlias);
        }
    }

    if ($connection === null) {
        $connection = birkly_connection_get_default('email', true);
    }

    if ($connection === null) {
        // No configured connection: fall back to native mail() with a default From.
        return birkly_send_mail_native($to, $subject, $html, $text, $envelope);
    }

    $connection = birkly_connection_decrypt_for_use($connection);
    $provider = (string) ($connection['provider'] ?? 'mail');
    $fromEmail = (string) ($envelope['from'] ?? $connection['from_email'] ?? birkly_mail_default_from());
    $fromName = trim((string) ($envelope['from_name'] ?? $connection['from_name'] ?? 'Birkly'));
    $replyTo = (string) ($envelope['reply_to'] ?? $fromEmail);
    $headers = is_array($envelope['headers'] ?? null) ? $envelope['headers'] : [];
    $tags = is_array($envelope['tags'] ?? null) ? $envelope['tags'] : [];
    $metadata = is_array($envelope['metadata'] ?? null) ? $envelope['metadata'] : [];

    $payload = [
        'to' => $to,
        'subject' => $subject,
        'html' => $html,
        'text' => $text,
        'from' => $fromEmail,
        'from_name' => $fromName,
        'reply_to' => $replyTo,
        'headers' => $headers,
        'tags' => $tags,
        'metadata' => $metadata,
    ];

    $result = match ($provider) {
        'log' => birkly_mail_transport_log($payload, $connection),
        'mail' => birkly_mail_transport_mail($payload, $connection),
        'smtp' => birkly_mail_transport_smtp($payload, $connection),
        'resend' => birkly_mail_transport_resend($payload, $connection),
        'postmark' => birkly_mail_transport_postmark($payload, $connection),
        'ses' => birkly_mail_transport_ses($payload, $connection),
        'mailgun' => birkly_mail_transport_mailgun($payload, $connection),
        default => birkly_mail_result('failed', $provider, $connection['id'] ?? null, $connection['alias'] ?? null, 'Unsupported provider: ' . $provider),
    };

    $event = $result['status'] === 'failed' ? 'mail_failed' : 'mail_sent';
    birkly_connection_audit_log($event, [
        'connection_id' => $connection['id'] ?? null,
        'alias' => $connection['alias'] ?? null,
        'provider' => $provider,
        'status' => $result['status'],
        'to' => implode(', ', $to),
        'subject' => $subject,
        'error' => $result['error'] ?? null,
    ]);

    return $result;
}

function birkly_mail_result(
    string $status,
    string $provider,
    ?string $connectionId,
    ?string $connectionAlias,
    ?string $error = null,
    array $details = []
): array {
    return [
        'status' => $status,
        'provider' => $provider,
        'connection_id' => $connectionId,
        'connection_alias' => $connectionAlias,
        'message_id' => $details['message_id'] ?? null,
        'error' => $error,
        'details' => $details,
    ];
}

function birkly_mail_default_from(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'birkly.local';
    $host = preg_replace('/:\d+$/', '', $host) ?: 'birkly.local';
    return 'noreply@' . $host;
}

function birkly_mail_default_from_name(): string
{
    return 'Birkly';
}

function birkly_mail_encode_address(string $name, string $email): string
{
    $name = trim(str_replace(["\r", "\n"], '', $name));
    if ($name === '') {
        return '<' . $email . '>';
    }
    if (preg_match('/^[A-Za-z0-9 _.-]+$/', $name)) {
        return $name . ' <' . $email . '>';
    }
    return '"=?UTF-8?B?' . base64_encode($name) . '?=" <' . $email . '>';
}

function birkly_mail_encode_header(string $value): string
{
    $value = str_replace(["\r", "\n"], '', $value);
    if (preg_match('/^[\x20-\x7E]*$/', $value)) {
        return $value;
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function birkly_send_mail_native(array $to, string $subject, string $html, string $text, array $envelope): array
{
    $from = (string) ($envelope['from'] ?? birkly_mail_default_from());
    $fromName = trim((string) ($envelope['from_name'] ?? birkly_mail_default_from_name()));

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . birkly_mail_encode_address($fromName, $from),
        'Reply-To: ' . $from,
    ];

    if (!function_exists('mail')) {
        return birkly_mail_result('failed', 'mail', null, null, 'PHP mail() is not available.');
    }

    $ok = @mail($to[0], $subject, $html !== '' ? $html : $text, implode("\r\n", $headers));
    $status = $ok ? 'accepted' : 'failed';
    $error = $ok ? null : 'PHP mail() returned false.';

    return birkly_mail_result($status, 'mail', null, null, $error);
}
