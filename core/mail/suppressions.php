<?php
/**
 * Email suppression list for Birkly Phase 65.
 *
 * Tracks hard bounces, spam complaints, and unsubscribes so the mail pipeline
 * can avoid re-sending to suppressed addresses.
 */

declare(strict_types=1);

require_once __DIR__ . '/../birkly_paths.php';

function birkly_mail_suppressions_file(): string
{
    $dir = birkly_project_root() . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    return $dir . '/suppressions.jsonl';
}

function birkly_mail_suppression_key(string $email): string
{
    return strtolower(trim($email));
}

function birkly_mail_suppressions_load(): array
{
    $file = birkly_mail_suppressions_file();
    if (!is_readable($file)) {
        return [];
    }

    $suppressions = [];
    $handle = @fopen($file, 'r');
    if ($handle === false) {
        return [];
    }
    while (($line = fgets($handle)) !== false) {
        $entry = json_decode(trim($line), true);
        if (!is_array($entry) || empty($entry['email'])) {
            continue;
        }
        $key = birkly_mail_suppression_key((string) $entry['email']);
        $suppressions[$key] = $entry;
    }
    fclose($handle);
    return $suppressions;
}

function birkly_mail_suppression_check(string $email): ?array
{
    $suppressions = birkly_mail_suppressions_load();
    $key = birkly_mail_suppression_key($email);
    return $suppressions[$key] ?? null;
}

function birkly_mail_suppression_add(string $email, string $reason = 'manual', ?string $source = null, array $metadata = []): bool
{
    $email = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
    if ($email === false) {
        return false;
    }

    $entry = array_merge($metadata, [
        'email' => $email,
        'reason' => $reason,
        'source' => $source ?? 'system',
        'created_at' => date('c'),
    ]);

    try {
        file_put_contents(
            birkly_mail_suppressions_file(),
            json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            FILE_APPEND | LOCK_EX
        );
        return true;
    } catch (Throwable $e) {
        error_log('[mail:suppressions] failed to add suppression: ' . $e->getMessage());
        return false;
    }
}

function birkly_mail_suppression_remove(string $email): bool
{
    $file = birkly_mail_suppressions_file();
    if (!is_readable($file)) {
        return false;
    }

    $target = birkly_mail_suppression_key($email);
    $lines = [];
    $removed = false;
    $handle = @fopen($file, 'r');
    if ($handle === false) {
        return false;
    }
    while (($line = fgets($handle)) !== false) {
        $entry = json_decode(trim($line), true);
        if (is_array($entry) && birkly_mail_suppression_key((string) ($entry['email'] ?? '')) === $target) {
            $removed = true;
            continue;
        }
        $lines[] = $line;
    }
    fclose($handle);

    if (!$removed) {
        return false;
    }

    file_put_contents($file, implode('', $lines), LOCK_EX);
    return true;
}
