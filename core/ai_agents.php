<?php
/**
 * AI agents — named identities with CMS roles (P5-M3).
 * Each agent owns an inbound MCP endpoint for connector OAuth.
 * Storage: settings/ai_agents.json
 */

declare(strict_types=1);

require_once __DIR__ . '/../api/storage.php';
require_once __DIR__ . '/mcp_endpoints.php';

function birkly_ai_agents_storage(): Storage
{
    return new Storage(__DIR__ . '/../settings');
}

function birkly_ai_agents_default_store(): array
{
    return [
        'version' => 1,
        'agents' => [],
    ];
}

function birkly_ai_agents_load_store(): array
{
    $data = birkly_ai_agents_storage()->read('ai_agents.json');
    if (!is_array($data)) {
        return birkly_ai_agents_default_store();
    }
    if (!isset($data['agents']) || !is_array($data['agents'])) {
        $data['agents'] = [];
    }
    return $data;
}

function birkly_ai_agents_save_store(array $store): bool
{
    return (bool) birkly_ai_agents_storage()->write('ai_agents.json', $store);
}

function birkly_ai_agents_scrub(array $agent): array
{
    unset($agent['mcp_api_key']);
    return $agent;
}

function birkly_ai_agents_list(bool $includeRevoked = false): array
{
    $agents = birkly_ai_agents_load_store()['agents'];
    if (!$includeRevoked) {
        $agents = array_values(array_filter(
            $agents,
            static fn(array $agent): bool => ($agent['status'] ?? 'active') !== 'revoked'
        ));
    }

    return array_values(array_map('birkly_ai_agents_scrub', $agents));
}

function birkly_ai_agents_find(string $id): ?array
{
    foreach (birkly_ai_agents_load_store()['agents'] as $agent) {
        if (($agent['id'] ?? '') === $id) {
            return $agent;
        }
    }
    return null;
}

function birkly_ai_agents_find_by_endpoint_id(string $endpointId): ?array
{
    foreach (birkly_ai_agents_load_store()['agents'] as $agent) {
        if (($agent['mcp_endpoint_id'] ?? '') === $endpointId && ($agent['status'] ?? 'active') !== 'revoked') {
            return $agent;
        }
    }
    return null;
}

function birkly_ai_role_rank(string $role): int
{
    static $ranks = [
        'viewer' => 1,
        'author' => 2,
        'editor' => 3,
        'admin' => 4,
    ];

    return $ranks[$role] ?? 0;
}

/**
 * Cap MCP tool access to the lower of agent role and authorizing human role.
 */
function birkly_ai_effective_role(string $agentRole, string $userRole): string
{
    if (birkly_ai_role_rank($userRole) < birkly_ai_role_rank($agentRole)) {
        return $userRole;
    }

    return $agentRole;
}

/**
 * Whether a logged-in CMS user may approve OAuth for this agent/endpoint.
 * Prevents privilege escalation (e.g. editor connecting an admin agent URL).
 */
function birkly_ai_user_can_authorize_agent(?array $agent, array $endpoint, array $user): bool
{
    if (!function_exists('checkPermission')) {
        require_once __DIR__ . '/../api/auth.php';
    }

    $agentRole = (string) ($agent['role'] ?? $endpoint['role'] ?? 'viewer');
    $userRole = (string) ($user['role'] ?? 'viewer');

    if (checkPermission($user, '*')) {
        return true;
    }

    if (birkly_ai_role_rank($userRole) < birkly_ai_role_rank($agentRole)) {
        return false;
    }

    if (checkPermission($user, 'manage_settings')) {
        return true;
    }

    if ($agent !== null && ($agent['created_by'] ?? '') === ($user['id'] ?? '')) {
        return true;
    }

    return in_array($userRole, ['admin', 'editor', 'author', 'viewer'], true);
}

function birkly_ai_user_authorize_agent_denial_reason(?array $agent, array $endpoint, array $user): string
{
    $agentRole = (string) ($agent['role'] ?? $endpoint['role'] ?? 'viewer');
    $userRole = (string) ($user['role'] ?? 'viewer');
    $agentName = (string) ($agent['name'] ?? $endpoint['name'] ?? 'AI agent');

    if (birkly_ai_role_rank($userRole) < birkly_ai_role_rank($agentRole)) {
        return 'Your account (' . $userRole . ') cannot authorize the "' . $agentName . '" agent (role: ' . $agentRole . ').';
    }

    return 'You do not have permission to authorize this AI agent connection.';
}

function birkly_ai_agents_valid_roles(): array
{
    return ['admin', 'editor', 'author', 'viewer'];
}

function birkly_ai_agents_create(array $input, string $createdBy): array
{
    $name = trim($input['name'] ?? '');
    $role = (string) ($input['role'] ?? 'viewer');
    $description = trim((string) ($input['description'] ?? ''));

    if ($name === '') {
        throw new InvalidArgumentException('Agent name is required');
    }
    if (!in_array($role, birkly_ai_agents_valid_roles(), true)) {
        throw new InvalidArgumentException('Invalid role');
    }

    $endpoint = birkly_mcp_create_endpoint([
        'name' => $name,
        'role' => $role,
        'require_approval' => array_key_exists('require_approval', $input)
            ? (bool) $input['require_approval']
            : true,
        'auth_mode' => 'oauth',
    ], $createdBy);

    $id = 'ai_agent_' . bin2hex(random_bytes(8));
    $agent = [
        'id' => $id,
        'name' => $name,
        'description' => $description,
        'role' => $role,
        'require_approval' => $endpoint['require_approval'] ?? true,
        'mcp_endpoint_id' => $endpoint['id'],
        'connector_url' => birkly_ai_agent_connector_url($id),
        'status' => 'active',
        'created_at' => date('c'),
        'created_by' => $createdBy,
    ];

    $store = birkly_ai_agents_load_store();
    $store['agents'][] = $agent;
    birkly_ai_agents_save_store($store);

    $agent['mcp_api_key'] = $endpoint['mcp_api_key'];
    return $agent;
}

function birkly_ai_agents_update(string $id, array $input): ?array
{
    $store = birkly_ai_agents_load_store();
    $updated = null;

    foreach ($store['agents'] as &$agent) {
        if (($agent['id'] ?? '') !== $id) {
            continue;
        }
        if (($agent['status'] ?? 'active') === 'revoked') {
            throw new RuntimeException('Cannot update a revoked agent');
        }

        if (isset($input['name'])) {
            $agent['name'] = trim((string) $input['name']);
        }
        if (isset($input['description'])) {
            $agent['description'] = trim((string) $input['description']);
        }
        if (isset($input['role'])) {
            $role = (string) $input['role'];
            if (!in_array($role, birkly_ai_agents_valid_roles(), true)) {
                throw new InvalidArgumentException('Invalid role');
            }
            $agent['role'] = $role;
        }
        if (array_key_exists('require_approval', $input)) {
            $agent['require_approval'] = (bool) $input['require_approval'];
        }

        $endpointId = (string) ($agent['mcp_endpoint_id'] ?? '');
        if ($endpointId !== '') {
            birkly_mcp_update_endpoint($endpointId, [
                'name' => $agent['name'],
                'role' => $agent['role'],
                'require_approval' => $agent['require_approval'] ?? true,
            ]);
        }

        $agent['updated_at'] = date('c');
        $updated = $agent;
        break;
    }

    if ($updated === null) {
        return null;
    }

    birkly_ai_agents_save_store($store);
    return birkly_ai_agents_scrub($updated);
}

function birkly_ai_agents_revoke(string $id): bool
{
    $store = birkly_ai_agents_load_store();
    $found = false;

    foreach ($store['agents'] as &$agent) {
        if (($agent['id'] ?? '') !== $id) {
            continue;
        }
        $endpointId = (string) ($agent['mcp_endpoint_id'] ?? '');
        if ($endpointId !== '') {
            birkly_mcp_revoke_endpoint($endpointId);
        }
        $agent['status'] = 'revoked';
        $agent['revoked_at'] = date('c');
        $found = true;
        break;
    }

    if (!$found) {
        return false;
    }

    return birkly_ai_agents_save_store($store);
}

function birkly_ai_agent_connector_url(string $agentId): string
{
    require_once __DIR__ . '/mcp_protocol.php';
    require_once __DIR__ . '/mcp_oauth.php';

    return birkly_mcp_oauth_absolute_url(birkly_mcp_connector_path_for_agent($agentId));
}

function birkly_ai_agents_with_endpoint(array $agent): array
{
    $endpointId = (string) ($agent['mcp_endpoint_id'] ?? '');
    $agentId = (string) ($agent['id'] ?? '');
    if ($agentId !== '' && ($agent['status'] ?? 'active') !== 'revoked') {
        $agent['connector_url'] = birkly_ai_agent_connector_url($agentId);
    }
    if ($endpointId === '') {
        return birkly_ai_agents_scrub($agent);
    }
    $endpoint = birkly_mcp_find_endpoint($endpointId);
    if ($endpoint) {
        $agent['auth_mode'] = $endpoint['auth_mode'] ?? 'oauth';
    }
    return birkly_ai_agents_scrub($agent);
}
