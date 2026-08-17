<?php
/**
 * Resolve which email connection alias to use for a feature.
 */

declare(strict_types=1);

require_once __DIR__ . '/registry.php';

/**
 * Resolve a configured connection alias to an active email connection alias.
 *
 * Empty / unknown values fall through to null so birkly_send_mail uses the default connection.
 */
function birkly_resolve_email_connection_alias(?string $configuredAlias): ?string
{
    $configuredAlias = trim((string) $configuredAlias);
    if ($configuredAlias === '') {
        return null;
    }

    $connection = birkly_connection_get_by_alias($configuredAlias, false);
    if ($connection === null || ($connection['type'] ?? 'email') !== 'email' || ($connection['enabled'] ?? true) === false) {
        return null;
    }

    return $configuredAlias;
}

/**
 * @return array{connections: list<array<string, mixed>>, default_alias: string|null, has_connections: bool}
 */
function birkly_email_connections_for_picker(): array
{
    $connections = birkly_connections_list(false);
    $emailConnections = [];
    foreach ($connections as $connection) {
        if (($connection['type'] ?? 'email') !== 'email' || ($connection['enabled'] ?? true) === false) {
            continue;
        }
        $emailConnections[] = [
            'id' => $connection['id'] ?? '',
            'alias' => $connection['alias'] ?? '',
            'name' => $connection['name'] ?? '',
            'provider' => $connection['provider'] ?? '',
            'from_email' => $connection['from_email'] ?? '',
            'is_default' => !empty($connection['is_default']),
        ];
    }

    $default = birkly_connection_get_default('email', false);

    return [
        'connections' => $emailConnections,
        'default_alias' => is_array($default) ? (string) ($default['alias'] ?? '') : null,
        'has_connections' => count($emailConnections) > 0,
    ];
}

/**
 * Send a collection/form notification email using configured api_settings.
 *
 * @param array<string, mixed> $apiSettings
 * @param array<string, mixed> $metadata
 * @return array<string, mixed>
 */
function birkly_send_api_notification_mail(array $apiSettings, string $to, string $subject, string $message, array $metadata = []): array
{
    $to = trim($to);
    if ($to === '') {
        return birkly_mail_result('failed', 'mail', null, null, 'Notification recipient is required.');
    }

    require_once __DIR__ . '/../mail/birkly_send_mail.php';

    $alias = birkly_resolve_email_connection_alias($apiSettings['email_connection_alias'] ?? null);

    return birkly_send_mail([
        'to' => $to,
        'subject' => $subject,
        'text' => $message,
        'metadata' => $metadata,
    ], $alias);
}
