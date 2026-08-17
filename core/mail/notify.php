<?php
/**
 * Notification fan-out helper for Birkly.
 *
 * Routes an event to all active connections whose alias matches the event
 * (exact match or the reserved global alias '*'). Email-type connections are
 * dispatched through birkly_send_mail; http_credential connections are
 * dispatched as outbound webhook POSTs.
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_send_mail.php';
require_once __DIR__ . '/../connections/http.php';
require_once __DIR__ . '/channels.php';

/**
 * Send a notification for an event.
 *
 * @param string $event Event name / alias to match against connection aliases.
 * @param array $payload {
 *   string|string[] $to
 *   string $subject
 *   string $message   Plain-text message body
 *   string|null $html  Optional HTML body
 *   string|null $from
 *   string|null $from_name
 *   string|null $reply_to
 *   string|null $url  Optional webhook URL override for HTTP connections.
 *   array $tags
 *   array $metadata
 *   array $headers  Extra headers for HTTP webhook dispatches.
 * }
 * @return array<string, array> Per-connection-alias result arrays.
 */
function birkly_notify(string $event, array $payload): array
{
    $event = birkly_connections_sanitize_alias($event);
    if ($event === '') {
        return ['_notify' => birkly_mail_result('failed', 'notify', null, null, 'Event name is required.')];
    }

    $connections = birkly_connections_match_event($event);
    if (empty($connections)) {
        // Fall back to the default email connection when no alias matches — so form
        // notifications and other events work once any email connection is configured.
        $hasEmailPayload = trim((string) ($payload['subject'] ?? '')) !== ''
            && (trim((string) ($payload['message'] ?? '')) !== '' || trim((string) ($payload['html'] ?? '')) !== '');
        if ($hasEmailPayload) {
            $defaultEmail = birkly_connection_get_default('email', false);
            if ($defaultEmail !== null) {
                $connections = [$defaultEmail];
            }
        }
    }
    if (empty($connections)) {
        return ['_notify' => birkly_mail_result('failed', 'notify', null, null, 'No active connections match event: ' . $event)];
    }

    $results = [];
    foreach ($connections as $connection) {
        $alias = $connection['alias'] ?? '';
        $type = $connection['type'] ?? '';

        if ($type === 'email') {
            $results[$alias] = birkly_notify_send_email($payload, $connection);
        } elseif ($type === 'http_credential') {
            $results[$alias] = birkly_notify_send_http($payload, $connection);
        } elseif ($type === 'channel') {
            $channelConnection = birkly_connection_get($connection['id'] ?? '', true);
            $results[$alias] = birkly_channel_dispatch($payload, $channelConnection ?? $connection);
        } else {
            $results[$alias] = birkly_mail_result('failed', 'notify', $connection['id'] ?? null, $alias, 'Unsupported connection type: ' . $type);
        }

        birkly_connection_audit_log('notify_dispatched', [
            'event' => $event,
            'connection_id' => $connection['id'] ?? null,
            'alias' => $alias,
            'type' => $type,
            'status' => is_array($results[$alias]) ? ($results[$alias]['status'] ?? 'unknown') : 'unknown',
            'error' => $results[$alias]['error'] ?? null,
        ]);
    }

    return $results;
}

function birkly_notify_send_email(array $payload, array $connection): array
{
    $to = $payload['to'] ?? [];
    $subject = trim((string) ($payload['subject'] ?? ''));
    $text = (string) ($payload['message'] ?? '');
    $html = (string) ($payload['html'] ?? '');

    if ($text === '' && $html === '') {
        return birkly_mail_result('failed', 'mail', $connection['id'] ?? null, $connection['alias'] ?? null, 'Notification message or html is required.');
    }

    return birkly_send_mail([
        'to' => $to,
        'subject' => $subject,
        'html' => $html,
        'text' => $text,
        'from' => $payload['from'] ?? null,
        'from_name' => $payload['from_name'] ?? null,
        'reply_to' => $payload['reply_to'] ?? null,
        'tags' => is_array($payload['tags'] ?? null) ? $payload['tags'] : [],
        'metadata' => array_merge(
            ['source' => 'birkly_notify', 'event_alias' => ($connection['alias'] ?? null)],
            is_array($payload['metadata'] ?? null) ? $payload['metadata'] : []
        ),
    ], $connection['alias'] ?? null);
}

function birkly_notify_results_any_success(array $results): bool
{
    foreach ($results as $result) {
        if (is_array($result) && in_array($result['status'] ?? '', ['queued', 'accepted', 'logged'], true)) {
            return true;
        }
    }
    return false;
}

function birkly_notify_send_http(array $payload, array $connection): array
{
    $alias = $connection['alias'] ?? '';
    $config = is_array($connection['config'] ?? null) ? $connection['config'] : [];
    $url = (string) ($payload['url'] ?? ($config['base_url'] ?? ''));
    if ($url === '') {
        return birkly_mail_result('failed', 'http', $connection['id'] ?? null, $alias, 'No webhook URL configured.');
    }

    $webhookPayload = [
        'event' => $payload['event'] ?? null,
        'to' => $payload['to'] ?? null,
        'subject' => $payload['subject'] ?? null,
        'message' => $payload['message'] ?? null,
        'html' => $payload['html'] ?? null,
        'metadata' => $payload['metadata'] ?? [],
        'dispatched_at' => date('c'),
    ];

    $result = birkly_http_request($alias, 'POST', $url, $webhookPayload);
    return birkly_mail_result(
        $result['status'] === 'accepted' ? 'accepted' : 'failed',
        'http',
        $connection['id'] ?? null,
        $alias,
        $result['error'],
        ['http_code' => $result['http_code'] ?? 0, 'response' => $result['response'] ?? '']
    );
}
