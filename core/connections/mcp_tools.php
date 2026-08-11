<?php
/**
 * MCP tool implementations for Birkly Connections (Phase 65 M12).
 *
 * These tools are approval-gated via the AI tool registry (write category).
 * They never echo decrypted secrets in their output.
 */

declare(strict_types=1);

require_once __DIR__ . '/registry.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/../mail/birkly_send_mail.php';
require_once __DIR__ . '/../mail/channels.php';
require_once __DIR__ . '/../mail/queue.php';

function birkly_connections_mcp_health(array $arguments): array
{
    $typeFilter = (string) ($arguments['type'] ?? '');
    $connections = birkly_connections_list(false);

    $counts = [];
    $defaults = [];
    foreach ($connections as $connection) {
        $type = (string) ($connection['type'] ?? 'unknown');
        $provider = (string) ($connection['provider'] ?? 'unknown');
        if ($typeFilter !== '' && $type !== $typeFilter) {
            continue;
        }
        $key = $type . '/' . $provider;
        $counts[$key] = ($counts[$key] ?? 0) + 1;
        if (!empty($connection['is_default'])) {
            $defaults[$type] = $connection['alias'] ?? $connection['id'] ?? '';
        }
    }

    $queueCounts = birkly_mail_queue_count();
    $auditFile = birkly_project_root() . '/logs/connections-audit.jsonl';
    $recentFailures = 0;
    if (is_readable($auditFile)) {
        $lines = array_filter(array_map('trim', file($auditFile) ?: []));
        $since = time() - 86400;
        foreach (array_slice($lines, -100) as $line) {
            $entry = json_decode($line, true);
            if (!is_array($entry)) {
                continue;
            }
            $event = (string) ($entry['event'] ?? '');
            $created = strtotime((string) ($entry['created_at'] ?? '')) ?: 0;
            if ($created >= $since && in_array($event, ['mail_failed', 'connection_tested'], true) && (($entry['data']['status'] ?? '') === 'failed' || ($entry['data']['status'] ?? '') === 'error')) {
                $recentFailures++;
            }
        }
    }

    return [
        'status' => 'ok',
        'summary' => [
            'total_connections' => count($connections),
            'counts_by_type_provider' => $counts,
            'default_connections' => $defaults,
            'mail_queue' => $queueCounts,
            'recent_failures_24h' => $recentFailures,
        ],
    ];
}

function birkly_connections_mcp_configure(array $arguments): array
{
    $operation = strtolower(trim((string) ($arguments['operation'] ?? '')));
    $connectionData = is_array($arguments['connection'] ?? null) ? $arguments['connection'] : [];

    if ($operation === '') {
        return ['status' => 'error', 'error' => 'operation is required (create, update, or delete).'];
    }

    if ($operation === 'delete') {
        $id = (string) ($connectionData['id'] ?? '');
        $alias = (string) ($connectionData['alias'] ?? '');
        if ($id === '' && $alias === '') {
            return ['status' => 'error', 'error' => 'Connection id or alias is required to delete.'];
        }
        $target = $id !== '' ? birkly_connection_get($id, false) : birkly_connection_get_by_alias($alias, false);
        if ($target === null) {
            return ['status' => 'error', 'error' => 'Connection not found.'];
        }
        $result = birkly_connection_delete($target['id']);
        if (!$result['ok']) {
            return ['status' => 'error', 'error' => $result['errors'] ?? 'Delete failed.'];
        }
        return ['status' => 'ok', 'action' => 'deleted', 'id' => $target['id']];
    }

    if ($operation === 'create') {
        $result = birkly_connection_create($connectionData);
        if (!$result['ok']) {
            return ['status' => 'error', 'error' => $result['errors'] ?? 'Create failed.'];
        }
        return ['status' => 'ok', 'action' => 'created', 'connection' => birkly_connection_mask_secrets($result['connection'])];
    }

    if ($operation === 'update') {
        $id = (string) ($connectionData['id'] ?? '');
        $alias = (string) ($connectionData['alias'] ?? '');
        if ($id === '' && $alias === '') {
            return ['status' => 'error', 'error' => 'Connection id or alias is required to update.'];
        }
        $target = $id !== '' ? birkly_connection_get($id, false) : birkly_connection_get_by_alias($alias, false);
        if ($target === null) {
            return ['status' => 'error', 'error' => 'Connection not found.'];
        }
        $result = birkly_connection_update($target['id'], $connectionData);
        if (!$result['ok']) {
            return ['status' => 'error', 'error' => $result['errors'] ?? 'Update failed.'];
        }
        return ['status' => 'ok', 'action' => 'updated', 'connection' => birkly_connection_mask_secrets($result['connection'])];
    }

    return ['status' => 'error', 'error' => 'Unsupported operation: ' . $operation];
}

function birkly_connections_mcp_test(array $arguments): array
{
    $alias = (string) ($arguments['alias'] ?? '');
    if ($alias === '') {
        return ['status' => 'error', 'error' => 'Connection alias is required.'];
    }

    $connection = birkly_connection_get_by_alias($alias, true);
    if ($connection === null) {
        return ['status' => 'error', 'error' => 'Connection not found: ' . $alias];
    }

    $type = (string) ($connection['type'] ?? '');
    $provider = (string) ($connection['provider'] ?? '');
    $result = null;

    if ($type === 'email') {
        $testEmail = (string) ($arguments['test_email'] ?? '');
        if ($testEmail === '' || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'error', 'error' => 'A valid test_email is required for email connections.'];
        }
        $result = birkly_send_mail([
            'to' => $testEmail,
            'subject' => 'Birkly MCP connection test',
            'html' => '<p>This is a test message from the Birkly MCP connection test tool.</p>',
            'text' => 'This is a test message from the Birkly MCP connection test tool.',
        ], $alias);
    } elseif ($type === 'http_credential') {
        $baseUrl = (string) ($connection['config']['base_url'] ?? '');
        if ($baseUrl === '') {
            return ['status' => 'error', 'error' => 'HTTP credential has no base URL.'];
        }
        $result = birkly_http_request($alias, 'GET', $baseUrl);
    } elseif ($type === 'channel') {
        $payload = [
            'subject' => 'Birkly MCP channel test',
            'text' => 'This is a test notification from the Birkly MCP connection test tool.',
        ];
        if ($provider === 'twilio_sms') {
            $toNumber = (string) ($arguments['to_number'] ?? '');
            if ($toNumber === '') {
                return ['status' => 'error', 'error' => 'to_number is required for Twilio SMS tests.'];
            }
            $payload['to_number'] = $toNumber;
        }
        $result = birkly_channel_dispatch($payload, $connection);
    } else {
        return ['status' => 'error', 'error' => 'Unsupported connection type for testing: ' . $type];
    }

    birkly_connection_audit_log('connection_tested', [
        'alias' => $alias,
        'type' => $type,
        'provider' => $provider,
        'status' => $result['status'] ?? 'unknown',
        'error' => $result['error'] ?? null,
    ]);

    return [
        'status' => 'ok',
        'alias' => $alias,
        'type' => $type,
        'provider' => $provider,
        'send_result' => [
            'status' => $result['status'] ?? 'unknown',
            'error' => $result['error'] ?? null,
        ],
    ];
}

function birkly_connections_mcp_result_to_content(array $result): array
{
    return ['content' => [['type' => 'text', 'text' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)]]];
}
