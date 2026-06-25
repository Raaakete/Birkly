<?php
/**
 * AI action permission enforcement (P3-M2).
 * Maps AI/MCP action types to flat permissions checked via checkPermission().
 */

require_once __DIR__ . '/../api/auth.php';

/**
 * Permission sets per AI action (any match grants access).
 *
 * @return array<string, string[]>
 */
function birkly_ai_action_permission_map(): array
{
    return [
        // Content reads
        'read_entry' => ['read'],
        'list_entries' => ['read'],
        'read_collection' => ['read'],
        'list_collections' => ['read'],

        // Media reads
        'list_media' => ['read', 'read_media', 'manage_media'],
        'read_media' => ['read', 'read_media', 'manage_media'],

        // User reads
        'list_users' => ['list_users', 'manage_users'],
        'read_user' => ['list_users', 'manage_users'],

        // Settings / roles reads
        'read_settings' => ['read_settings', 'manage_settings'],
        'list_roles' => ['read_settings', 'manage_settings'],
        'read_role' => ['read_settings', 'manage_settings'],

        // Pages / activity
        'list_pages' => ['read', 'manage_content'],
        'read_page' => ['read', 'manage_content'],
        'list_activity' => ['read', 'manage_content'],

        // Plugins read
        'list_plugins' => ['manage_plugins', 'read'],

        // Content writes
        'create_collection' => ['manage_content', 'write'],
        'update_collection' => ['manage_content', 'write'],
        'delete_collection' => ['delete', 'manage_content'],
        'create_entry' => ['write', 'manage_content'],
        'update_entry' => ['write', 'manage_content'],
        'delete_entry' => ['delete', 'manage_content'],

        // Media writes
        'upload_media' => ['upload', 'manage_media'],
        'delete_media' => ['manage_media', 'delete'],
        'update_media' => ['manage_media', 'write'],
        'create_media_folder' => ['manage_media'],
        'delete_media_folder' => ['manage_media'],
        'move_media' => ['manage_media'],

        // Settings / pages writes
        'update_settings' => ['manage_settings'],
        'create_page' => ['manage_content', 'write'],
        'update_page' => ['manage_content', 'write'],
        'delete_page' => ['delete', 'manage_content'],

        // User / role writes
        'update_user' => ['update_user', 'manage_users'],
        'delete_user' => ['delete_user', 'manage_users'],
        'create_role' => ['manage_settings'],
        'update_role' => ['manage_settings'],
        'delete_role' => ['manage_settings'],

        // Plugins writes
        'activate_plugin' => ['manage_plugins'],
        'deactivate_plugin' => ['manage_plugins'],

        // MCP tool aliases
        'get_collection' => ['read'],
        'get_system_context' => ['read'],
    ];
}

/**
 * @return string[]|null Null when action is unknown (deny).
 */
function birkly_ai_permissions_for_action(string $actionType): ?array
{
    $map = birkly_ai_action_permission_map();
    return $map[$actionType] ?? null;
}

function birkly_ai_user_has_any_permission(array $user, array $permissions): bool
{
    foreach ($permissions as $permission) {
        if (checkPermission($user, $permission)) {
            return true;
        }
    }
    return false;
}

/**
 * @return bool True when the AI user may run the action.
 */
function birkly_ai_user_can_action(array $aiUser, string $actionType): bool
{
    $required = birkly_ai_permissions_for_action($actionType);
    if ($required === null) {
        return false;
    }
    return birkly_ai_user_has_any_permission($aiUser, $required);
}

/**
 * Enforce permission before executing an AI action.
 *
 * @return array{success: false, message: string}|null Null when allowed.
 */
function birkly_ai_enforce_action_permission(array $aiUser, string $actionType): ?array
{
    $required = birkly_ai_permissions_for_action($actionType);
    if ($required === null) {
        return [
            'success' => false,
            'message' => 'Unknown or unsupported AI action: ' . $actionType,
        ];
    }

    if (!birkly_ai_user_has_any_permission($aiUser, $required)) {
        $role = $aiUser['role'] ?? 'unknown';
        return [
            'success' => false,
            'message' => 'Permission denied for action ' . $actionType . ' (role: ' . $role . ')',
        ];
    }

    return null;
}
