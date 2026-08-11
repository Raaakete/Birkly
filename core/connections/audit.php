<?php
/**
 * Audit logging for the connection registry and central mail pipeline.
 *
 * Events are written as JSON Lines to logs/connections-audit.jsonl.
 */

declare(strict_types=1);

require_once __DIR__ . '/../birkly_paths.php';

function birkly_connection_audit_dir(): string
{
    $dir = birkly_project_root() . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    return $dir;
}

function birkly_connection_audit_file(): string
{
    return birkly_connection_audit_dir() . '/connections-audit.jsonl';
}

function birkly_connection_audit_actor(): string
{
    if (isset($_SESSION['user']['email']) && is_string($_SESSION['user']['email']) && $_SESSION['user']['email'] !== '') {
        return $_SESSION['user']['email'];
    }
    if (isset($_SESSION['user']['id']) && is_string($_SESSION['user']['id']) && $_SESSION['user']['id'] !== '') {
        return $_SESSION['user']['id'];
    }
    return 'system';
}

/**
 * Append an audit event. Failures are silently logged to PHP error_log so audit
 * errors cannot break the calling operation.
 */
function birkly_connection_audit_log(string $event, array $details = [], ?string $actor = null): void
{
    try {
        $entry = [
            'timestamp' => date('c'),
            'event' => $event,
            'actor' => $actor ?? birkly_connection_audit_actor(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? ($_SERVER['SERVER_ADDR'] ?? 'cli'),
            'details' => $details,
        ];
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        file_put_contents(birkly_connection_audit_file(), $line, FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) {
        error_log('[connections:audit] failed to write audit log: ' . $e->getMessage());
    }
}
