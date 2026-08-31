<?php
/**
 * CMS MCP admin catalog + dispatch — full core admin API parity for connected AI.
 *
 * Use cms_list_admin_actions to discover actions, then cms_admin_action with
 * action "domain.subaction" (e.g. entries.bulk, agent_guide.publish).
 */

declare(strict_types=1);

require_once __DIR__ . '/cms_mcp_handlers.php';

/** @return list<array{group: string, actions: list<array{action: string, description: string, write?: bool, permission?: string}>}> */
function cms_mcp_admin_action_catalog(): array
{
    return [
        [
            'group' => 'entries',
            'actions' => [
                ['action' => 'entries.bulk', 'description' => 'Bulk publish/unpublish/delete/restore entries', 'write' => true, 'permission' => 'write'],
                ['action' => 'entries.review', 'description' => 'Approve/reject/request changes on pending_review entry', 'write' => true, 'permission' => 'write'],
                ['action' => 'entries.duplicate', 'description' => 'Duplicate an entry', 'write' => true, 'permission' => 'write'],
                ['action' => 'entries.restore', 'description' => 'Restore trashed entry', 'write' => true, 'permission' => 'write'],
                ['action' => 'entries.versions', 'description' => 'List entry version history', 'permission' => 'read'],
                ['action' => 'entries.restore_version', 'description' => 'Restore entry to a prior version', 'write' => true, 'permission' => 'write'],
                ['action' => 'entries.diff', 'description' => 'Diff entry versions', 'permission' => 'read'],
                ['action' => 'entries.bulk_field_edit', 'description' => 'Bulk edit a field across entries', 'write' => true, 'permission' => 'write'],
                ['action' => 'entries.list', 'description' => 'List entries (admin view)', 'permission' => 'read'],
                ['action' => 'entries.save', 'description' => 'Save entry via admin editor path (reviewer, SEO meta, notifications)', 'write' => true, 'permission' => 'write'],
            ],
        ],
        [
            'group' => 'content',
            'actions' => [
                ['action' => 'content.duplicate_collection', 'description' => 'Duplicate a collection', 'write' => true, 'permission' => 'manage_content'],
                ['action' => 'content.set_entry_order', 'description' => 'Set manual entry sort order', 'write' => true, 'permission' => 'write'],
            ],
        ],
        [
            'group' => 'sub_entries',
            'actions' => [
                ['action' => 'sub_entries.set_order', 'description' => 'Set sub-entry order', 'write' => true, 'permission' => 'write'],
                ['action' => 'sub_entries.reorder', 'description' => 'Alias for set_order', 'write' => true, 'permission' => 'write'],
                ['action' => 'sub_entries.bulk', 'description' => 'Bulk sub-entry delete', 'write' => true, 'permission' => 'delete'],
                ['action' => 'sub_entries.count', 'description' => 'Count sub-entries', 'permission' => 'read'],
            ],
        ],
        [
            'group' => 'connections',
            'actions' => [
                ['action' => 'connections.list', 'description' => 'List Connections (no secrets)', 'permission' => 'manage_settings'],
                ['action' => 'connections.get', 'description' => 'Get one Connection', 'permission' => 'manage_settings'],
                ['action' => 'connections.providers', 'description' => 'List connection provider types', 'permission' => 'manage_settings'],
                ['action' => 'connections.email_picker', 'description' => 'Email connections for picker UI', 'permission' => 'read'],
                ['action' => 'connections.create', 'description' => 'Create Connection', 'write' => true, 'permission' => 'manage_settings'],
                ['action' => 'connections.update', 'description' => 'Update Connection', 'write' => true, 'permission' => 'manage_settings'],
                ['action' => 'connections.delete', 'description' => 'Delete Connection', 'write' => true, 'permission' => 'manage_settings'],
                ['action' => 'connections.set_default', 'description' => 'Set default Connection for type', 'write' => true, 'permission' => 'manage_settings'],
                ['action' => 'connections.dns_hints', 'description' => 'DNS hints for email provider', 'permission' => 'manage_settings'],
                ['action' => 'connections.test', 'description' => 'Test Connection', 'write' => true, 'permission' => 'manage_settings'],
            ],
        ],
        [
            'group' => 'agent_guide',
            'actions' => [
                ['action' => 'agent_guide.get', 'description' => 'Get draft/published Agent Guide state', 'permission' => 'read'],
                ['action' => 'agent_guide.save_draft', 'description' => 'Save Agent Guide draft', 'write' => true, 'permission' => 'manage_settings'],
                ['action' => 'agent_guide.publish', 'description' => 'Publish Agent Guide', 'write' => true, 'permission' => 'manage_settings'],
                ['action' => 'agent_guide.unpublish', 'description' => 'Unpublish Agent Guide', 'write' => true, 'permission' => 'manage_settings'],
                ['action' => 'agent_guide.cms_refresh', 'description' => 'Sync CMS content into Agent Guide draft', 'write' => true, 'permission' => 'manage_settings'],
                ['action' => 'agent_guide.cms_diff', 'description' => 'Diff Agent Guide draft vs CMS sources', 'permission' => 'read'],
                ['action' => 'agent_guide.completeness', 'description' => 'Agent Guide completeness score', 'permission' => 'read'],
                ['action' => 'agent_guide.staleness', 'description' => 'Agent Guide staleness vs CMS', 'permission' => 'read'],
                ['action' => 'agent_guide.discovery_status', 'description' => 'AI discovery / sitemap / IndexNow status', 'permission' => 'read'],
                ['action' => 'agent_guide.list_versions', 'description' => 'List published Agent Guide versions', 'permission' => 'read'],
                ['action' => 'agent_guide.list_collections', 'description' => 'Collections for Agent Guide wizard', 'permission' => 'read'],
                ['action' => 'agent_guide.list_collection_entries', 'description' => 'Entries in a collection for wizard', 'permission' => 'read'],
                ['action' => 'agent_guide.copilot', 'description' => 'AI copilot suggestions for Agent Guide', 'write' => true, 'permission' => 'manage_settings'],
                ['action' => 'agent_guide.wizard_generate', 'description' => 'Generate Agent Guide from collections', 'write' => true, 'permission' => 'manage_settings'],
                ['action' => 'agent_guide.wizard_apply', 'description' => 'Apply wizard output to draft', 'write' => true, 'permission' => 'manage_settings'],
                ['action' => 'agent_guide.validate_links', 'description' => 'Validate links in draft', 'write' => true, 'permission' => 'manage_settings'],
                ['action' => 'agent_guide.accept_suggestion', 'description' => 'Apply one copilot suggestion to draft', 'write' => true, 'permission' => 'manage_settings'],
                ['action' => 'agent_guide.preview_query', 'description' => 'Preview Agent Guide Q&A', 'permission' => 'read'],
                ['action' => 'agent_guide.public_urls', 'description' => 'Public Agent Guide URLs', 'permission' => 'read'],
                ['action' => 'agent_guide.setup_prefill', 'description' => 'Setup wizard prefill from site', 'permission' => 'read'],
                ['action' => 'agent_guide.fetch_url_meta', 'description' => 'Fetch Open Graph metadata for URL', 'permission' => 'read'],
            ],
        ],
        [
            'group' => 'mcp_endpoints',
            'actions' => [
                ['action' => 'mcp_endpoints.list', 'description' => 'List MCP OAuth endpoints', 'permission' => 'manage_settings'],
                ['action' => 'mcp_endpoints.create', 'description' => 'Create MCP endpoint', 'write' => true, 'permission' => 'manage_settings'],
                ['action' => 'mcp_endpoints.update', 'description' => 'Update MCP endpoint', 'write' => true, 'permission' => 'manage_settings'],
                ['action' => 'mcp_endpoints.rotate', 'description' => 'Rotate MCP endpoint key', 'write' => true, 'permission' => 'manage_settings'],
                ['action' => 'mcp_endpoints.revoke', 'description' => 'Revoke MCP endpoint', 'write' => true, 'permission' => 'manage_settings'],
            ],
        ],
        [
            'group' => 'ai_agents',
            'actions' => [
                ['action' => 'ai_agents.list', 'description' => 'List AI agents', 'permission' => 'manage_settings'],
                ['action' => 'ai_agents.create', 'description' => 'Create AI agent', 'write' => true, 'permission' => 'manage_settings'],
                ['action' => 'ai_agents.update', 'description' => 'Update AI agent', 'write' => true, 'permission' => 'manage_settings'],
                ['action' => 'ai_agents.revoke', 'description' => 'Revoke AI agent', 'write' => true, 'permission' => 'manage_settings'],
            ],
        ],
        [
            'group' => 'users',
            'actions' => [
                ['action' => 'users.list', 'description' => 'List human user accounts', 'permission' => 'manage_users'],
                ['action' => 'users.create', 'description' => 'Create human user account', 'write' => true, 'permission' => 'manage_users'],
                ['action' => 'users.update', 'description' => 'Update user role, email, or 2FA grace', 'write' => true, 'permission' => 'manage_users'],
                ['action' => 'users.delete', 'description' => 'Delete human user account', 'write' => true, 'permission' => 'delete_user'],
                ['action' => 'users.revoke_mcp_sessions', 'description' => 'Revoke OAuth MCP sessions for user', 'write' => true, 'permission' => 'manage_users'],
            ],
        ],
        [
            'group' => 'roles',
            'actions' => [
                ['action' => 'roles.list', 'description' => 'List all roles including plugin_access', 'permission' => 'manage_users'],
                ['action' => 'roles.list_plugins', 'description' => 'List active plugins for role matrix', 'permission' => 'manage_users'],
                ['action' => 'roles.get', 'description' => 'Get one role by id', 'permission' => 'manage_users'],
                ['action' => 'roles.create', 'description' => 'Create role', 'write' => true, 'permission' => 'manage_users'],
                ['action' => 'roles.update', 'description' => 'Update role (permissions, plugin_access)', 'write' => true, 'permission' => 'manage_users'],
                ['action' => 'roles.delete', 'description' => 'Delete role', 'write' => true, 'permission' => 'manage_users'],
            ],
        ],
        [
            'group' => 'invitations',
            'actions' => [
                ['action' => 'invitations.list', 'description' => 'List pending invitations', 'permission' => 'manage_users'],
                ['action' => 'invitations.create', 'description' => 'Send user invitation email', 'write' => true, 'permission' => 'manage_users'],
                ['action' => 'invitations.resend', 'description' => 'Resend invitation email', 'write' => true, 'permission' => 'manage_users'],
                ['action' => 'invitations.cancel', 'description' => 'Cancel pending invitation', 'write' => true, 'permission' => 'manage_users'],
            ],
        ],
        [
            'group' => 'marketplace',
            'actions' => [
                ['action' => 'marketplace.browse', 'description' => 'Browse Hub offers', 'permission' => 'manage_plugins'],
                ['action' => 'marketplace.offer', 'description' => 'Get offer detail', 'permission' => 'manage_plugins'],
                ['action' => 'marketplace.install', 'description' => 'Install offer from Hub', 'write' => true, 'permission' => 'manage_plugins'],
                ['action' => 'marketplace.list_hub_licenses', 'description' => 'List Hub licenses', 'permission' => 'manage_plugins'],
                ['action' => 'marketplace.activate_license', 'description' => 'Activate license key', 'write' => true, 'permission' => 'manage_plugins'],
                ['action' => 'marketplace.deactivate_license', 'description' => 'Deactivate Hub license', 'write' => true, 'permission' => 'manage_plugins'],
                ['action' => 'marketplace.check_updates', 'description' => 'Check Hub update for installed plugin', 'permission' => 'manage_plugins'],
                ['action' => 'marketplace.upgrade', 'description' => 'Upgrade installed plugin from Hub', 'write' => true, 'permission' => 'manage_plugins'],
                ['action' => 'marketplace.list_installed_template_packs', 'description' => 'List installed template packs', 'permission' => 'manage_plugins'],
                ['action' => 'marketplace.preflight_template_pack', 'description' => 'Preflight template pack install by slug', 'permission' => 'manage_plugins'],
                ['action' => 'marketplace.template_pack_status', 'description' => 'Template pack update / touched-file status', 'permission' => 'manage_plugins'],
            ],
        ],
        [
            'group' => 'project_git',
            'actions' => [
                ['action' => 'project_git.status', 'description' => 'Git status for project/', 'permission' => 'manage_project_site'],
                ['action' => 'project_git.connect', 'description' => 'Connect Git remote', 'write' => true, 'permission' => 'manage_project_site'],
                ['action' => 'project_git.push', 'description' => 'Push project/ to remote', 'write' => true, 'permission' => 'manage_project_site'],
                ['action' => 'project_git.pull_preview', 'description' => 'Preview pull changes', 'permission' => 'manage_project_site'],
                ['action' => 'project_git.pull_apply', 'description' => 'Apply pull', 'write' => true, 'permission' => 'manage_project_site'],
            ],
        ],
        [
            'group' => 'project_site',
            'actions' => [
                ['action' => 'project_site.overview', 'description' => 'Project site overview', 'permission' => 'manage_project_site'],
                ['action' => 'project_site.save_settings', 'description' => 'Save project site settings', 'write' => true, 'permission' => 'manage_project_site'],
                ['action' => 'project_site.invalidate_ssr_cache', 'description' => 'Invalidate SSR cache', 'write' => true, 'permission' => 'manage_project_site'],
                ['action' => 'project_site.create_folder', 'description' => 'Create folder in project/', 'write' => true, 'permission' => 'manage_project_site'],
            ],
        ],
        [
            'group' => 'webhooks',
            'actions' => [
                ['action' => 'webhooks.list', 'description' => 'List outbound webhooks', 'permission' => 'manage_settings'],
                ['action' => 'webhooks.create', 'description' => 'Create webhook', 'write' => true, 'permission' => 'manage_settings'],
                ['action' => 'webhooks.update', 'description' => 'Update webhook', 'write' => true, 'permission' => 'manage_settings'],
                ['action' => 'webhooks.delete', 'description' => 'Delete webhook', 'write' => true, 'permission' => 'manage_settings'],
                ['action' => 'webhooks.test', 'description' => 'Test webhook', 'write' => true, 'permission' => 'manage_settings'],
            ],
        ],
        [
            'group' => 'redirects',
            'actions' => [
                ['action' => 'redirects.list', 'description' => 'List URL redirects', 'permission' => 'manage_settings'],
                ['action' => 'redirects.save', 'description' => 'Create/update redirect', 'write' => true, 'permission' => 'manage_settings'],
                ['action' => 'redirects.delete', 'description' => 'Delete redirect', 'write' => true, 'permission' => 'manage_settings'],
            ],
        ],
        [
            'group' => 'plugins',
            'actions' => [
                ['action' => 'plugins.get_settings', 'description' => 'Get plugin settings', 'permission' => 'manage_plugins'],
                ['action' => 'plugins.save_settings', 'description' => 'Save plugin settings', 'write' => true, 'permission' => 'manage_plugins'],
                ['action' => 'plugins.update', 'description' => 'Update plugin from Hub', 'write' => true, 'permission' => 'manage_plugins'],
                ['action' => 'plugins.delete', 'description' => 'Deactivate plugin', 'write' => true, 'permission' => 'manage_plugins'],
            ],
        ],
        [
            'group' => 'plugins_permissions',
            'actions' => [
                ['action' => 'plugins_permissions.get', 'description' => 'Get plugin capability permissions', 'permission' => 'manage_plugins'],
                ['action' => 'plugins_permissions.grant_capability', 'description' => 'Grant plugin capability', 'write' => true, 'permission' => 'manage_plugins'],
                ['action' => 'plugins_permissions.revoke_capability', 'description' => 'Revoke plugin capability', 'write' => true, 'permission' => 'manage_plugins'],
            ],
        ],
        [
            'group' => 'search',
            'actions' => [
                ['action' => 'search.admin_search', 'description' => 'Admin global search', 'permission' => 'read'],
            ],
        ],
        [
            'group' => 'analytics',
            'actions' => [
                ['action' => 'analytics.get_settings', 'description' => 'Get analytics settings', 'permission' => 'manage_settings'],
                ['action' => 'analytics.save_settings', 'description' => 'Save analytics settings', 'write' => true, 'permission' => 'manage_settings'],
                ['action' => 'analytics.presets', 'description' => 'List analytics presets', 'permission' => 'manage_settings'],
                ['action' => 'analytics.data', 'description' => 'Get analytics report data', 'permission' => 'manage_settings'],
            ],
        ],
        [
            'group' => 'webmcp',
            'actions' => [
                ['action' => 'webmcp.get', 'description' => 'Get WebMCP site tool config', 'permission' => 'manage_settings'],
                ['action' => 'webmcp.save', 'description' => 'Save WebMCP config', 'write' => true, 'permission' => 'manage_settings'],
            ],
        ],
        [
            'group' => 'components',
            'actions' => [
                ['action' => 'components.list', 'description' => 'List content editor components', 'permission' => 'manage_content'],
                ['action' => 'components.get', 'description' => 'Get component by slug', 'permission' => 'manage_content'],
                ['action' => 'components.save', 'description' => 'Save component', 'write' => true, 'permission' => 'manage_content'],
                ['action' => 'components.delete', 'description' => 'Delete component', 'write' => true, 'permission' => 'manage_content'],
                ['action' => 'components.render', 'description' => 'Preview component with entry data', 'permission' => 'manage_content'],
                ['action' => 'components.find_entries', 'description' => 'Find entries using a component', 'permission' => 'manage_content'],
                ['action' => 'components.update_entries', 'description' => 'Propagate component changes to all entries', 'write' => true, 'permission' => 'manage_content'],
            ],
        ],
        [
            'group' => 'settings',
            'actions' => [
                ['action' => 'settings.get', 'description' => 'Read settings by category (site, admin, media, security, branding, all)', 'permission' => 'manage_settings'],
                ['action' => 'settings.save', 'description' => 'Merge settings into a category file', 'write' => true, 'permission' => 'manage_settings'],
                ['action' => 'settings.rebuild_system', 'description' => 'Rebuild system cache', 'write' => true, 'permission' => 'manage_settings'],
            ],
        ],
        [
            'group' => 'notifications',
            'actions' => [
                ['action' => 'notifications.list_recent', 'description' => 'List recent inbox notifications', 'permission' => 'read'],
                ['action' => 'notifications.unread_count', 'description' => 'Unread notification badge count', 'permission' => 'read'],
                ['action' => 'notifications.mark_seen', 'description' => 'Mark notifications seen (dropdown opened)', 'write' => true, 'permission' => 'read'],
                ['action' => 'notifications.mark_read', 'description' => 'Mark one notification read', 'write' => true, 'permission' => 'read'],
                ['action' => 'notifications.mark_all_read', 'description' => 'Mark all notifications read', 'write' => true, 'permission' => 'read'],
            ],
        ],
        [
            'group' => 'dashboard',
            'actions' => [
                ['action' => 'dashboard.stats', 'description' => 'Dashboard stats summary', 'permission' => 'read'],
                ['action' => 'dashboard.pending_review', 'description' => 'Entries pending review', 'permission' => 'read'],
                ['action' => 'dashboard.activity', 'description' => 'Recent admin activity feed', 'permission' => 'read'],
            ],
        ],
        [
            'group' => 'media',
            'actions' => [
                ['action' => 'media.list', 'description' => 'List media with folder/search/type/sort filters', 'permission' => 'read_media'],
                ['action' => 'media.get', 'description' => 'Get one media item with full metadata', 'permission' => 'read_media'],
                ['action' => 'media.folders', 'description' => 'List media folder tree with counts', 'permission' => 'read_media'],
                ['action' => 'media.upload', 'description' => 'Upload media (multipart file in MCP payload not supported — use upload_media for url/base64)', 'write' => true, 'permission' => 'upload'],
                ['action' => 'media.update', 'description' => 'Update title, alt_text, focal point', 'write' => true, 'permission' => 'manage_media'],
                ['action' => 'media.delete', 'description' => 'Delete a media file', 'write' => true, 'permission' => 'manage_media'],
                ['action' => 'media.create_folder', 'description' => 'Create media folder', 'write' => true, 'permission' => 'manage_media'],
                ['action' => 'media.rename_folder', 'description' => 'Rename media folder and update metadata refs', 'write' => true, 'permission' => 'manage_media'],
                ['action' => 'media.delete_folder', 'description' => 'Delete folder; handle_files: move (default) or delete', 'write' => true, 'permission' => 'manage_media'],
                ['action' => 'media.move', 'description' => 'Move media file to folder (updates url)', 'write' => true, 'permission' => 'manage_media'],
            ],
        ],
    ];
}

/** @return array<string, array{description: string, write: bool, permission: string}> */
function cms_mcp_admin_action_meta(): array
{
    static $meta = null;
    if ($meta !== null) {
        return $meta;
    }
    $meta = [];
    foreach (cms_mcp_admin_action_catalog() as $group) {
        foreach ($group['actions'] as $row) {
            $meta[$row['action']] = [
                'description' => $row['description'],
                'write' => !empty($row['write']),
                'permission' => (string) ($row['permission'] ?? 'read'),
            ];
        }
    }
    return $meta;
}

/** @return string[]|null */
function cms_mcp_permissions_for_action(string $action): ?array
{
    $meta = cms_mcp_admin_action_meta()[$action] ?? null;
    if ($meta === null) {
        return null;
    }
    $perm = $meta['permission'];
    if ($perm === 'manage_content') {
        return ['manage_content', 'write', 'read'];
    }
    if ($perm === 'manage_settings') {
        return ['manage_settings', 'read_settings'];
    }
    if ($perm === 'manage_users') {
        return ['manage_users', 'list_users'];
    }
    if ($perm === 'manage_plugins') {
        return ['manage_plugins', 'read'];
    }
    if ($perm === 'manage_project_site') {
        return ['manage_project_site', 'read'];
    }
    if ($perm === 'read_media') {
        return ['read_media', 'read', 'manage_media'];
    }
    if ($perm === 'manage_media') {
        return ['manage_media', 'upload', 'delete'];
    }
    if ($perm === 'upload') {
        return ['upload', 'manage_media'];
    }
    if ($perm === 'delete') {
        return ['delete', 'manage_content', 'delete_user'];
    }
    return ['read', 'write', $perm];
}

/**
 * Dispatch a CMS admin action for MCP.
 *
 * @return array<string, mixed>
 */
function cms_mcp_dispatch_admin(string $action, array $input, array $aiUser): array
{
    $action = trim($action);
    if ($action === '') {
        return cms_mcp_error('action is required');
    }

    $meta = cms_mcp_admin_action_meta()[$action] ?? null;
    if ($meta === null) {
        return cms_mcp_error('Unknown CMS admin action: ' . $action);
    }

    $required = cms_mcp_permissions_for_action($action);
    if ($required === null || !cms_mcp_user_can($aiUser, $required)) {
        return cms_mcp_error('Permission denied for ' . $action);
    }

    $parts = explode('.', $action, 2);
    if (count($parts) < 2) {
        return cms_mcp_error('Invalid action format');
    }

    [$domain, $subAction] = $parts;

    try {
        return cms_mcp_dispatch_domain($domain, $subAction, $input, $aiUser);
    } catch (Throwable $e) {
        return cms_mcp_error('CMS admin action failed: ' . $e->getMessage());
    }
}

/** @return array{isError?: bool, content: list<array{type: string, text: string}>} */
function cms_mcp_admin_result_to_mcp_content(array $result): array
{
    if (($result['status'] ?? '') !== 'ok') {
        return [
            'isError' => true,
            'content' => [['type' => 'text', 'text' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)]],
        ];
    }
    $payload = $result['data'] ?? $result;
    unset($payload['status']);
    return ['content' => [['type' => 'text', 'text' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)]]];
}
