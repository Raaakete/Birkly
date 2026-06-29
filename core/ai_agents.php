<?php
/**
 * AI agents — personal (dynamic) + service (fixed role) — P5.1.
 * Storage: settings/ai_agents.json
 */

declare(strict_types=1);

require_once __DIR__ . '/../api/storage.php';
require_once __DIR__ . '/mcp_endpoints.php';

define('BIRKLY_AI_AGENT_PERSONAL_ID', 'ai_agent_personal');

function birkly_ai_agents_storage(): Storage
{
    return new Storage(__DIR__ . '/../settings');
}

function birkly_ai_agents_default_store(): array
{
    return [
        'version' => 2,
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

function birkly_ai_agents_normalize_agent(array $agent): array
{
    $type = (string) ($agent['type'] ?? 'service');
    if (!in_array($type, ['personal', 'service'], true)) {
        $type = 'service';
    }
    $agent['type'] = $type;

    if (!isset($agent['visible_to_roles']) || !is_array($agent['visible_to_roles'])) {
        $agent['visible_to_roles'] = $type === 'personal' ? [] : ['admin'];
    }
    if (!isset($agent['allowed_users']) || !is_array($agent['allowed_users'])) {
        $agent['allowed_users'] = [];
    }

    return $agent;
}

function birkly_ai_agents_scrub(array $agent): array
{
    unset($agent['mcp_api_key']);
    return birkly_ai_agents_normalize_agent($agent);
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
            return birkly_ai_agents_normalize_agent($agent);
        }
    }
    return null;
}

function birkly_ai_agents_find_personal(): ?array
{
    return birkly_ai_agents_find(BIRKLY_AI_AGENT_PERSONAL_ID);
}

function birkly_ai_agents_find_by_endpoint_id(string $endpointId): ?array
{
    foreach (birkly_ai_agents_load_store()['agents'] as $agent) {
        if (($agent['mcp_endpoint_id'] ?? '') === $endpointId && ($agent['status'] ?? 'active') !== 'revoked') {
            return birkly_ai_agents_normalize_agent($agent);
        }
    }
    return null;
}

function birkly_ai_agents_is_admin(array $user): bool
{
    if (!function_exists('checkPermission')) {
        require_once __DIR__ . '/../api/auth.php';
    }

    return checkPermission($user, '*') || checkPermission($user, 'manage_settings');
}

function birkly_ai_agents_user_has_panel_access(array $user): bool
{
    $role = (string) ($user['role'] ?? '');
    return in_array($role, ['admin', 'editor', 'author', 'viewer'], true);
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
 * Cap MCP tool access to the lower of agent role and authorizing human role (service agents only).
 */
function birkly_ai_effective_role(string $agentRole, string $userRole): string
{
    if (birkly_ai_role_rank($userRole) < birkly_ai_role_rank($agentRole)) {
        return $userRole;
    }

    return $agentRole;
}

function birkly_ai_user_can_see_agent(?array $agent, array $user): bool
{
    if ($agent === null) {
        return false;
    }

    $status = (string) ($agent['status'] ?? 'active');
    if ($status === 'revoked') {
        return false;
    }

    $type = (string) ($agent['type'] ?? 'service');

    if ($type === 'personal') {
        if ($status === 'disabled') {
            return birkly_ai_agents_is_admin($user);
        }
        return birkly_ai_agents_user_has_panel_access($user);
    }

    if (birkly_ai_agents_is_admin($user)) {
        return true;
    }

    $userId = (string) ($user['id'] ?? '');
    $userRole = (string) ($user['role'] ?? '');

    if ($userId !== '' && in_array($userId, $agent['allowed_users'] ?? [], true)) {
        return true;
    }

    return in_array($userRole, $agent['visible_to_roles'] ?? [], true);
}

/**
 * Whether a logged-in CMS user may approve OAuth for this agent.
 */
function birkly_ai_user_can_authorize_agent(?array $agent, array $endpoint, array $user): bool
{
    if ($agent === null) {
        if (!birkly_ai_agents_user_has_panel_access($user)) {
            return false;
        }
        $endpointRole = (string) ($endpoint['role'] ?? 'viewer');
        if (birkly_ai_agents_is_admin($user)) {
            return true;
        }

        return birkly_ai_role_rank((string) ($user['role'] ?? 'viewer')) >= birkly_ai_role_rank($endpointRole);
    }

    if (($agent['status'] ?? 'active') !== 'active') {
        return false;
    }

    if (!birkly_ai_user_can_see_agent($agent, $user)) {
        return false;
    }

    $type = (string) ($agent['type'] ?? 'service');

    if ($type === 'personal') {
        return birkly_ai_agents_user_has_panel_access($user);
    }

    if (birkly_ai_agents_is_admin($user)) {
        return true;
    }

    return birkly_ai_user_can_see_agent($agent, $user);
}

function birkly_ai_user_authorize_agent_denial_reason(?array $agent, array $endpoint, array $user): string
{
    $agent = $agent !== null ? birkly_ai_agents_normalize_agent($agent) : null;
    $agentName = (string) ($agent['name'] ?? $endpoint['name'] ?? 'AI agent');
    $type = (string) ($agent['type'] ?? 'service');

    if ($agent !== null && ($agent['status'] ?? 'active') !== 'active') {
        return 'The "' . $agentName . '" agent is not active.';
    }

    if ($agent !== null && !birkly_ai_user_can_see_agent($agent, $user)) {
        if ($type === 'service') {
            return 'You are not assigned to the "' . $agentName . '" service agent. Ask an admin to grant access.';
        }
        return 'You do not have access to the personal connector.';
    }

    if ($type === 'service') {
        $agentRole = (string) ($agent['role'] ?? $endpoint['role'] ?? 'viewer');
        $userRole = (string) ($user['role'] ?? 'viewer');
        if (!birkly_ai_agents_is_admin($user) && birkly_ai_role_rank($userRole) < birkly_ai_role_rank($agentRole)) {
            return 'Your account (' . $userRole . ') cannot authorize the "' . $agentName . '" agent (role: ' . $agentRole . ').';
        }
    }

    return 'You do not have permission to authorize this AI agent connection.';
}

function birkly_ai_agents_consent_summary(?array $agent, array $endpoint, array $user): array
{
    $agent = $agent !== null ? birkly_ai_agents_normalize_agent($agent) : null;
    $type = (string) ($agent['type'] ?? 'service');
    $userRole = (string) ($user['role'] ?? 'viewer');

    if ($type === 'personal') {
        return [
            'type' => 'personal',
            'title' => 'Personal connector',
            'body' => 'You will connect with your current Birkly role (' . $userRole . ') and your own permissions.',
        ];
    }

    $agentName = (string) ($agent['name'] ?? $endpoint['name'] ?? 'Service agent');
    $agentRole = (string) ($agent['role'] ?? $endpoint['role'] ?? 'viewer');
    $effectiveRole = birkly_ai_agents_is_admin($user)
        ? $agentRole
        : birkly_ai_effective_role($agentRole, $userRole);

    return [
        'type' => 'service',
        'title' => 'Service agent',
        'body' => 'You will connect as "' . $agentName . '" with ' . $effectiveRole . ' access.',
    ];
}

function birkly_ai_agents_valid_roles(): array
{
    return ['admin', 'editor', 'author', 'viewer'];
}

function birkly_ai_agents_normalize_string_list($value): array
{
    if (!is_array($value)) {
        return [];
    }

    return array_values(array_unique(array_filter(array_map('strval', $value), static fn(string $v): bool => $v !== '')));
}

function birkly_ai_agents_maybe_migrate_store(): void
{
    $store = birkly_ai_agents_load_store();
    $changed = false;

    foreach ($store['agents'] as &$agent) {
        $before = json_encode($agent);
        $agent = birkly_ai_agents_migrate_legacy_row($agent);
        if (json_encode($agent) !== $before) {
            $changed = true;
        }
    }
    unset($agent);

    if (($store['version'] ?? 1) < 2) {
        $store['version'] = 2;
        $changed = true;
    }

    if ($changed) {
        birkly_ai_agents_save_store($store);
    }
}

function birkly_ai_agents_ensure_personal(): array
{
    birkly_ai_agents_maybe_migrate_store();

    $existing = birkly_ai_agents_find(BIRKLY_AI_AGENT_PERSONAL_ID);
    if ($existing !== null && ($existing['status'] ?? 'active') !== 'revoked') {
        return $existing;
    }

    $endpoint = birkly_mcp_create_endpoint([
        'name' => 'Personal connector',
        'role' => 'viewer',
        'require_approval' => true,
        'auth_mode' => 'oauth',
    ], 'system');

    $agent = birkly_ai_agents_normalize_agent([
        'id' => BIRKLY_AI_AGENT_PERSONAL_ID,
        'type' => 'personal',
        'name' => 'Your personal connector',
        'description' => 'Connect Claude with your own Birkly permissions. Each team member uses the same URL but signs in with their own account.',
        'role' => 'viewer',
        'visible_to_roles' => [],
        'allowed_users' => [],
        'require_approval' => true,
        'mcp_endpoint_id' => $endpoint['id'],
        'connector_url' => birkly_ai_agent_connector_url(BIRKLY_AI_AGENT_PERSONAL_ID),
        'status' => 'active',
        'created_at' => date('c'),
        'created_by' => 'system',
    ]);

    $store = birkly_ai_agents_load_store();
    $store['agents'] = array_values(array_filter(
        $store['agents'],
        static fn(array $row): bool => ($row['id'] ?? '') !== BIRKLY_AI_AGENT_PERSONAL_ID
    ));
    $store['agents'][] = $agent;
    birkly_ai_agents_save_store($store);

    return $agent;
}

function birkly_ai_agents_list_for_user(array $user, bool $includePersonal = true): array
{
    birkly_ai_agents_ensure_personal();

    $visible = [];
    foreach (birkly_ai_agents_list() as $agent) {
        $agent = birkly_ai_agents_normalize_agent($agent);
        if (!$includePersonal && ($agent['type'] ?? '') === 'personal') {
            continue;
        }
        if (birkly_ai_user_can_see_agent($agent, $user)) {
            $visible[] = birkly_ai_agents_scrub($agent);
        }
    }

    return $visible;
}

function birkly_ai_agents_list_service_for_user(array $user): array
{
    return array_values(array_filter(
        birkly_ai_agents_list_for_user($user, false),
        static fn(array $agent): bool => ($agent['type'] ?? '') === 'service'
    ));
}

function birkly_ai_agents_load_authorizer_user(string $userId): ?array
{
    if ($userId === '') {
        return null;
    }

    $storage = new Storage(__DIR__ . '/../users');
    $user = $storage->read($userId . '.json');
    return is_array($user) ? $user : null;
}

function birkly_ai_agents_permissions_for_user(array $user): array
{
    if (!function_exists('getPermissionsForRole')) {
        require_once __DIR__ . '/../api/ai_helpers.php';
    }

    $rolePerms = getPermissionsForRole((string) ($user['role'] ?? 'viewer'));
    $explicit = $user['permissions'] ?? [];
    if (!is_array($explicit)) {
        $explicit = [];
    }

    return array_values(array_unique(array_merge($rolePerms, $explicit)));
}

function birkly_ai_agents_mcp_actor_for_token(array $endpoint, ?array $agent, array $tokenEntry): array
{
    $actor = birkly_mcp_endpoint_to_ai_user($endpoint);
    $authorizedBy = (string) ($tokenEntry['authorized_by'] ?? '');
    $agent = $agent !== null ? birkly_ai_agents_normalize_agent($agent) : null;
    $agentType = (string) ($tokenEntry['agent_type'] ?? $agent['type'] ?? 'service');

    $actor['authorized_by'] = $authorizedBy;
    $actor['agent_type'] = $agentType;

    if ($agentType === 'personal' && $authorizedBy !== '') {
        $authorizer = birkly_ai_agents_load_authorizer_user($authorizedBy);
        if ($authorizer !== null) {
            $actor['role'] = (string) ($authorizer['role'] ?? 'viewer');
            $actor['permissions'] = birkly_ai_agents_permissions_for_user($authorizer);
            $actor['name'] = 'Personal: ' . (string) ($authorizer['email'] ?? $authorizer['id'] ?? 'user');
        }
        return $actor;
    }

    $agentRole = (string) ($agent['role'] ?? $endpoint['role'] ?? 'viewer');
    $authorizedRole = trim((string) ($tokenEntry['authorized_role'] ?? ''));
    $authorizer = $authorizedBy !== '' ? birkly_ai_agents_load_authorizer_user($authorizedBy) : null;

    if ($authorizer !== null && birkly_ai_agents_is_admin($authorizer)) {
        $actor['role'] = $agentRole;
    } elseif ($authorizedRole !== '') {
        $actor['role'] = birkly_ai_effective_role($agentRole, $authorizedRole);
        $actor['authorized_role'] = $authorizedRole;
    } else {
        $actor['role'] = $agentRole;
    }

    return $actor;
}

function birkly_ai_agents_migrate_legacy_row(array $agent): array
{
    $agent = birkly_ai_agents_normalize_agent($agent);

    if (($agent['id'] ?? '') === BIRKLY_AI_AGENT_PERSONAL_ID) {
        $agent['type'] = 'personal';
        $agent['visible_to_roles'] = [];
        $agent['allowed_users'] = [];
        return $agent;
    }

    if (!isset($agent['type']) || $agent['type'] === 'service') {
        $agent['type'] = 'service';
        if (($agent['visible_to_roles'] ?? []) === [] && ($agent['allowed_users'] ?? []) === []) {
            $agent['visible_to_roles'] = ['admin'];
        }
    }

    return $agent;
}

function birkly_ai_agents_create(array $input, string $createdBy): array
{
    $name = trim($input['name'] ?? '');
    $role = (string) ($input['role'] ?? 'viewer');
    $description = trim((string) ($input['description'] ?? ''));
    $visibleToRoles = birkly_ai_agents_normalize_string_list($input['visible_to_roles'] ?? ['admin']);
    $allowedUsers = birkly_ai_agents_normalize_string_list($input['allowed_users'] ?? []);

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
    $agent = birkly_ai_agents_normalize_agent([
        'id' => $id,
        'type' => 'service',
        'name' => $name,
        'description' => $description,
        'role' => $role,
        'visible_to_roles' => $visibleToRoles,
        'allowed_users' => $allowedUsers,
        'require_approval' => $endpoint['require_approval'] ?? true,
        'mcp_endpoint_id' => $endpoint['id'],
        'connector_url' => birkly_ai_agent_connector_url($id),
        'status' => 'active',
        'created_at' => date('c'),
        'created_by' => $createdBy,
    ]);

    $store = birkly_ai_agents_load_store();
    $store['agents'][] = $agent;
    birkly_ai_agents_save_store($store);

    $agent['mcp_api_key'] = $endpoint['mcp_api_key'];
    return $agent;
}

function birkly_ai_agents_update(string $id, array $input, ?array $currentUser = null): ?array
{
    if ($id === BIRKLY_AI_AGENT_PERSONAL_ID) {
        throw new RuntimeException('The personal connector cannot be edited like a service agent');
    }

    $store = birkly_ai_agents_load_store();
    $updated = null;

    foreach ($store['agents'] as &$agent) {
        if (($agent['id'] ?? '') !== $id) {
            continue;
        }
        if (($agent['status'] ?? 'active') === 'revoked') {
            throw new RuntimeException('Cannot update a revoked agent');
        }

        $agent = birkly_ai_agents_normalize_agent($agent);

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

        $canEditVisibility = $currentUser === null || birkly_ai_agents_is_admin($currentUser);
        if ($canEditVisibility) {
            if (array_key_exists('visible_to_roles', $input)) {
                $agent['visible_to_roles'] = birkly_ai_agents_normalize_string_list($input['visible_to_roles']);
            }
            if (array_key_exists('allowed_users', $input)) {
                $agent['allowed_users'] = birkly_ai_agents_normalize_string_list($input['allowed_users']);
            }
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

function birkly_ai_agents_set_personal_status(string $status): ?array
{
    if (!in_array($status, ['active', 'disabled'], true)) {
        throw new InvalidArgumentException('Invalid personal agent status');
    }

    $store = birkly_ai_agents_load_store();
    $updated = null;

    foreach ($store['agents'] as &$agent) {
        if (($agent['id'] ?? '') !== BIRKLY_AI_AGENT_PERSONAL_ID) {
            continue;
        }
        $agent['status'] = $status;
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
    if ($id === BIRKLY_AI_AGENT_PERSONAL_ID) {
        throw new RuntimeException('The personal connector cannot be deleted. Disable it instead.');
    }

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
    $agent = birkly_ai_agents_normalize_agent($agent);
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
