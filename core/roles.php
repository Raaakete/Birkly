<?php
/**
 * CMS role definitions and seeding (P5.2-M1).
 */

require_once __DIR__ . '/birkly_paths.php';

/**
 * Default CMS roles with resource-based permissions matching legacy fallback in api/ai_helpers.php.
 *
 * @return list<array{id: string, name: string, description: string, permissions: array<string, list<string>>}>
 */
function birkly_default_roles(): array
{
    $allActions = ['view', 'edit', 'create', 'delete'];
    $writeActions = ['view', 'edit', 'create'];

    return [
        [
            'id' => 'admin',
            'name' => 'Admin',
            'description' => 'Full access to all CMS features',
            'permissions' => [
                '*' => $allActions,
                'project_site' => $allActions,
            ],
        ],
        [
            'id' => 'editor',
            'name' => 'Editor',
            'description' => 'Manage content, media, and plugins',
            'permissions' => [
                'collections' => $allActions,
                'media' => $allActions,
                'plugins' => $allActions,
            ],
        ],
        [
            'id' => 'author',
            'name' => 'Author',
            'description' => 'Create and edit content and media',
            'permissions' => [
                'collections' => $writeActions,
                'media' => $writeActions,
            ],
        ],
        [
            'id' => 'viewer',
            'name' => 'Viewer',
            'description' => 'Read-only access to content',
            'permissions' => [
                'collections' => ['view'],
            ],
        ],
    ];
}

function birkly_roles_file_path(): string
{
    return birkly_project_root() . '/settings/roles.json';
}

/**
 * Seed settings/roles.json when missing or empty; return current role list.
 *
 * @return list<array<string, mixed>>
 */
function birkly_ensure_default_roles(): array
{
    $path = birkly_roles_file_path();

    if (is_file($path)) {
        $decoded = json_decode((string) file_get_contents($path), true);
        if (is_array($decoded) && $decoded !== []) {
            return $decoded;
        }
    }

    $roles = birkly_default_roles();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($path, json_encode($roles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return $roles;
}

/**
 * Role id strings from roles.json (seeds defaults when needed).
 *
 * @return list<string>
 */
function birkly_list_role_ids(): array
{
    $roles = birkly_ensure_default_roles();
    $ids = [];
    foreach ($roles as $role) {
        if (!empty($role['id'])) {
            $ids[] = (string) $role['id'];
        }
    }

    return $ids;
}

/**
 * @return array<string, mixed>|null
 */
function birkly_role_find(string $idOrName): ?array
{
    $roles = birkly_ensure_default_roles();
    $needle = strtolower(trim($idOrName));
    foreach ($roles as $role) {
        $id = strtolower((string) ($role['id'] ?? ''));
        $name = strtolower((string) ($role['name'] ?? ''));
        if ($id === $needle || $name === $needle) {
            return $role;
        }
    }
    return null;
}

/**
 * Whether a CMS role may access a plugin admin surface (P69).
 */
function birkly_role_plugin_allowed(string $roleIdOrName, string $pluginId): bool
{
    $pluginId = trim($pluginId);
    if ($pluginId === '') {
        return true;
    }

    $role = birkly_role_find($roleIdOrName);
    if ($role === null) {
        return true;
    }

    $access = $role['plugin_access'] ?? null;
    if (!is_array($access)) {
        return true;
    }

    $mode = (string) ($access['mode'] ?? 'all');
    $deny = array_map('strval', is_array($access['deny'] ?? null) ? $access['deny'] : []);
    $allow = array_map('strval', is_array($access['allow'] ?? null) ? $access['allow'] : []);

    if ($mode === 'only') {
        return in_array($pluginId, $allow, true);
    }

    if ($mode === 'all' || $mode === '') {
        return true;
    }

    if ($mode === 'all_except' || $deny !== []) {
        return !in_array($pluginId, $deny, true);
    }

    return true;
}

function birkly_user_plugin_allowed(array $user, string $pluginId): bool
{
    $role = (string) ($user['role'] ?? 'viewer');
    return birkly_role_plugin_allowed($role, $pluginId);
}
