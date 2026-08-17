<?php
/**
 * Log-only mail transport for local development and testing.
 *
 * Status is always 'logged' — never 'sent'.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../birkly_paths.php';

function birkly_mail_transport_log(array $payload, array $connection): array
{
    $logDir = birkly_project_root() . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }

    $entry = [
        'timestamp' => date('c'),
        'connection_id' => $connection['id'] ?? null,
        'alias' => $connection['alias'] ?? null,
        'provider' => 'log',
        'to' => $payload['to'] ?? [],
        'from' => $payload['from'] ?? '',
        'subject' => $payload['subject'] ?? '',
        'body_preview' => substr(strip_tags((string) ($payload['html'] ?? $payload['text'] ?? '')), 0, 240),
        'attachments' => array_map(static fn(array $att): string => (string) ($att['filename'] ?? ''), is_array($payload['attachments'] ?? null) ? $payload['attachments'] : []),
        'tags' => $payload['tags'] ?? [],
        'metadata' => $payload['metadata'] ?? [],
    ];

    $file = $logDir . '/mail.jsonl';
    file_put_contents($file, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);

    return birkly_mail_result('logged', 'log', $connection['id'] ?? null, $connection['alias'] ?? null, null, [
        'logged_at' => $entry['timestamp'],
    ]);
}
