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
