<?php
/**
 * Email-picker permissions. Core keeps its own admin permissions.
 * Plugins register the extra slugs that may open the picker.
 */

$birkly_email_picker_permissions = [];

function register_email_picker_permission(string $permission, string $plugin_id = ''): bool
{
    global $birkly_email_picker_permissions;

    $permission = trim($permission);
    if ($permission === '' || !preg_match('/^[a-z0-9_]+$/', $permission)) {
        return false;
    }

    $birkly_email_picker_permissions[$permission] = [
        'permission' => $permission,
        'plugin_id' => $plugin_id,
    ];

    return true;
}

/**
 * @return list<string>
 */
function birkly_email_picker_permissions(): array
{
    global $birkly_email_picker_permissions;

    $permissions = ['manage_settings', 'manage_content'];
    foreach ($birkly_email_picker_permissions as $entry) {
        $pluginId = (string) ($entry['plugin_id'] ?? '');
        if ($pluginId !== '' && function_exists('birkly_is_plugin_active') && !birkly_is_plugin_active($pluginId)) {
            continue;
        }
        $permissions[] = (string) $entry['permission'];
    }

    return array_values(array_unique($permissions));
}

function birkly_email_picker_allowed($user): bool
{
    if (!is_array($user) || !function_exists('checkPermission')) {
        return false;
    }
    foreach (birkly_email_picker_permissions() as $permission) {
        if (checkPermission($user, $permission)) {
            return true;
        }
    }

    return false;
}
