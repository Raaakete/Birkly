<?php
/**
 * Durable file-based mail queue for Birkly Phase 65.
 *
 * Messages are stored as JSON Lines in logs/mail-queue.jsonl. A worker
 * processes due items with exponential backoff and writes every delivery
 * attempt to logs/mail-deliveries.jsonl.
 */

declare(strict_types=1);

require_once __DIR__ . '/../birkly_paths.php';
require_once __DIR__ . '/birkly_send_mail.php';

function birkly_mail_queue_file(): string
{
    $dir = birkly_project_root() . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    return $dir . '/mail-queue.jsonl';
}

function birkly_mail_delivery_log_file(): string
{
    $dir = birkly_project_root() . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    return $dir . '/mail-deliveries.jsonl';
}

function birkly_mail_queue_next_attempt(int $attempts): int
{
    // Exponential backoff: 30s, 2m, 8m, 32m, 2h.
    $delays = [30, 120, 480, 1920, 7200];
    $delay = $delays[$attempts] ?? end($delays);
    return time() + $delay;
}

function birkly_mail_queue_push(array $envelope, ?string $connectionAlias = null, array $options = []): array
{
    $item = [
        'id' => 'mq_' . bin2hex(random_bytes(12)),
        'envelope' => $envelope,
        'connection_alias' => $connectionAlias,
        'attempts' => 0,
        'max_attempts' => (int) ($options['max_attempts'] ?? 5),
        'next_attempt' => time(),
        'created_at' => date('c'),
        'status' => 'queued',
    ];

    try {
        file_put_contents(
            birkly_mail_queue_file(),
            json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            FILE_APPEND | LOCK_EX
        );
        return ['ok' => true, 'item' => $item];
    } catch (Throwable $e) {
        error_log('[mail:queue] failed to push: ' . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function birkly_mail_queue_load(): array
{
    $file = birkly_mail_queue_file();
    if (!is_readable($file)) {
        return [];
    }

    $items = [];
    $handle = @fopen($file, 'r');
    if ($handle === false) {
        return [];
    }
    while (($line = fgets($handle)) !== false) {
        $item = json_decode(trim($line), true);
        if (is_array($item)) {
            $items[] = $item;
        }
    }
    fclose($handle);
    return $items;
}

function birkly_mail_queue_save(array $items): bool
{
    $lines = [];
    foreach ($items as $item) {
        $lines[] = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    try {
        file_put_contents(birkly_mail_queue_file(), implode("\n", $lines) . (empty($lines) ? '' : "\n"), LOCK_EX);
        return true;
    } catch (Throwable $e) {
        error_log('[mail:queue] failed to save queue: ' . $e->getMessage());
        return false;
    }
}

function birkly_mail_delivery_log(array $entry): void
{
    try {
        file_put_contents(
            birkly_mail_delivery_log_file(),
            json_encode(array_merge(['timestamp' => date('c')], $entry), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            FILE_APPEND | LOCK_EX
        );
    } catch (Throwable $e) {
        error_log('[mail:queue] failed to write delivery log: ' . $e->getMessage());
    }
}

function birkly_mail_queue_process(?int $limit = null): array
{
    $items = birkly_mail_queue_load();
    $now = time();
    $results = [];
    $processed = 0;
    $limit = $limit ?? 50;

    foreach ($items as $i => $item) {
        if (($item['status'] ?? 'queued') !== 'queued') {
            continue;
        }
        if ((int) ($item['next_attempt'] ?? 0) > $now) {
            continue;
        }
        if ($processed >= $limit) {
            break;
        }
        $processed++;

        $item['attempts'] = ($item['attempts'] ?? 0) + 1;
        $result = birkly_send_mail($item['envelope'] ?? [], $item['connection_alias'] ?? null);

        birkly_mail_delivery_log([
            'queue_id' => $item['id'] ?? null,
            'connection_alias' => $item['connection_alias'] ?? null,
            'attempt' => $item['attempts'],
            'status' => $result['status'] ?? 'unknown',
            'error' => $result['error'] ?? null,
            'to' => implode(', ', (array) (($item['envelope']['to'] ?? []))),
            'subject' => $item['envelope']['subject'] ?? '',
        ]);

        if (in_array($result['status'] ?? '', ['accepted', 'queued', 'logged'], true)) {
            $item['status'] = 'delivered';
            $item['delivered_at'] = date('c');
            $item['result'] = $result;
        } elseif ((int) $item['attempts'] >= (int) ($item['max_attempts'] ?? 5)) {
            $item['status'] = 'failed';
            $item['failed_at'] = date('c');
            $item['result'] = $result;
        } else {
            $item['next_attempt'] = birkly_mail_queue_next_attempt((int) $item['attempts']);
            $item['last_result'] = $result;
        }

        $items[$i] = $item;
        $results[] = ['id' => $item['id'], 'status' => $item['status'], 'result' => $result];
    }

    birkly_mail_queue_save($items);
    return ['processed' => $processed, 'results' => $results];
}

function birkly_mail_queue_count(): array
{
    $items = birkly_mail_queue_load();
    $queued = 0;
    $failed = 0;
    $delivered = 0;
    foreach ($items as $item) {
        switch ($item['status'] ?? 'queued') {
            case 'queued':
                $queued++;
                break;
            case 'failed':
                $failed++;
                break;
            case 'delivered':
                $delivered++;
                break;
        }
    }
    return ['queued' => $queued, 'failed' => $failed, 'delivered' => $delivered, 'total' => count($items)];
}
