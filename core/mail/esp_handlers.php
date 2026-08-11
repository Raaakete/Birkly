<?php
/**
 * ESP inbound webhook handlers for Birkly Phase 65.
 *
 * Parses bounce/complaint/unsubscribe events from Resend, Postmark,
 * Amazon SES (SNS), and Mailgun, verifies signatures when configured,
 * updates the suppression list, and writes the delivery log.
 */

declare(strict_types=1);

require_once __DIR__ . '/../birkly_paths.php';
require_once __DIR__ . '/suppressions.php';
require_once __DIR__ . '/queue.php';

function birkly_esp_webhook_handle(array $connection, array $headers, string $rawBody): array
{
    $provider = (string) ($connection['provider'] ?? '');

    return match ($provider) {
        'resend' => birkly_esp_resend_handle($connection, $headers, $rawBody),
        'postmark' => birkly_esp_postmark_handle($connection, $headers, $rawBody),
        'ses' => birkly_esp_ses_handle($connection, $headers, $rawBody),
        'mailgun' => birkly_esp_mailgun_handle($connection, $headers, $rawBody),
        default => ['status' => 'error', 'error' => 'Unsupported ESP provider: ' . $provider],
    };
}

function birkly_esp_signing_secret(array $connection): string
{
    $secrets = is_array($connection['secrets'] ?? null) ? $connection['secrets'] : [];
    return (string) ($secrets['signing_secret'] ?? '');
}

function birkly_esp_header(array $headers, string $name): ?string
{
    foreach ($headers as $key => $value) {
        if (strtolower((string) $key) === strtolower($name)) {
            return is_array($value) ? implode(',', $value) : (string) $value;
        }
    }
    return null;
}

function birkly_esp_suppress_event(string $email, string $type, string $provider, array $context): array
{
    birkly_mail_suppression_add($email, $type, $provider);

    $log = [
        'event' => $type,
        'provider' => $provider,
        'email' => $email,
        'status' => 'suppressed',
        'context' => $context,
    ];
    birkly_mail_delivery_log($log);

    return [
        'email' => $email,
        'type' => $type,
        'action' => 'suppressed',
        'provider' => $provider,
    ];
}

// ------------------------------------------------------------------
// Resend
// ------------------------------------------------------------------

function birkly_esp_resend_handle(array $connection, array $headers, string $rawBody): array
{
    $secret = birkly_esp_signing_secret($connection);
    if ($secret !== '') {
        $signature = birkly_esp_header($headers, 'Resend-Signature');
        if ($signature === null || $signature === '') {
            return ['status' => 'error', 'error' => 'Missing Resend-Signature.'];
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        if (!hash_equals($expected, $signature)) {
            return ['status' => 'error', 'error' => 'Invalid Resend signature.'];
        }
    }

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        return ['status' => 'error', 'error' => 'Invalid JSON body.'];
    }

    $type = (string) ($payload['type'] ?? '');
    $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
    $email = (string) ($data['email'] ?? '');

    $events = [];
    if (str_contains($type, 'bounce')) {
        $events[] = birkly_esp_suppress_event($email, 'bounce', 'resend', $data);
    } elseif (str_contains($type, 'complaint')) {
        $events[] = birkly_esp_suppress_event($email, 'complaint', 'resend', $data);
    } elseif (str_contains($type, 'delivered')) {
        birkly_mail_delivery_log([
            'event' => 'delivered',
            'provider' => 'resend',
            'email' => $email,
            'status' => 'delivered',
            'context' => $data,
        ]);
        $events[] = ['email' => $email, 'type' => 'delivered', 'action' => 'logged', 'provider' => 'resend'];
    }

    return ['status' => 'ok', 'provider' => 'resend', 'events' => $events];
}

// ------------------------------------------------------------------
// Postmark
// ------------------------------------------------------------------

function birkly_esp_postmark_handle(array $connection, array $headers, string $rawBody): array
{
    $secret = birkly_esp_signing_secret($connection);
    if ($secret !== '') {
        $signature = birkly_esp_header($headers, 'X-Postmark-Signature');
        if ($signature === null || $signature === '') {
            return ['status' => 'error', 'error' => 'Missing X-Postmark-Signature.'];
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        if (!hash_equals($expected, $signature)) {
            return ['status' => 'error', 'error' => 'Invalid Postmark signature.'];
        }
    }

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        return ['status' => 'error', 'error' => 'Invalid JSON body.'];
    }

    $type = (string) ($payload['Type'] ?? $payload['type'] ?? '');
    $email = (string) ($payload['Email'] ?? $payload['email'] ?? '');
    $events = [];

    if (stripos($type, 'Bounce') !== false) {
        $events[] = birkly_esp_suppress_event($email, 'bounce', 'postmark', $payload);
    } elseif (stripos($type, 'SpamComplaint') !== false || stripos($type, 'Complaint') !== false) {
        $events[] = birkly_esp_suppress_event($email, 'complaint', 'postmark', $payload);
    } elseif (stripos($type, 'SubscriptionChange') !== false) {
        $suppress = (bool) ($payload['SuppressSending'] ?? $payload['suppress_sending'] ?? false);
        if ($suppress) {
            $events[] = birkly_esp_suppress_event($email, 'unsubscribe', 'postmark', $payload);
        } else {
            birkly_mail_suppression_remove($email);
            $events[] = ['email' => $email, 'type' => 'unsubscribe', 'action' => 'removed', 'provider' => 'postmark'];
        }
    } else {
        $events[] = ['email' => $email, 'type' => $type, 'action' => 'ignored', 'provider' => 'postmark'];
    }

    return ['status' => 'ok', 'provider' => 'postmark', 'events' => $events];
}

// ------------------------------------------------------------------
// Amazon SES (SNS)
// ------------------------------------------------------------------

function birkly_esp_ses_handle(array $connection, array $headers, string $rawBody): array
{
    $secret = birkly_esp_signing_secret($connection);
    // SES/SNS signatures are not verified here; verify in production via SNS cert.
    if ($secret !== '') {
        // If a secret is configured, require a matching X-Birkly-Signature for tests.
        $signature = birkly_esp_header($headers, 'X-Birkly-Signature');
        if ($signature === null || $signature === '') {
            return ['status' => 'error', 'error' => 'Missing signature.'];
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        if (!hash_equals($expected, $signature)) {
            return ['status' => 'error', 'error' => 'Invalid signature.'];
        }
    }

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        return ['status' => 'error', 'error' => 'Invalid JSON body.'];
    }

    // SNS notifications wrap the SES content in a "Message" string.
    $message = $payload;
    if (isset($payload['Message']) && is_string($payload['Message'])) {
        $decoded = json_decode($payload['Message'], true);
        if (is_array($decoded)) {
            $message = $decoded;
        }
    }

    $notificationType = (string) ($message['notificationType'] ?? $message['eventType'] ?? '');
    $events = [];

    if (stripos($notificationType, 'Bounce') !== false) {
        $bounced = is_array($message['bounce']['bouncedRecipients'] ?? null) ? $message['bounce']['bouncedRecipients'] : [];
        foreach ($bounced as $recipient) {
            $email = (string) ($recipient['emailAddress'] ?? '');
            if ($email !== '') {
                $events[] = birkly_esp_suppress_event($email, 'bounce', 'ses', $message);
            }
        }
    } elseif (stripos($notificationType, 'Complaint') !== false) {
        $complained = is_array($message['complaint']['complainedRecipients'] ?? null) ? $message['complaint']['complainedRecipients'] : [];
        foreach ($complained as $recipient) {
            $email = (string) ($recipient['emailAddress'] ?? '');
            if ($email !== '') {
                $events[] = birkly_esp_suppress_event($email, 'complaint', 'ses', $message);
            }
        }
    } else {
        $events[] = ['type' => $notificationType, 'action' => 'ignored', 'provider' => 'ses'];
    }

    return ['status' => 'ok', 'provider' => 'ses', 'events' => $events];
}

// ------------------------------------------------------------------
// Mailgun
// ------------------------------------------------------------------

function birkly_esp_mailgun_handle(array $connection, array $headers, string $rawBody): array
{
    $contentType = birkly_esp_header($headers, 'Content-Type') ?? '';
    $secret = birkly_esp_signing_secret($connection);

    if (stripos($contentType, 'application/x-www-form-urlencoded') !== false) {
        parse_str($rawBody, $payload);
    } else {
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            $payload = [];
        }
    }

    if ($secret !== '') {
        $timestamp = (string) ($payload['timestamp'] ?? '');
        $token = (string) ($payload['token'] ?? '');
        $signature = (string) ($payload['signature'] ?? '');
        if ($timestamp === '' || $token === '' || $signature === '') {
            return ['status' => 'error', 'error' => 'Missing Mailgun signature fields.'];
        }
        $expected = hash_hmac('sha256', $timestamp . $token, $secret);
        if (!hash_equals($expected, $signature)) {
            return ['status' => 'error', 'error' => 'Invalid Mailgun signature.'];
        }
    }

    $event = (string) ($payload['event'] ?? $payload['Event'] ?? '');
    $email = (string) ($payload['recipient'] ?? $payload['Recipient'] ?? '');
    $events = [];

    if (in_array($event, ['bounced', 'bounce', 'failed'], true)) {
        $events[] = birkly_esp_suppress_event($email, 'bounce', 'mailgun', $payload);
    } elseif (in_array($event, ['complained', 'complaint', 'spam'], true)) {
        $events[] = birkly_esp_suppress_event($email, 'complaint', 'mailgun', $payload);
    } elseif (in_array($event, ['unsubscribed', 'unsubscribe'], true)) {
        $events[] = birkly_esp_suppress_event($email, 'unsubscribe', 'mailgun', $payload);
    } else {
        $events[] = ['email' => $email, 'type' => $event, 'action' => 'ignored', 'provider' => 'mailgun'];
    }

    return ['status' => 'ok', 'provider' => 'mailgun', 'events' => $events];
}
