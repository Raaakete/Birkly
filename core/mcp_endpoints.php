<?php
/**
 * Standalone inbound MCP endpoint profiles (P4-M3).
 * Storage: settings/mcp_endpoints.json
 */

require_once __DIR__ . '/../api/storage.php';
require_once __DIR__ . '/ai_tool_registry.php';

define('BIRKLY_MCP_LEGACY_GRACE_DAYS', 90);
define('BIRKLY_MCP_ROTATION_GRACE_HOURS', 24);
define('BIRKLY_MCP_RATE_LIMIT_PER_MINUTE', 120);
define('BIRKLY_MCP_AUTH_LOCKOUT_ATTEMPTS', 10);
define('BIRKLY_MCP_AUTH_LOCKOUT_SECONDS', 900);

function birkly_mcp_endpoints_storage(): Storage
{
    return new Storage(__DIR__ . '/../settings');
}

function birkly_mcp_default_store(): array
{
    return [
        'version' => 1,
        'legacy_dual_resolve_until' => null,
        'endpoints' => [],
    ];
}

function birkly_mcp_load_store(): array
{
    $storage = birkly_mcp_endpoints_storage();
    $data = $storage->read('mcp_endpoints.json');
    if (!is_array($data)) {
        return birkly_mcp_default_store();
    }
    if (!isset($data['endpoints']) || !is_array($data['endpoints'])) {
        $data['endpoints'] = [];
    }
    return $data;
}

function birkly_mcp_save_store(array $store): bool
{
    $storage = birkly_mcp_endpoints_storage();
    return (bool) $storage->write('mcp_endpoints.json', $store);
}

function birkly_mcp_scrub_endpoint(array $endpoint): array
{
    unset($endpoint['mcp_api_key'], $endpoint['previous_mcp_api_key']);
    return $endpoint;
}

function birkly_mcp_list_endpoints(): array
{
    $store = birkly_mcp_load_store();
    return array_values(array_map('birkly_mcp_scrub_endpoint', $store['endpoints']));
}

function birkly_mcp_find_endpoint(string $id): ?array
{
    foreach (birkly_mcp_load_store()['endpoints'] as $endpoint) {
        if (($endpoint['id'] ?? '') === $id) {
            return $endpoint;
        }
    }
    return null;
}

function birkly_mcp_generate_key(): string
{
    return bin2hex(random_bytes(32));
}

function birkly_mcp_endpoint_to_ai_user(array $endpoint): array
{
    return [
        'id' => $endpoint['id'],
        'type' => 'mcp_endpoint',
        'name' => $endpoint['name'] ?? 'MCP Endpoint',
        'role' => $endpoint['role'] ?? 'viewer',
        'require_approval' => $endpoint['require_approval'] ?? true,
        'email' => ($endpoint['id'] ?? 'mcp') . '@mcp.birkly',
        'provider' => 'mcp',
    ];
}

function birkly_mcp_create_endpoint(array $input, string $createdBy): array
{
    $name = trim($input['name'] ?? '');
    $role = $input['role'] ?? 'viewer';
    if ($name === '') {
        throw new InvalidArgumentException('Endpoint name is required');
    }

    $store = birkly_mcp_load_store();
    $id = 'mcp_ep_' . bin2hex(random_bytes(8));
    $key = birkly_mcp_generate_key();

    $endpoint = [
        'id' => $id,
        'name' => $name,
        'role' => $role,
        'require_approval' => array_key_exists('require_approval', $input)
            ? (bool) $input['require_approval']
            : true,
        'mcp_api_key' => $key,
        'previous_mcp_api_key' => null,
        'previous_key_expires_at' => null,
        'status' => 'active',
        'created_at' => date('c'),
        'created_by' => $createdBy,
        'rotated_at' => null,
        'revoked_at' => null,
        'failed_auth_count' => 0,
        'locked_until' => null,
        'mcp_url' => birkly_ai_mcp_server_url(),
    ];

    $store['endpoints'][] = $endpoint;
    birkly_mcp_save_store($store);

    return $endpoint;
}

function birkly_mcp_update_endpoint(string $id, array $input): ?array
{
    $store = birkly_mcp_load_store();
    $updated = null;

    foreach ($store['endpoints'] as &$endpoint) {
        if (($endpoint['id'] ?? '') !== $id) {
            continue;
        }
        if (($endpoint['status'] ?? 'active') === 'revoked') {
            throw new RuntimeException('Cannot update a revoked endpoint');
        }
        if (isset($input['name'])) {
            $endpoint['name'] = trim((string) $input['name']);
        }
        if (isset($input['role'])) {
            $endpoint['role'] = (string) $input['role'];
        }
        if (array_key_exists('require_approval', $input)) {
            $endpoint['require_approval'] = (bool) $input['require_approval'];
        }
        $endpoint['updated_at'] = date('c');
        $updated = $endpoint;
        break;
    }
    unset($endpoint);

    if ($updated === null) {
        return null;
    }

    birkly_mcp_save_store($store);
    return $updated;
}

function birkly_mcp_rotate_endpoint_key(string $id): ?array
{
    $store = birkly_mcp_load_store();
    $updated = null;

    foreach ($store['endpoints'] as &$endpoint) {
        if (($endpoint['id'] ?? '') !== $id) {
            continue;
        }
        if (($endpoint['status'] ?? 'active') === 'revoked') {
            throw new RuntimeException('Cannot rotate a revoked endpoint');
        }
        $endpoint['previous_mcp_api_key'] = $endpoint['mcp_api_key'] ?? null;
        $endpoint['previous_key_expires_at'] = date('c', time() + (BIRKLY_MCP_ROTATION_GRACE_HOURS * 3600));
        $endpoint['mcp_api_key'] = birkly_mcp_generate_key();
        $endpoint['rotated_at'] = date('c');
        $updated = $endpoint;
        break;
    }
    unset($endpoint);

    if ($updated === null) {
        return null;
    }

    birkly_mcp_save_store($store);
    return $updated;
}

function birkly_mcp_revoke_endpoint(string $id): bool
{
    $store = birkly_mcp_load_store();
    $found = false;

    foreach ($store['endpoints'] as &$endpoint) {
        if (($endpoint['id'] ?? '') !== $id) {
            continue;
        }
        $endpoint['status'] = 'revoked';
        $endpoint['revoked_at'] = date('c');
        $endpoint['previous_mcp_api_key'] = null;
        $endpoint['previous_key_expires_at'] = null;
        $found = true;
        break;
    }
    unset($endpoint);

    if (!$found) {
        return false;
    }

    return birkly_mcp_save_store($store);
}

function birkly_mcp_key_matches_endpoint(array $endpoint, string $apiKey): bool
{
    if (($endpoint['status'] ?? 'active') !== 'active') {
        return false;
    }

    if (!empty($endpoint['locked_until']) && strtotime($endpoint['locked_until']) > time()) {
        return false;
    }

    if (!empty($endpoint['mcp_api_key']) && hash_equals($endpoint['mcp_api_key'], $apiKey)) {
        return true;
    }

    if (!empty($endpoint['previous_mcp_api_key']) && !empty($endpoint['previous_key_expires_at'])) {
        if (strtotime($endpoint['previous_key_expires_at']) >= time()
            && hash_equals($endpoint['previous_mcp_api_key'], $apiKey)) {
            return true;
        }
    }

    return false;
}

function birkly_mcp_resolve_endpoint_by_key(string $apiKey): ?array
{
    foreach (birkly_mcp_load_store()['endpoints'] as $endpoint) {
        if (birkly_mcp_key_matches_endpoint($endpoint, $apiKey)) {
            return $endpoint;
        }
    }
    return null;
}

function birkly_mcp_legacy_dual_resolve_active(): bool
{
    $until = birkly_mcp_load_store()['legacy_dual_resolve_until'] ?? null;
    if (!$until) {
        return false;
    }
    return strtotime($until) >= time();
}

function birkly_mcp_set_legacy_dual_resolve_until(string $isoDate): void
{
    $store = birkly_mcp_load_store();
    $store['legacy_dual_resolve_until'] = $isoDate;
    birkly_mcp_save_store($store);
}

function birkly_mcp_find_legacy_ai_user_by_key(string $apiKey): ?array
{
    if (!birkly_mcp_legacy_dual_resolve_active()) {
        return null;
    }

    $usersStorage = new Storage(__DIR__ . '/../users');
    foreach ($usersStorage->list('') as $userFile) {
        $user = $usersStorage->read($userFile);
        if ($user
            && ($user['type'] ?? '') === 'ai'
            && isset($user['mcp_api_key'])
            && hash_equals($user['mcp_api_key'], $apiKey)) {
            return $user;
        }
    }
    return null;
}

function birkly_mcp_resolve_actor_by_key(string $apiKey): ?array
{
    $endpoint = birkly_mcp_resolve_endpoint_by_key($apiKey);
    if ($endpoint) {
        return birkly_mcp_endpoint_to_ai_user($endpoint);
    }

    $legacy = birkly_mcp_find_legacy_ai_user_by_key($apiKey);
    if ($legacy) {
        return $legacy;
    }

    return null;
}

function birkly_mcp_record_failed_auth(string $apiKey): void
{
    $store = birkly_mcp_load_store();
    $changed = false;

    foreach ($store['endpoints'] as &$endpoint) {
        $keys = array_filter([
            $endpoint['mcp_api_key'] ?? null,
            $endpoint['previous_mcp_api_key'] ?? null,
        ]);
        $matched = false;
        foreach ($keys as $key) {
            if ($key && hash_equals($key, $apiKey)) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            continue;
        }
        $endpoint['failed_auth_count'] = (int) ($endpoint['failed_auth_count'] ?? 0) + 1;
        if ($endpoint['failed_auth_count'] >= BIRKLY_MCP_AUTH_LOCKOUT_ATTEMPTS) {
            $endpoint['locked_until'] = date('c', time() + BIRKLY_MCP_AUTH_LOCKOUT_SECONDS);
            $endpoint['failed_auth_count'] = 0;
        }
        $changed = true;
        break;
    }
    unset($endpoint);

    if ($changed) {
        birkly_mcp_save_store($store);
    }
}

function birkly_mcp_reset_failed_auth(string $endpointId): void
{
    $store = birkly_mcp_load_store();
    foreach ($store['endpoints'] as &$endpoint) {
        if (($endpoint['id'] ?? '') === $endpointId) {
            $endpoint['failed_auth_count'] = 0;
            $endpoint['locked_until'] = null;
            break;
        }
    }
    unset($endpoint);
    birkly_mcp_save_store($store);
}

function birkly_mcp_rate_limit_dir(): string
{
    $dir = __DIR__ . '/../data/mcp_rate';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function birkly_mcp_check_rate_limit(string $endpointId): bool
{
    $file = birkly_mcp_rate_limit_dir() . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $endpointId) . '.json';
    $window = (int) floor(time() / 60);
    $data = ['window' => $window, 'count' => 0];
    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }
    if ((int) ($data['window'] ?? 0) !== $window) {
        $data = ['window' => $window, 'count' => 0];
    }
    $data['count'] = (int) ($data['count'] ?? 0) + 1;
    file_put_contents($file, json_encode($data));
    return $data['count'] <= BIRKLY_MCP_RATE_LIMIT_PER_MINUTE;
}

function birkly_mcp_audit_log(string $endpointId, string $method, string $toolName = '', bool $success = true): void
{
    $dir = __DIR__ . '/../data/mcp_audit';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $entry = [
        'timestamp' => date('c'),
        'endpoint_id' => $endpointId,
        'method' => $method,
        'tool' => $toolName,
        'success' => $success,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ];
    $file = $dir . '/' . date('Y-m-d') . '.jsonl';
    file_put_contents($file, json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);
}

function birkly_mcp_migrate_from_ai_connections(): array
{
    require_once __DIR__ . '/../api/ai_helpers.php';

    $usersStorage = new Storage(__DIR__ . '/../users');
    $store = birkly_mcp_load_store();
    $existingKeys = [];
    foreach ($store['endpoints'] as $ep) {
        if (!empty($ep['mcp_api_key'])) {
            $existingKeys[$ep['mcp_api_key']] = true;
        }
    }

    $migrated = 0;
    $skipped = 0;

    foreach ($usersStorage->list('') as $userFile) {
        $user = $usersStorage->read($userFile);
        if (!$user || ($user['type'] ?? '') !== 'ai' || empty($user['mcp_api_key'])) {
            continue;
        }
        if (isset($existingKeys[$user['mcp_api_key']])) {
            $skipped++;
            continue;
        }

        $store['endpoints'][] = [
            'id' => 'mcp_ep_mig_' . substr($user['id'], 0, 16),
            'name' => ($user['name'] ?? 'Migrated MCP') . ' (migrated)',
            'role' => $user['role'] ?? 'viewer',
            'require_approval' => $user['require_approval'] ?? true,
            'mcp_api_key' => $user['mcp_api_key'],
            'previous_mcp_api_key' => null,
            'previous_key_expires_at' => null,
            'status' => ($user['status'] ?? 'active') === 'active' ? 'active' : 'revoked',
            'created_at' => $user['created_at'] ?? date('c'),
            'created_by' => $user['created_by'] ?? 'migration',
            'migrated_from_ai_connection' => $user['id'],
            'mcp_url' => birkly_ai_mcp_server_url(),
        ];
        $existingKeys[$user['mcp_api_key']] = true;
        $migrated++;
    }

    if ($migrated > 0 || empty($store['legacy_dual_resolve_until'])) {
        $store['legacy_dual_resolve_until'] = date('c', time() + (BIRKLY_MCP_LEGACY_GRACE_DAYS * 86400));
    }

    birkly_mcp_save_store($store);

    return ['migrated' => $migrated, 'skipped' => $skipped, 'legacy_until' => $store['legacy_dual_resolve_until']];
}
