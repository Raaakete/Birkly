<?php
/**
 * Activity row formatter v2 (P69) — shared security.log + plugin bus shape.
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';

function birkly_activity_str_starts_with(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return true;
    }
    return strncmp($haystack, $needle, strlen($needle)) === 0;
}

function birkly_activity_str_contains(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return true;
    }
    return strpos($haystack, $needle) !== false;
}

/**
 * Routine public API successes are audit noise — keep in security.log, hide from Activity feed.
 */
function birkly_activity_is_routine_public_noise(string $event): bool
{
    return in_array($event, ['domain_auth_success', 'api_key_auth_success'], true);
}

function birkly_activity_details_domain(array $details): string
{
    $domain = trim((string) ($details['domain'] ?? ''));
    if ($domain !== '') {
        return $domain;
    }
    $origin = trim((string) ($details['origin'] ?? ''));
    if ($origin !== '') {
        $host = parse_url($origin, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return $host;
        }
    }
    $referer = trim((string) ($details['referer'] ?? ''));
    if ($referer !== '') {
        $host = parse_url($referer, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return $host;
        }
    }

    return '';
}

function birkly_activity_referer_page_label(string $referer): string
{
    $referer = trim($referer);
    if ($referer === '') {
        return '';
    }
    $path = parse_url($referer, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return '';
    }
    if ($path === '/') {
        return 'homepage';
    }

    return ltrim($path, '/');
}

function birkly_activity_visitor_label(array $details, string $fallbackUser = 'guest'): string
{
    $domain = birkly_activity_details_domain($details);
    if ($domain !== '') {
        return 'Visitor on ' . $domain;
    }
    $ip = trim((string) ($details['ip'] ?? ''));
    if ($ip !== '' && $ip !== 'unknown') {
        return 'Visitor (' . $ip . ')';
    }
    if ($fallbackUser !== '' && strtolower($fallbackUser) !== 'guest' && strtolower($fallbackUser) !== 'unknown') {
        return $fallbackUser;
    }

    return 'Public visitor';
}

/**
 * @return list<string>
 */
function birkly_activity_severity_options(): array
{
    return ['info', 'success', 'warning', 'danger'];
}

function birkly_activity_event_category(string $event): string
{
    $contentEvents = [
        'create_entry', 'update_entry', 'delete_entry',
        'create_collection', 'update_collection', 'delete_collection', 'duplicate_collection',
        'publish_entry', 'unpublish_entry', 'scheduled_publish', 'review_action',
        'upload_media', 'project_file_written', 'project_file_created', 'project_file_deleted',
        'write_project_file', 'create_project_file', 'delete_project_file',
        'project_settings_updated', 'project_folder_created', 'project_downloaded', 'project_deleted',
        'project_git_connected', 'project_git_disconnected', 'project_git_pushed',
        'project_git_pulled', 'project_git_conflict_resolved',
        'ai_approval_created', 'ai_approval_approved',
    ];
    $userEvents = [
        'create_user', 'update_user', 'delete_user',
        'invitation_created', 'invitation_accepted',
        'role_created', 'role_updated', 'role_deleted',
        'password_changed', 'mcp_sessions_revoked',
    ];
    $systemEvents = [
        'login_success', 'login_failed', 'logout', 'rate_limited', 'permission_denied',
        'settings_save_all', 'settings_update', 'settings_reset', 'system_rebuild',
        'storage_migrated', 'update_check', 'update_installed', 'update_failed',
        'plugin_activated', 'plugin_deactivated', 'plugin_deleted',
        'webhook_created', 'webhook_updated', 'webhook_deleted', 'webhook_triggered',
        'mcp_endpoint_created', 'mcp_endpoint_rotated', 'ai_tools_blocked',
        'public_read_denied', 'public_submit_denied', 'public_submission', 'public_submission_spam',
        'domain_auth_failed', 'domain_permission_denied', 'domain_registered', 'domain_updated',
        'domain_deleted', 'api_key_auth_failed',
    ];

    if (in_array($event, $contentEvents, true)) {
        return 'content';
    }
    if (in_array($event, $userEvents, true)) {
        return 'users';
    }
    if (in_array($event, $systemEvents, true)) {
        return 'system';
    }

    return 'other';
}

function birkly_activity_event_severity(string $event): string
{
    $danger = [
        'delete_entry', 'delete_collection', 'delete_user', 'role_deleted',
        'login_failed', 'permission_denied', 'rate_limited', 'project_deleted',
        'project_file_deleted', 'delete_project_file', 'plugin_deleted',
        'update_failed', 'update_install_failed', 'system_rebuild_failed', 'ai_tools_blocked',
    ];
    $warning = [
        'review_action', 'unpublish_entry', 'ai_approval_created', 'plugin_deactivated',
        'update_check_failed', 'project_git_conflict_resolved',
        'public_read_denied', 'public_submit_denied', 'domain_auth_failed', 'domain_permission_denied',
        'api_key_auth_failed',
    ];
    $success = [
        'publish_entry', 'scheduled_publish', 'login_success', 'invitation_accepted',
        'create_entry', 'create_collection', 'plugin_activated', 'ai_approval_approved',
        'project_git_pushed', 'update_installed', 'webhook_triggered',
    ];

    if (in_array($event, $danger, true)) {
        return 'danger';
    }
    if (in_array($event, $warning, true)) {
        return 'warning';
    }
    if (in_array($event, $success, true)) {
        return 'success';
    }

    return 'info';
}

function birkly_activity_event_icon(string $event): string
{
    $icons = [
        'create_entry' => '📝',
        'update_entry' => '✏️',
        'delete_entry' => '🗑️',
        'create_collection' => '📁',
        'update_collection' => '📁',
        'delete_collection' => '🗑️',
        'duplicate_collection' => '📁',
        'publish_entry' => '✅',
        'unpublish_entry' => '❌',
        'scheduled_publish' => '⏰',
        'review_action' => '👁️',
        'create_user' => '👤',
        'update_user' => '👤',
        'delete_user' => '🗑️',
        'role_created' => '🛡️',
        'role_updated' => '🛡️',
        'role_deleted' => '🗑️',
        'invitation_created' => '✉️',
        'invitation_accepted' => '✅',
        'login_success' => '🔓',
        'login_failed' => '🔒',
        'logout' => '🚪',
        'upload_media' => '📷',
        'project_file_written' => '📝',
        'project_file_created' => '📄',
        'project_file_deleted' => '🗑️',
        'write_project_file' => '📝',
        'create_project_file' => '📄',
        'delete_project_file' => '🗑️',
        'project_settings_updated' => '⚙️',
        'project_folder_created' => '📁',
        'project_downloaded' => '⬇️',
        'project_deleted' => '🗑️',
        'project_git_connected' => '🔗',
        'project_git_disconnected' => '🔗',
        'project_git_pushed' => '⬆️',
        'project_git_pulled' => '⬇️',
        'project_git_conflict_resolved' => '🔀',
        'ai_approval_approved' => '✅',
        'ai_approval_created' => '⏳',
        'plugin_activated' => '🧩',
        'plugin_deactivated' => '🧩',
        'plugin_deleted' => '🗑️',
        'webhook_created' => '🔗',
        'webhook_updated' => '🔗',
        'webhook_deleted' => '🗑️',
        'settings_save_all' => '⚙️',
        'settings_update' => '⚙️',
        'ai_tools_blocked' => '🚫',
        'public_read_denied' => '🔒',
        'public_submit_denied' => '🔒',
        'public_submission' => '📨',
        'domain_auth_failed' => '🌐',
        'domain_permission_denied' => '🌐',
    ];

    return $icons[$event] ?? '📌';
}

function birkly_activity_project_group(string $event): string
{
    if (birkly_activity_str_starts_with($event, 'project_git_')) {
        return 'git';
    }
    if (birkly_activity_str_contains($event, 'file')
        || in_array($event, ['write_project_file', 'create_project_file', 'delete_project_file'], true)) {
        return 'files';
    }
    if (in_array($event, [
        'project_settings_updated',
        'project_folder_created',
        'project_downloaded',
        'project_deleted',
    ], true)) {
        return 'settings';
    }

    return 'other';
}

function birkly_activity_event_color(string $event): string
{
    $severity = birkly_activity_event_severity($event);
    switch ($severity) {
        case 'danger':
            return '#dc3545';
        case 'warning':
            return '#ffc107';
        case 'success':
            return '#28a745';
        default:
            return '#6c757d';
    }
}

/**
 * @param array<string, mixed> $details
 */
function birkly_activity_event_message(string $event, array $details): string
{
    switch ($event) {
        case 'create_entry':
            $collection = (string) ($details['collection'] ?? 'collection');
            $title = (string) ($details['title'] ?? $details['id'] ?? 'entry');
            return "Created entry \"{$title}\" in {$collection}";

        case 'update_entry':
            $collection = (string) ($details['collection'] ?? 'collection');
            $title = (string) ($details['title'] ?? $details['id'] ?? 'entry');
            return "Updated entry \"{$title}\" in {$collection}";

        case 'delete_entry':
            $collection = (string) ($details['collection'] ?? 'collection');
            return "Deleted entry from {$collection}";

        case 'create_collection':
            $name = (string) ($details['name'] ?? $details['title'] ?? 'collection');
            return "Created collection \"{$name}\"";

        case 'update_collection':
            $name = (string) ($details['name'] ?? $details['title'] ?? 'collection');
            return "Updated collection \"{$name}\"";

        case 'delete_collection':
            return 'Deleted collection ' . (string) ($details['id'] ?? '');

        case 'duplicate_collection':
            return 'Duplicated collection ' . (string) ($details['id'] ?? '');

        case 'publish_entry':
            $collection = (string) ($details['collection'] ?? 'collection');
            return "Published entry in {$collection}";

        case 'scheduled_publish':
            $collection = (string) ($details['collection'] ?? 'collection');
            return "Scheduled entry in {$collection}";

        case 'review_action':
            $action = (string) ($details['action'] ?? 'reviewed');
            $collection = (string) ($details['collection'] ?? 'collection');
            return "{$action} entry in {$collection}";

        case 'create_user':
            return 'Created user ' . (string) ($details['email'] ?? 'user');

        case 'update_user':
            return 'Updated user ' . (string) ($details['email'] ?? $details['id'] ?? 'user');

        case 'delete_user':
            return 'Deleted user ' . (string) ($details['email'] ?? $details['id'] ?? '');

        case 'role_created':
            return 'Created role ' . (string) ($details['role_id'] ?? $details['id'] ?? '');

        case 'role_updated':
            return 'Updated role ' . (string) ($details['role_id'] ?? $details['id'] ?? '');

        case 'role_deleted':
            return 'Deleted role ' . (string) ($details['role_id'] ?? $details['id'] ?? '');

        case 'invitation_created':
            return 'Sent invitation to ' . (string) ($details['email'] ?? 'user');

        case 'invitation_accepted':
            return (string) ($details['email'] ?? 'User') . ' accepted invitation';

        case 'login_success':
            return 'User logged in';

        case 'login_failed':
            return 'Failed login for ' . (string) ($details['email'] ?? 'unknown');

        case 'logout':
            return 'User logged out';

        case 'upload_media':
            return 'Uploaded media: ' . (string) ($details['name'] ?? $details['filename'] ?? 'file');

        case 'project_file_written':
        case 'write_project_file':
            $path = (string) ($details['path'] ?? 'file');
            return 'Wrote project file ' . $path;

        case 'project_file_created':
        case 'create_project_file':
            $path = (string) ($details['path'] ?? 'file');
            return 'Created project file ' . $path;

        case 'project_file_deleted':
        case 'delete_project_file':
            $path = (string) ($details['path'] ?? 'file');
            return 'Deleted project file ' . $path;

        case 'project_settings_updated':
            return 'Updated project settings';

        case 'project_folder_created':
            return 'Created project folder ' . (string) ($details['path'] ?? '');

        case 'project_downloaded':
            return 'Downloaded project as ZIP';

        case 'project_deleted':
            return 'Deleted all project files';

        case 'project_git_connected':
            return 'Connected git remote ' . (string) ($details['remote'] ?? '');

        case 'project_git_disconnected':
            return 'Disconnected git remote';

        case 'project_git_pushed':
            return 'Pushed to ' . (string) ($details['branch'] ?? 'remote');

        case 'project_git_pulled':
            return 'Pulled from ' . (string) ($details['branch'] ?? 'remote');

        case 'project_git_conflict_resolved':
            return 'Resolved git conflict (' . (string) ($details['strategy'] ?? 'unknown') . ')';

        case 'ai_approval_created':
            $actionType = (string) ($details['action_type'] ?? 'action');
            return 'AI requested approval: ' . str_replace('_', ' ', $actionType);

        case 'ai_approval_approved':
            $actionType = (string) ($details['action_type'] ?? 'action');
            return 'AI approved: ' . str_replace('_', ' ', $actionType);

        case 'plugin_activated':
            return 'Activated plugin ' . (string) ($details['plugin'] ?? '');

        case 'plugin_deactivated':
            return 'Deactivated plugin ' . (string) ($details['plugin'] ?? '');

        case 'plugin_deleted':
            return 'Deleted plugin ' . (string) ($details['plugin'] ?? '');

        case 'webhook_created':
            return 'Created webhook';

        case 'webhook_updated':
            return 'Updated webhook';

        case 'webhook_deleted':
            return 'Deleted webhook';

        case 'webhook_triggered':
            return 'Webhook triggered';

        case 'settings_save_all':
            return 'Saved settings';

        case 'settings_update':
            return 'Updated ' . (string) ($details['category'] ?? 'settings');

        case 'ai_tools_blocked':
            return 'AI tool call blocked (' . (string) ($details['mode'] ?? 'restricted') . ')';

        case 'permission_denied':
            return 'Permission denied: ' . (string) ($details['action'] ?? 'action');

        case 'public_read_denied':
            $collection = (string) ($details['collection'] ?? 'collection');
            $reason = (string) ($details['reason'] ?? '');
            $action = (string) ($details['action'] ?? '');
            $page = birkly_activity_referer_page_label((string) ($details['referer'] ?? ''));
            $message = 'Live site blocked from reading "' . $collection . '"';
            if ($reason === 'public_read_disabled') {
                $message .= ' — public read is disabled on this collection';
            } elseif ($reason !== '') {
                $message .= ' (' . str_replace('_', ' ', $reason) . ')';
            }
            if ($action !== '') {
                $message .= ' via ' . str_replace('_', ' ', $action);
            }
            if ($page !== '') {
                $message .= ' while loading ' . $page;
            }
            return $message;

        case 'public_submit_denied':
            $collection = (string) ($details['collection'] ?? 'form');
            $reason = (string) ($details['reason'] ?? '');
            $message = 'Live site blocked from submitting to "' . $collection . '"';
            if ($reason !== '') {
                $message .= ' (' . str_replace('_', ' ', $reason) . ')';
            }
            return $message;

        case 'public_submission':
            $collection = (string) ($details['collection'] ?? 'form');
            return 'Visitor submitted "' . $collection . '" via public form';

        case 'domain_auth_failed':
            $domain = birkly_activity_details_domain($details);
            $reason = (string) ($details['reason'] ?? 'not authorized');
            return 'Domain not authorized: ' . ($domain !== '' ? $domain : 'unknown') . ' (' . str_replace('_', ' ', $reason) . ')';

        case 'domain_permission_denied':
            $domain = birkly_activity_details_domain($details);
            $operation = (string) ($details['operation'] ?? 'access');
            $collection = (string) ($details['collection'] ?? '');
            $message = 'Domain blocked from ' . $operation . ': ' . ($domain !== '' ? $domain : 'unknown');
            if ($collection !== '') {
                $message .= ' on "' . $collection . '"';
            }
            return $message;

        case 'domain_auth_success':
            $domain = birkly_activity_details_domain($details);
            $operation = (string) ($details['operation'] ?? 'read');
            return 'Public API check passed for ' . ($domain !== '' ? $domain : 'domain') . ' (' . $operation . ')';

        case 'api_key_auth_failed':
            return 'API key rejected for public access';

        case 'api_key_auth_success':
            return 'API key accepted for public access';

        default:
            if (birkly_activity_str_starts_with($event, 'commerce_') || birkly_activity_str_starts_with($event, 'automation_')) {
                return ucfirst(str_replace('_', ' ', $event));
            }
            return ucfirst(str_replace('_', ' ', $event));
    }
}

/**
 * @param array<string, mixed> $details
 * @return array<string, mixed>
 */
function birkly_activity_build_target(string $event, array $details): array
{
    $collection = (string) ($details['collection'] ?? $details['dir'] ?? '');
    $entryId = (string) ($details['id'] ?? $details['entry_id'] ?? '');
    $path = (string) ($details['path'] ?? '');
    $plugin = (string) ($details['plugin'] ?? '');
    $email = (string) ($details['email'] ?? '');

    if (in_array($event, ['create_entry', 'update_entry', 'delete_entry', 'publish_entry', 'review_action'], true)
        && $collection !== '') {
        return [
            'type' => 'entry',
            'label' => (string) (($details['title'] ?? '') !== '' ? $details['title'] : ($entryId !== '' ? $entryId : $collection)),
            'collection' => $collection,
            'id' => $entryId,
        ];
    }

    if (in_array($event, ['create_collection', 'update_collection', 'delete_collection', 'duplicate_collection'], true)) {
        return [
            'type' => 'collection',
            'label' => (string) ($details['title'] ?? $details['name'] ?? $entryId),
            'id' => (string) ($details['id'] ?? $details['slug'] ?? ''),
        ];
    }

    if (birkly_activity_str_contains($event, 'project_file') || birkly_activity_str_contains($event, 'project_file')
        || in_array($event, ['write_project_file', 'create_project_file', 'delete_project_file'], true)) {
        return [
            'type' => 'file',
            'label' => $path !== '' ? $path : 'project file',
            'path' => $path,
        ];
    }

    if (birkly_activity_str_starts_with($event, 'project_git_')) {
        return [
            'type' => 'git',
            'label' => (string) ($details['branch'] ?? $details['remote'] ?? 'git'),
        ];
    }

    if (in_array($event, ['project_settings_updated', 'project_downloaded', 'project_deleted', 'project_folder_created'], true)) {
        return ['type' => 'settings', 'label' => 'Project settings'];
    }

    if (birkly_activity_str_starts_with($event, 'plugin_') && $plugin !== '') {
        return ['type' => 'plugin', 'label' => $plugin, 'id' => $plugin];
    }

    if (in_array($event, ['create_user', 'update_user', 'delete_user', 'invitation_created', 'invitation_accepted'], true)) {
        return ['type' => 'user', 'label' => $email !== '' ? $email : (string) ($details['id'] ?? 'user')];
    }

    if (birkly_activity_str_starts_with($event, 'role_')) {
        return [
            'type' => 'role',
            'label' => (string) ($details['role_id'] ?? $details['id'] ?? 'role'),
        ];
    }

    if (in_array($event, ['public_read_denied', 'public_submit_denied', 'public_submission'], true)
        && $collection !== '') {
        return [
            'type' => 'collection',
            'label' => $collection,
            'id' => $collection,
        ];
    }

    $domain = birkly_activity_details_domain($details);
    if (in_array($event, ['domain_auth_failed', 'domain_permission_denied', 'domain_registered', 'domain_updated', 'domain_deleted'], true)
        && $domain !== '') {
        return ['type' => 'domain', 'label' => $domain];
    }

    return ['type' => 'none', 'label' => ''];
}

/**
 * @param array<string, mixed> $details
 * @param array<string, mixed> $target
 */
function birkly_activity_build_href(string $event, array $details, array $target): ?string
{
    $type = (string) ($target['type'] ?? 'none');

    if ($type === 'entry' && ($target['collection'] ?? '') !== '') {
        $params = ['collection' => (string) $target['collection']];
        if (($target['id'] ?? '') !== '') {
            $params['entry'] = (string) $target['id'];
        }
        return birkly_admin_url('content', $params);
    }

    if ($type === 'collection' && ($target['id'] ?? '') !== '') {
        return birkly_admin_url('content', ['collection' => (string) $target['id']]);
    }

    if ($type === 'file' || $type === 'git' || $type === 'settings'
        || birkly_activity_str_starts_with($event, 'project_')
        || in_array($event, ['write_project_file', 'create_project_file', 'delete_project_file'], true)) {
        return birkly_admin_url('settings', ['section' => 'project']);
    }

    if ($type === 'plugin' && ($target['id'] ?? '') !== '') {
        return birkly_admin_url((string) $target['id']);
    }

    if ($type === 'user') {
        return birkly_admin_url('settings', ['section' => 'users']);
    }

    if ($type === 'role') {
        return birkly_admin_url('settings', ['section' => 'roles']);
    }

    if (birkly_activity_str_starts_with($event, 'webhook_')) {
        return birkly_admin_url('webhooks');
    }

    if (in_array($event, ['domain_auth_failed', 'domain_permission_denied', 'domain_registered', 'domain_updated', 'domain_deleted'], true)) {
        return birkly_admin_url('settings', ['tab' => 'site-delivery']) . '#domains';
    }

    return null;
}

/**
 * @param array<string, mixed> $details
 */
function birkly_activity_build_detail_summary(string $event, array $details): string
{
    $domain = birkly_activity_details_domain($details);
    $ip = trim((string) ($details['ip'] ?? ''));
    $referer = trim((string) ($details['referer'] ?? ''));
    $hint = trim((string) ($details['hint'] ?? ''));
    $parts = [];

    if ($event === 'public_read_denied') {
        if ($domain !== '') {
            $parts[] = 'Site: ' . $domain;
        }
        if ($referer !== '') {
            $parts[] = 'Page: ' . $referer;
        }
        if ($ip !== '' && $ip !== 'unknown') {
            $parts[] = 'IP: ' . $ip;
        }
        if ($hint !== '') {
            $parts[] = $hint;
        } elseif (($details['reason'] ?? '') === 'public_read_disabled') {
            $parts[] = 'Fix: open the collection and enable Allow public read access, or remove the template loop from the live site.';
        }
        return implode(' · ', $parts);
    }

    if (in_array($event, ['domain_auth_failed', 'domain_permission_denied'], true)) {
        if ($domain !== '') {
            $parts[] = 'Domain: ' . $domain;
        }
        if (($details['operation'] ?? '') !== '') {
            $parts[] = 'Operation: ' . (string) $details['operation'];
        }
        if ($ip !== '' && $ip !== 'unknown') {
            $parts[] = 'IP: ' . $ip;
        }
        return implode(' · ', $parts);
    }

    if ($event === 'public_submit_denied') {
        if ($domain !== '') {
            $parts[] = 'Site: ' . $domain;
        }
        if ($referer !== '') {
            $parts[] = 'Page: ' . $referer;
        }
        return implode(' · ', $parts);
    }

    return '';
}

/**
 * @param array<string, mixed> $details
 */
function birkly_activity_stable_id(string $event, string $user, string $timestamp, array $details, string $source): string
{
    if (!empty($details['correlation_id'])) {
        return (string) $details['correlation_id'];
    }
    if (!empty($details['id']) && $source === 'plugin_bus') {
        return (string) $details['id'];
    }

    return substr(hash('sha256', $source . '|' . $event . '|' . $timestamp . '|' . $user . '|' . json_encode($details)), 0, 16);
}

/**
 * @param array<string, mixed> $details
 */
function birkly_activity_format_item(
    string $event,
    string $user,
    array $details,
    string $timestamp,
    string $source = 'security_log'
): array {
    $event = trim($event);
    if ($event === '') {
        return [];
    }

    $category = birkly_activity_event_category($event);
    $severity = birkly_activity_event_severity($event);
    $message = birkly_activity_event_message($event, $details);
    $target = birkly_activity_build_target($event, $details);
    $href = birkly_activity_build_href($event, $details, $target);
    $actorType = (($details['source'] ?? '') === 'ai' || !empty($details['ai_user_id'])) ? 'ai' : 'user';
    $publicVisitorEvents = [
        'public_read_denied', 'public_submit_denied', 'public_submission', 'public_submission_spam',
        'domain_auth_failed', 'domain_permission_denied',
    ];
    if (in_array($event, $publicVisitorEvents, true) || ($user === 'guest' && birkly_activity_str_starts_with($event, 'public_'))) {
        $actorType = 'visitor';
    } elseif ($user === 'guest' || $user === 'system') {
        $actorType = 'system';
    }

    $actorLabel = $user !== '' ? $user : 'Unknown';
    if ($actorType === 'visitor') {
        $actorLabel = birkly_activity_visitor_label($details, $user);
    }

    $id = birkly_activity_stable_id($event, $user, $timestamp, $details, $source);
    $detailSummary = birkly_activity_build_detail_summary($event, $details);

    $item = [
        'id' => $id,
        'event' => $event,
        'user' => $user,
        'message' => $message,
        'summary' => $message,
        'detail_summary' => $detailSummary,
        'icon' => birkly_activity_event_icon($event),
        'color' => birkly_activity_event_color($event),
        'timestamp' => $timestamp,
        'details' => $details,
        'detail' => $details,
        'category' => $category,
        'severity' => $severity,
        'actor' => [
            'type' => $actorType,
            'label' => $actorLabel,
        ],
        'target' => $target,
        'href' => $href,
        'source' => $source,
        'correlation_id' => (string) ($details['correlation_id'] ?? ''),
    ];

    if (birkly_activity_str_starts_with($event, 'project_')
        || in_array($event, ['write_project_file', 'create_project_file', 'delete_project_file'], true)) {
        $item['project_group'] = birkly_activity_project_group($event);
    }

    return $item;
}

/**
 * Map P61 plugin bus row to activity v2.
 *
 * @param array<string, mixed> $item
 */
function birkly_activity_format_plugin_item(array $item): array
{
    $plugin = (string) ($item['plugin'] ?? '');
    $event = (string) ($item['event'] ?? 'plugin_event');
    $timestamp = (string) ($item['created_at'] ?? '');
    $title = (string) ($item['title'] ?? '');
    $body = (string) ($item['body'] ?? '');
    $link = trim((string) ($item['link'] ?? ''));
    $meta = is_array($item['meta'] ?? null) ? $item['meta'] : [];
    $details = array_merge($meta, [
        'plugin' => $plugin,
        'title' => $title,
        'body' => $body,
        'id' => (string) ($item['id'] ?? ''),
    ]);

    $formatted = birkly_activity_format_item(
        $plugin !== '' ? $plugin . '_' . $event : $event,
        'plugin',
        $details,
        $timestamp,
        'plugin_bus'
    );

    $formatted['message'] = $title;
    $formatted['summary'] = $body !== '' ? $body : $title;
    $formatted['category'] = 'other';
    if ($link !== '') {
        $formatted['href'] = $link;
    } elseif ($plugin !== '') {
        $formatted['href'] = birkly_admin_url($plugin);
    }
    $formatted['target'] = [
        'type' => 'plugin',
        'label' => $plugin,
        'id' => $plugin,
    ];
    $formatted['id'] = (string) ($item['id'] ?? $formatted['id']);

    return $formatted;
}

/**
 * @return list<array<string, mixed>>
 */
function birkly_activity_read_security_log(int $limit, int $offset = 0, string $filter = 'all', string $severityFilter = 'all'): array
{
    $logFile = birkly_project_root() . '/logs/security.log';
    if (!is_file($logFile)) {
        return [];
    }

    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    $lines = array_reverse($lines);
    $activity = [];
    $processed = 0;

    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if (!is_array($entry)) {
            $parts = explode(' | ', $line);
            if (count($parts) >= 3) {
                $entry = [
                    'timestamp' => $parts[0] ?? '',
                    'event' => $parts[1] ?? '',
                    'details' => json_decode($parts[2] ?? '{}', true) ?: [],
                ];
            } else {
                continue;
            }
        }

        $event = (string) ($entry['event'] ?? '');
        $timestamp = (string) ($entry['timestamp'] ?? '');
        $user = (string) ($entry['user'] ?? 'Unknown');
        $details = is_array($entry['details'] ?? null) ? $entry['details'] : [];

        if (birkly_activity_is_routine_public_noise($event)) {
            continue;
        }

        if ($filter !== '' && $filter !== 'all' && birkly_activity_event_category($event) !== $filter) {
            continue;
        }

        $item = birkly_activity_format_item($event, $user, $details, $timestamp, 'security_log');
        if ($item === []) {
            continue;
        }

        if ($severityFilter !== '' && $severityFilter !== 'all' && ($item['severity'] ?? '') !== $severityFilter) {
            continue;
        }

        if ($processed >= ($offset + $limit)) {
            break;
        }
        if ($processed >= $offset) {
            $activity[] = $item;
        }
        $processed++;
    }

    return $activity;
}

/**
 * @return list<array<string, mixed>>
 */
function birkly_activity_read_plugin_bus(int $limit): array
{
    if (!function_exists('birkly_plugin_activity_list')) {
        $pluginActivityFile = birkly_project_root() . '/core/plugin_activity.php';
        if (is_file($pluginActivityFile)) {
            require_once $pluginActivityFile;
        }
    }

    if (!function_exists('birkly_plugin_activity_list')) {
        return [];
    }

    $items = [];
    foreach (birkly_plugin_activity_list($limit) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $items[] = birkly_activity_format_plugin_item($row);
    }

    return $items;
}

/**
 * @return list<array<string, mixed>>
 */
function birkly_activity_merge_sorted(array $streams, int $limit): array
{
    $merged = [];
    foreach ($streams as $stream) {
        foreach ($stream as $item) {
            if (is_array($item) && ($item['timestamp'] ?? '') !== '') {
                $merged[] = $item;
            }
        }
    }

    usort($merged, static function (array $a, array $b): int {
        return strcmp((string) ($b['timestamp'] ?? ''), (string) ($a['timestamp'] ?? ''));
    });

    $seen = [];
    $out = [];
    foreach ($merged as $item) {
        $key = (string) ($item['id'] ?? '') . '|' . (string) ($item['timestamp'] ?? '');
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $item;
        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}

/** Backward-compatible aliases used by api/activity.php includes. */
function getEventCategory(string $event): string
{
    return birkly_activity_event_category($event);
}

function formatActivityItem(string $event, string $user, array $details, string $timestamp): array
{
    return birkly_activity_format_item($event, $user, $details, $timestamp, 'security_log');
}

function getEventIcon(string $event): string
{
    return birkly_activity_event_icon($event);
}

function getEventMessage(string $event, array $details): string
{
    return birkly_activity_event_message($event, $details);
}

function getEventColor(string $event): string
{
    return birkly_activity_event_color($event);
}
