<?php
/**
 * Connection registry for Birkly Phase 65.
 *
 * Stores encrypted connection records in settings/connections.json.
 */

declare(strict_types=1);

require_once __DIR__ . '/../birkly_paths.php';
require_once __DIR__ . '/../storage/StorageFactory.php';
require_once __DIR__ . '/secret.php';
require_once __DIR__ . '/audit.php';

const BIRKLY_CONNECTION_TYPES = ['email', 'http_credential', 'channel'];
// Known providers are used for built-in schemas and config filtering.
// Custom providers are allowed: their config/secrets are stored as-is.
const BIRKLY_KNOWN_EMAIL_PROVIDERS = ['log', 'mail', 'smtp', 'resend', 'postmark', 'ses', 'mailgun'];
const BIRKLY_KNOWN_HTTP_PROVIDERS = ['basic_auth', 'bearer', 'custom_header'];
const BIRKLY_KNOWN_CHANNEL_PROVIDERS = ['slack', 'discord', 'twilio_sms'];

function birkly_connections_storage(): StorageInterface
{
    return StorageFactory::createStorage(birkly_project_root() . '/settings');
}

function birkly_connections_load(): array
{
    $storage = birkly_connections_storage();
    $store = $storage->read('connections.json');
    if (!is_array($store)) {
        return ['connections' => []];
    }
    if (!isset($store['connections']) || !is_array($store['connections'])) {
        $store['connections'] = [];
    }
    return $store;
}

function birkly_connections_save(array $store): bool
{
    $storage = birkly_connections_storage();
    return $storage->write('connections.json', $store);
}

function birkly_connections_now(): string
{
    return date('c');
}

function birkly_connections_generate_id(): string
{
    return 'conn_' . bin2hex(random_bytes(12));
}

function birkly_connections_sanitize_alias(string $alias): string
{
    $alias = strtolower(trim($alias));
    // Allow '*' as a reserved global/wildcard alias.
    if ($alias === '*') {
        return '*';
    }
    $alias = preg_replace('/[^a-z0-9_.-]+/', '-', $alias) ?? '';
    return trim($alias, '-_.');
}

function birkly_connection_mask_secrets(array $connection): array
{
    $connection['secrets'] = ['_masked' => true];
    unset($connection['secrets_ciphertext']);
    return $connection;
}

function birkly_connections_list(bool $withSecrets = false): array
{
    $store = birkly_connections_load();
    $connections = $store['connections'];
    if (!$withSecrets) {
        $connections = array_map('birkly_connection_mask_secrets', $connections);
    }
    return $connections;
}

function birkly_connection_get(string $id, bool $withSecrets = false): ?array
{
    $store = birkly_connections_load();
    foreach ($store['connections'] as $connection) {
        if (($connection['id'] ?? '') === $id) {
            if ($withSecrets) {
                return birkly_connection_decrypt_for_use($connection);
            }
            return birkly_connection_mask_secrets($connection);
        }
    }
    return null;
}

function birkly_connection_get_by_alias(string $alias, bool $withSecrets = false): ?array
{
    $alias = birkly_connections_sanitize_alias($alias);
    $store = birkly_connections_load();
    foreach ($store['connections'] as $connection) {
        if (birkly_connections_sanitize_alias((string) ($connection['alias'] ?? '')) === $alias) {
            if ($withSecrets) {
                return birkly_connection_decrypt_for_use($connection);
            }
            return birkly_connection_mask_secrets($connection);
        }
    }
    return null;
}

function birkly_connections_match_event(string $event): array
{
    $event = birkly_connections_sanitize_alias($event);
    $store = birkly_connections_load();
    $matches = [];
    foreach ($store['connections'] as $connection) {
        if (($connection['enabled'] ?? true) !== true) {
            continue;
        }
        $alias = birkly_connections_sanitize_alias((string) ($connection['alias'] ?? ''));
        if ($alias === $event || $alias === '*') {
            $matches[] = $connection;
        }
    }
    return $matches;
}

function birkly_connection_get_default(string $type = 'email', bool $withSecrets = false): ?array
{
    $store = birkly_connections_load();
    foreach ($store['connections'] as $connection) {
        if (($connection['type'] ?? '') === $type && !empty($connection['is_default']) && ($connection['enabled'] ?? true)) {
            return $withSecrets ? birkly_connection_decrypt_for_use($connection) : birkly_connection_mask_secrets($connection);
        }
    }
    // Fall back to the first enabled connection of the requested type.
    foreach ($store['connections'] as $connection) {
        if (($connection['type'] ?? '') === $type && ($connection['enabled'] ?? true)) {
            return $withSecrets ? birkly_connection_decrypt_for_use($connection) : birkly_connection_mask_secrets($connection);
        }
    }
    return null;
}

function birkly_connection_decrypt_for_use(array $connection): array
{
    if (!empty($connection['secrets_ciphertext'])) {
        $connection['secrets'] = birkly_connection_decrypt_secret($connection['secrets_ciphertext']) ?? [];
    }
    return $connection;
}

function birkly_connection_validate(array $data, ?string $excludeId = null): array
{
    $errors = [];

    $type = (string) ($data['type'] ?? '');
    if (!in_array($type, BIRKLY_CONNECTION_TYPES, true)) {
        $errors['type'] = 'Unsupported connection type.';
    }

    $provider = (string) ($data['provider'] ?? '');
    if ($provider === '') {
        $errors['provider'] = 'Provider is required.';
    } elseif (!preg_match('/^[a-z0-9_.-]+$/', $provider)) {
        $errors['provider'] = 'Provider must be lowercase letters, numbers, dots, dashes or underscores.';
    }

    $alias = birkly_connections_sanitize_alias((string) ($data['alias'] ?? ''));
    if ($alias === '') {
        $errors['alias'] = 'Alias is required.';
    } elseif (strlen($alias) > 64) {
        $errors['alias'] = 'Alias must be 64 characters or fewer.';
    } else {
        $existing = birkly_connection_get_by_alias($alias, true);
        if ($existing !== null && ($excludeId === null || $existing['id'] !== $excludeId)) {
            $errors['alias'] = 'Alias is already in use.';
        }
    }

    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        $errors['name'] = 'Name is required.';
    }

    $fromEmail = (string) ($data['from_email'] ?? '');
    if ($type === 'email' && $fromEmail !== '' && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $errors['from_email'] = 'From email must be a valid email address.';
    }

    return $errors;
}

function birkly_connection_prepare_record(array $input, ?array $existing = null): array
{
    $provider = (string) ($input['provider'] ?? ($existing['provider'] ?? 'log'));
    $type = (string) ($input['type'] ?? ($existing['type'] ?? 'email'));

    $config = array_merge($existing['config'] ?? [], $input['config'] ?? []);
    $config = birkly_connection_filter_config($type, $provider, $config);

    $record = [
        'id' => $existing['id'] ?? birkly_connections_generate_id(),
        'type' => $type,
        'provider' => $provider,
        'alias' => birkly_connections_sanitize_alias((string) ($input['alias'] ?? ($existing['alias'] ?? ''))),
        'name' => trim((string) ($input['name'] ?? ($existing['name'] ?? ''))),
        'from_email' => (string) ($input['from_email'] ?? ($existing['from_email'] ?? '')),
        'from_name' => trim((string) ($input['from_name'] ?? ($existing['from_name'] ?? 'Birkly'))),
        'is_default' => (bool) ($input['is_default'] ?? ($existing['is_default'] ?? false)),
        'enabled' => (bool) ($input['enabled'] ?? ($existing['enabled'] ?? true)),
        'config' => $config,
        'created_at' => $existing['created_at'] ?? birkly_connections_now(),
        'updated_at' => birkly_connections_now(),
    ];

    // Merge secrets: provided values override existing; empty strings mean "keep existing".
    $existingSecrets = [];
    if ($existing !== null && !empty($existing['secrets_ciphertext'])) {
        $existingSecrets = birkly_connection_decrypt_secret($existing['secrets_ciphertext']) ?? [];
    }
    $incomingSecrets = is_array($input['secrets'] ?? null) ? $input['secrets'] : [];
    $secrets = array_merge($existingSecrets, array_filter($incomingSecrets, function ($v) {
        return $v !== null && $v !== '';
    }));

    if (!empty($secrets)) {
        $record['secrets_ciphertext'] = birkly_connection_encrypt_secret($secrets);
    } elseif (!empty($existing['secrets_ciphertext'])) {
        $record['secrets_ciphertext'] = $existing['secrets_ciphertext'];
    }

    return $record;
}

function birkly_connection_filter_config(string $type, string $provider, array $config): array
{
    $allowed = [
        'email' => [
            'smtp' => ['host', 'port', 'encryption', 'auth'],
            'resend' => ['region'],
            'postmark' => [],
            'ses' => ['region'],
            'mailgun' => ['region', 'domain'],
            'log' => [],
            'mail' => [],
        ],
        'http_credential' => [
            'basic_auth' => ['base_url', 'timeout'],
            'bearer' => ['base_url', 'timeout'],
            'custom_header' => ['base_url', 'timeout', 'header_name'],
        ],
        'channel' => [
            'slack' => ['channel'],
            'discord' => [],
            'twilio_sms' => ['from_number'],
        ],
    ];

    $keys = $allowed[$type][$provider] ?? null;
    // Unknown providers pass config through unchanged so users can define custom fields.
    if ($keys === null) {
        return $config;
    }
    $filtered = [];
    foreach ($keys as $key) {
        if (array_key_exists($key, $config) && $config[$key] !== '') {
            $filtered[$key] = $config[$key];
        }
    }

    // Type normalization.
    if (isset($filtered['port'])) {
        $filtered['port'] = (int) $filtered['port'];
    }
    if (isset($filtered['timeout'])) {
        $filtered['timeout'] = (int) $filtered['timeout'];
    }
    if (isset($filtered['encryption'])) {
        $filtered['encryption'] = strtolower((string) $filtered['encryption']);
    }
    if (isset($filtered['auth'])) {
        $filtered['auth'] = strtolower((string) $filtered['auth']);
    }

    return $filtered;
}

function birkly_connection_create(array $data): array
{
    $errors = birkly_connection_validate($data);
    if (!empty($errors)) {
        return ['ok' => false, 'errors' => $errors];
    }

    $store = birkly_connections_load();
    $record = birkly_connection_prepare_record($data);

    if ($record['is_default']) {
        $store['connections'] = birkly_connections_clear_default_flag($store['connections'], $record['type'], null);
    } elseif (!birkly_connections_has_default($store['connections'], $record['type'])) {
        $record['is_default'] = true;
    }

    $store['connections'][] = $record;
    if (!birkly_connections_save($store)) {
        return ['ok' => false, 'errors' => ['_store' => 'Failed to save connection.']];
    }

    birkly_connection_audit_log('connection_created', [
        'connection_id' => $record['id'],
        'alias' => $record['alias'],
        'provider' => $record['provider'],
    ]);

    return ['ok' => true, 'connection' => birkly_connection_mask_secrets($record)];
}

function birkly_connection_update(string $id, array $data): array
{
    $store = birkly_connections_load();
    $index = birkly_connections_find_index($store['connections'], $id);
    if ($index === null) {
        return ['ok' => false, 'errors' => ['_store' => 'Connection not found.']];
    }

    $existing = $store['connections'][$index];
    $errors = birkly_connection_validate(array_merge($existing, $data), $id);
    if (!empty($errors)) {
        return ['ok' => false, 'errors' => $errors];
    }

    $record = birkly_connection_prepare_record($data, $existing);

    if ($record['is_default']) {
        $store['connections'] = birkly_connections_clear_default_flag($store['connections'], $record['type'], $id);
    }

    $store['connections'][$index] = $record;
    if (!birkly_connections_save($store)) {
        return ['ok' => false, 'errors' => ['_store' => 'Failed to save connection.']];
    }

    birkly_connection_audit_log('connection_updated', [
        'connection_id' => $record['id'],
        'alias' => $record['alias'],
        'provider' => $record['provider'],
    ]);

    return ['ok' => true, 'connection' => birkly_connection_mask_secrets($record)];
}

function birkly_connection_delete(string $id): array
{
    $store = birkly_connections_load();
    $index = birkly_connections_find_index($store['connections'], $id);
    if ($index === null) {
        return ['ok' => false, 'errors' => ['_store' => 'Connection not found.']];
    }

    $deleted = $store['connections'][$index];
    array_splice($store['connections'], $index, 1);

    if (!birkly_connections_save($store)) {
        return ['ok' => false, 'errors' => ['_store' => 'Failed to save connection.']];
    }

    birkly_connection_audit_log('connection_deleted', [
        'connection_id' => $deleted['id'],
        'alias' => $deleted['alias'],
        'provider' => $deleted['provider'],
    ]);

    return ['ok' => true];
}

function birkly_connection_set_default(string $id): array
{
    $store = birkly_connections_load();
    $index = birkly_connections_find_index($store['connections'], $id);
    if ($index === null) {
        return ['ok' => false, 'errors' => ['_store' => 'Connection not found.']];
    }

    $record = $store['connections'][$index];
    $store['connections'] = birkly_connections_clear_default_flag($store['connections'], $record['type'], $id);
    $record['is_default'] = true;
    $record['updated_at'] = birkly_connections_now();
    $store['connections'][$index] = $record;

    if (!birkly_connections_save($store)) {
        return ['ok' => false, 'errors' => ['_store' => 'Failed to save connection.']];
    }

    birkly_connection_audit_log('connection_set_default', [
        'connection_id' => $record['id'],
        'alias' => $record['alias'],
    ]);

    return ['ok' => true, 'connection' => birkly_connection_mask_secrets($record)];
}

function birkly_connections_find_index(array $connections, string $id): ?int
{
    foreach ($connections as $i => $connection) {
        if (($connection['id'] ?? '') === $id) {
            return $i;
        }
    }
    return null;
}

function birkly_connections_clear_default_flag(array $connections, string $type, ?string $exceptId): array
{
    foreach ($connections as $i => $connection) {
        if (($connection['type'] ?? '') === $type && ($exceptId === null || ($connection['id'] ?? '') !== $exceptId)) {
            $connections[$i]['is_default'] = false;
        }
    }
    return $connections;
}

function birkly_connections_has_default(array $connections, string $type): bool
{
    foreach ($connections as $connection) {
        if (($connection['type'] ?? '') === $type && !empty($connection['is_default'])) {
            return true;
        }
    }
    return false;
}
