<?php
/**
 * CMS MCP admin handlers — internal dispatch for core admin APIs (no HTTP/CSRF).
 */

declare(strict_types=1);

require_once __DIR__ . '/cms_mcp_request.php';

function cms_mcp_bootstrap_session(array $aiUser): void
{
    require_once dirname(__DIR__) . '/api/session.php';
    if (session_status() !== PHP_SESSION_ACTIVE) {
        haru_secure_session_start();
    }
    $role = (string) ($aiUser['role'] ?? 'admin');
    $_SESSION['user'] = [
        'id' => (string) ($aiUser['id'] ?? 'mcp'),
        'email' => (string) ($aiUser['email'] ?? 'mcp@birkly.local'),
        'name' => (string) ($aiUser['name'] ?? 'MCP Agent'),
        'role' => $role,
        'capabilities' => is_array($aiUser['capabilities'] ?? null) ? $aiUser['capabilities'] : [],
        'plugin_capabilities' => is_array($aiUser['plugin_capabilities'] ?? null) ? $aiUser['plugin_capabilities'] : [],
    ];
}

function cms_mcp_ensure_admin_entries_loaded(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $GLOBALS['__CMS_MCP_LOAD_FUNCTIONS_ONLY'] = true;
    require_once dirname(__DIR__) . '/api/admin_entries.php';
    unset($GLOBALS['__CMS_MCP_LOAD_FUNCTIONS_ONLY']);
    $loaded = true;
}

function cms_mcp_user_can(array $aiUser, array $permissions): bool
{
    require_once dirname(__DIR__) . '/api/auth.php';
    foreach ($permissions as $permission) {
        if (checkPermission($aiUser, $permission)) {
            return true;
        }
    }
    return false;
}

/** @return array<string, mixed> */
function cms_mcp_ok(array $data = []): array
{
    return ['status' => 'ok', 'data' => $data];
}

/** @return array<string, mixed> */
function cms_mcp_error(string $message): array
{
    return ['status' => 'error', 'message' => $message];
}

/** @return array<string, mixed> */
function cms_mcp_handler_entries(string $subAction, array $input, array $aiUser): array
{
    cms_mcp_bootstrap_session($aiUser);
    cms_mcp_ensure_admin_entries_loaded();

    return cms_mcp_with_json_input($input, static function () use ($subAction): array {
        switch ($subAction) {
            case 'bulk':
                return performBulkOperation();
            case 'review':
                return processReviewAction();
            case 'duplicate':
                return duplicateEntry();
            case 'restore':
                return restoreEntry();
            case 'versions':
            case 'list_versions':
                return getVersionHistory();
            case 'restore_version':
                return restoreVersion();
            case 'diff':
                return getVersionDiff();
            case 'bulk_field_edit':
                return bulkFieldEdit();
            case 'list':
                return listEntries();
            case 'save':
                return saveEntry();
            default:
                return cms_mcp_error('Unknown entries action: ' . $subAction);
        }
    });
}

/** @return array<string, mixed> */
function cms_mcp_handler_content(string $subAction, array $input, array $aiUser): array
{
    cms_mcp_bootstrap_session($aiUser);
    require_once dirname(__DIR__) . '/api/storage.php';
    require_once dirname(__DIR__) . '/core/content_resolve.php';
    require_once dirname(__DIR__) . '/core/entry_storage.php';

    switch ($subAction) {
        case 'duplicate_collection':
            $slug = trim((string) ($input['slug'] ?? ''));
            $newSlug = trim((string) ($input['new_slug'] ?? ''));
            if ($slug === '' || $newSlug === '') {
                return cms_mcp_error('slug and new_slug are required');
            }
            $contentStorage = birkly_ai_content_storage();
            $sourceDir = birkly_content_resolve_collection_dir_name($contentStorage, $slug);
            if ($sourceDir === null) {
                return cms_mcp_error('Collection not found');
            }
            $dest = dirname(__DIR__) . '/content/collections/' . $newSlug;
            if (is_dir($dest)) {
                return cms_mcp_error('Target collection already exists');
            }
            if (!cms_mcp_recursive_copy(dirname(__DIR__) . '/content/collections/' . $sourceDir, $dest)) {
                return cms_mcp_error('Failed to duplicate collection');
            }
            $metaPath = $dest . '/collection.json';
            if (is_file($metaPath)) {
                $meta = json_decode((string) file_get_contents($metaPath), true);
                if (is_array($meta)) {
                    $meta['slug'] = $newSlug;
                    $meta['name'] = (string) ($input['name'] ?? ($meta['name'] ?? $newSlug));
                    file_put_contents($metaPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
            }
            return cms_mcp_ok(['slug' => $newSlug]);

        case 'set_entry_order':
            $collection = trim((string) ($input['collection'] ?? ''));
            $order = is_array($input['order'] ?? null) ? $input['order'] : [];
            if ($collection === '' || $order === []) {
                return cms_mcp_error('collection and order[] are required');
            }
            $settingsPath = dirname(__DIR__) . '/content/collections/' . $collection . '/order.json';
            $dir = dirname($settingsPath);
            if (!is_dir($dir)) {
                return cms_mcp_error('Collection not found');
            }
            file_put_contents($settingsPath, json_encode(array_values($order), JSON_PRETTY_PRINT));
            return cms_mcp_ok(['collection' => $collection, 'count' => count($order)]);

        default:
            return cms_mcp_error('Unknown content action: ' . $subAction);
    }
}

function cms_mcp_recursive_copy(string $src, string $dst): bool
{
    if (!is_dir($src)) {
        return false;
    }
    if (!is_dir($dst) && !mkdir($dst, 0755, true)) {
        return false;
    }
    $items = scandir($src) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $from = $src . '/' . $item;
        $to = $dst . '/' . $item;
        if (is_dir($from)) {
            if (!cms_mcp_recursive_copy($from, $to)) {
                return false;
            }
        } elseif (!copy($from, $to)) {
            return false;
        }
    }
    return true;
}

/** @return array<string, mixed> */
function cms_mcp_handler_sub_entries(string $subAction, array $input, array $aiUser): array
{
    cms_mcp_bootstrap_session($aiUser);
    require_once dirname(__DIR__) . '/core/sub_entries.php';
    require_once dirname(__DIR__) . '/api/storage.php';

    $collection = (string) ($input['collection'] ?? '');
    $entryId = (string) ($input['entry_id'] ?? '');
    $subCollection = (string) ($input['sub_collection'] ?? '');

    switch ($subAction) {
        case 'set_order':
        case 'reorder':
            $order = is_array($input['order'] ?? null) ? $input['order'] : [];
            if ($collection === '' || $entryId === '' || $subCollection === '' || $order === []) {
                return cms_mcp_error('collection, entry_id, sub_collection, and order[] are required');
            }
            $path = dirname(__DIR__) . '/content/collections/' . $collection . '/entries/' . $entryId . '/sub_collections/' . $subCollection . '/.order.json';
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($path, json_encode(array_values($order), JSON_PRETTY_PRINT));
            return cms_mcp_ok(['order' => array_values($order)]);

        case 'bulk':
            $bulkAction = (string) ($input['bulk_action'] ?? $input['action'] ?? '');
            $ids = is_array($input['sub_entry_ids'] ?? null) ? $input['sub_entry_ids'] : [];
            if ($bulkAction === 'delete') {
                $deleted = 0;
                foreach ($ids as $id) {
                    if (deleteSubEntry($collection, $entryId, $subCollection, (string) $id)) {
                        $deleted++;
                    }
                }
                return cms_mcp_ok(['deleted' => $deleted]);
            }
            return cms_mcp_error('Unsupported sub_entries bulk action');

        case 'count':
            return cms_mcp_ok(['count' => countSubEntries($collection, $entryId, $subCollection)]);

        default:
            return cms_mcp_error('Unknown sub_entries action: ' . $subAction);
    }
}

/** @return array<string, mixed> */
function cms_mcp_handler_connections(string $subAction, array $input, array $aiUser): array
{
    require_once __DIR__ . '/connections/registry.php';
    require_once __DIR__ . '/connections/http.php';
    require_once __DIR__ . '/mail/birkly_send_mail.php';
    require_once __DIR__ . '/mail/dns_hints.php';

    switch ($subAction) {
        case 'list':
            return cms_mcp_ok(['connections' => birkly_connections_list(false)]);
        case 'get':
            $id = (string) ($input['id'] ?? $input['alias'] ?? '');
            $conn = birkly_connection_get($id, false) ?? birkly_connection_get_by_alias($id, false);
            if ($conn === null) {
                return cms_mcp_error('Connection not found');
            }
            return cms_mcp_ok(['connection' => birkly_connection_mask_secrets($conn)]);
        case 'providers':
            return cms_mcp_ok(['providers' => ['log', 'smtp', 'resend', 'postmark', 'mailgun', 'ses', 'sendgrid', 'twilio', 'http_credential', 'webhook']]);
        case 'email_picker':
            require_once __DIR__ . '/connections/resolve.php';
            $picker = birkly_email_connections_for_picker();
            return cms_mcp_ok($picker);
        case 'create':
            $result = birkly_connection_create($input);
            return ($result['ok'] ?? false)
                ? cms_mcp_ok(['connection' => birkly_connection_mask_secrets($result['connection'] ?? [])])
                : cms_mcp_error(is_array($result['errors'] ?? null) ? implode('; ', $result['errors']) : 'Create failed');
        case 'update':
            $id = (string) ($input['id'] ?? '');
            $result = birkly_connection_update($id, $input);
            return ($result['ok'] ?? false)
                ? cms_mcp_ok(['connection' => birkly_connection_mask_secrets($result['connection'] ?? [])])
                : cms_mcp_error(is_array($result['errors'] ?? null) ? implode('; ', $result['errors']) : 'Update failed');
        case 'delete':
            $id = (string) ($input['id'] ?? $input['alias'] ?? '');
            $target = birkly_connection_get($id, false) ?? birkly_connection_get_by_alias($id, false);
            if ($target === null) {
                return cms_mcp_error('Connection not found');
            }
            $result = birkly_connection_delete((string) $target['id']);
            return ($result['ok'] ?? false) ? cms_mcp_ok(['deleted' => true]) : cms_mcp_error('Delete failed');
        case 'set_default':
            $id = (string) ($input['id'] ?? '');
            $result = birkly_connection_set_default($id);
            return ($result['ok'] ?? false) ? cms_mcp_ok(['connection' => $result['connection'] ?? []]) : cms_mcp_error('set_default failed');
        case 'dns_hints':
            return cms_mcp_ok(['hints' => birkly_mail_dns_hints((string) ($input['provider'] ?? ''), (string) ($input['domain'] ?? ''))]);
        case 'test':
            require_once __DIR__ . '/connections/mcp_tools.php';
            return birkly_connections_mcp_test(array_merge($input, ['alias' => $input['alias'] ?? $input['id'] ?? '']));
        default:
            return cms_mcp_error('Unknown connections action: ' . $subAction);
    }
}

/** @return array<string, mixed> */
function cms_mcp_handler_agent_guide(string $subAction, array $input, array $aiUser): array
{
    cms_mcp_bootstrap_session($aiUser);
    require_once __DIR__ . '/ai_presence.php';
    require_once __DIR__ . '/ai_presence_pro.php';
    require_once __DIR__ . '/ai_presence_links.php';

    $user = $_SESSION['user'] ?? [];
    if (!birkly_ai_presence_can_view($user)) {
        return cms_mcp_error('Forbidden');
    }
    $writeActions = ['save_draft', 'publish', 'unpublish', 'cms_refresh', 'wizard_apply', 'accept_suggestion', 'accept_suggestions', 'setup_complete', 'setup_skip', 'setup_start'];
    if (in_array($subAction, $writeActions, true) && !birkly_ai_presence_can_edit($user)) {
        return cms_mcp_error('Forbidden — cannot edit Agent Guide');
    }

    switch ($subAction) {
        case 'get':
            $draft = birkly_ai_presence_load_draft();
            return cms_mcp_ok([
                'draft' => $draft,
                'published' => birkly_ai_presence_load_published(),
                'meta' => birkly_ai_presence_load_meta(),
                'publish_state' => birkly_ai_presence_publish_state($draft),
                'completeness' => birkly_ai_presence_completeness($draft),
                'public_urls' => birkly_ai_presence_public_urls(),
                'staleness' => birkly_ai_presence_staleness($draft),
                'cms_diff' => birkly_ai_presence_cms_sync_diff($draft),
            ]);
        case 'save_draft':
            $draft = birkly_ai_presence_merge_draft(is_array($input['draft'] ?? null) ? $input['draft'] : $input);
            if (!birkly_ai_presence_save_draft($draft)) {
                return cms_mcp_error('Failed to save draft');
            }
            return cms_mcp_ok(['draft' => $draft]);
        case 'publish':
            $result = birkly_ai_presence_publish();
            return ($result['ok'] ?? false) ? cms_mcp_ok($result) : cms_mcp_error($result['message'] ?? 'Publish failed');
        case 'unpublish':
            $result = birkly_ai_presence_unpublish();
            return ($result['ok'] ?? false) ? cms_mcp_ok($result) : cms_mcp_error($result['message'] ?? 'Unpublish failed');
        case 'completeness':
            return cms_mcp_ok(birkly_ai_presence_completeness());
        case 'staleness':
            return cms_mcp_ok(birkly_ai_presence_staleness());
        case 'cms_diff':
            return cms_mcp_ok(birkly_ai_presence_cms_sync_diff());
        case 'cms_refresh':
            $draft = birkly_ai_presence_load_draft();
            $refIndex = (int) ($input['ref_index'] ?? -1);
            $field = isset($input['field']) ? (string) $input['field'] : null;
            $result = birkly_ai_presence_cms_sync_apply($draft, $refIndex, $field);
            if (!($result['ok'] ?? false)) {
                return cms_mcp_error($result['message'] ?? 'cms_refresh failed');
            }
            birkly_ai_presence_save_draft($result['draft'] ?? $draft);
            return cms_mcp_ok($result);
        case 'list_collections':
            return cms_mcp_ok(['collections' => birkly_ai_presence_list_collections()]);
        case 'list_collection_entries':
            $slug = (string) ($input['collection'] ?? '');
            return cms_mcp_ok(['entries' => birkly_ai_presence_list_collection_entries($slug)]);
        case 'list_versions':
            return cms_mcp_ok(['versions' => birkly_ai_presence_list_versions()]);
        case 'preview_query':
            return cms_mcp_ok(birkly_ai_presence_preview_query_pro((string) ($input['question'] ?? ''), (string) ($input['connection_id'] ?? '')));
        case 'wizard_generate':
            $slugs = is_array($input['collections'] ?? null) ? $input['collections'] : [];
            return cms_mcp_ok(birkly_ai_presence_wizard_generate($slugs, (string) ($input['connection_id'] ?? ''), !empty($input['creative'])));
        case 'wizard_apply':
            $draft = birkly_ai_presence_load_draft();
            $generated = is_array($input['generated'] ?? null) ? $input['generated'] : [];
            $merged = $generated !== [] ? birkly_ai_presence_merge_draft($generated) : $draft;
            birkly_ai_presence_save_draft($merged);
            return cms_mcp_ok(['draft' => $merged]);
        case 'copilot':
        case 'suggest':
            $draft = birkly_ai_presence_load_draft();
            return cms_mcp_ok(birkly_ai_presence_copilot_suggestions($draft, (string) ($input['connection_id'] ?? ''), (string) ($input['mode'] ?? 'all'), !empty($input['creative'])));
        case 'accept_suggestion':
            $draft = birkly_ai_presence_load_draft();
            $suggestion = is_array($input['suggestion'] ?? null) ? $input['suggestion'] : [];
            $draft = birkly_ai_presence_apply_suggestion_pro($draft, $suggestion);
            birkly_ai_presence_save_draft($draft);
            return cms_mcp_ok(['draft' => $draft]);
        case 'validate_links':
            $draft = birkly_ai_presence_load_draft();
            $issues = birkly_ai_presence_validate_draft_links($draft, true);
            birkly_ai_presence_save_draft($draft);
            return cms_mcp_ok(['issues' => $issues, 'draft' => $draft]);
        case 'fetch_url_meta':
            return cms_mcp_ok(birkly_ai_presence_fetch_url_meta((string) ($input['url'] ?? '')));
        case 'public_urls':
            return cms_mcp_ok(birkly_ai_presence_public_urls());
        case 'setup_prefill':
            return cms_mcp_ok(birkly_ai_presence_setup_prefill());
        case 'discovery_status':
            require_once __DIR__ . '/ai_presence_discovery.php';
            return cms_mcp_ok(birkly_ai_presence_discovery_status());
        default:
            return cms_mcp_error('Unknown agent_guide action: ' . $subAction);
    }
}

/** @return array<string, mixed> */
function cms_mcp_handler_mcp_endpoints(string $subAction, array $input, array $aiUser): array
{
    require_once __DIR__ . '/mcp_endpoints.php';

    switch ($subAction) {
        case 'list':
            return cms_mcp_ok([
                'endpoints' => birkly_mcp_list_endpoints(),
                'server_url' => function_exists('birkly_ai_mcp_server_url') ? birkly_ai_mcp_server_url() : '',
            ]);
        case 'get':
            $id = (string) ($input['id'] ?? '');
            $endpoint = birkly_mcp_find_endpoint($id);
            return $endpoint ? cms_mcp_ok(['endpoint' => birkly_mcp_scrub_endpoint($endpoint)]) : cms_mcp_error('Endpoint not found');
        case 'create':
            $created = birkly_mcp_create_endpoint($input, (string) ($aiUser['id'] ?? 'mcp'));
            return cms_mcp_ok(['endpoint' => $created]);
        case 'update':
            $id = (string) ($input['id'] ?? '');
            $updated = birkly_mcp_update_endpoint($id, $input);
            return $updated ? cms_mcp_ok(['endpoint' => birkly_mcp_scrub_endpoint($updated)]) : cms_mcp_error('Update failed');
        case 'rotate':
            $id = (string) ($input['id'] ?? '');
            $rotated = birkly_mcp_rotate_endpoint_key($id);
            return $rotated ? cms_mcp_ok(['endpoint' => $rotated]) : cms_mcp_error('Rotate failed');
        case 'revoke':
            $id = (string) ($input['id'] ?? '');
            return birkly_mcp_revoke_endpoint($id) ? cms_mcp_ok(['revoked' => true]) : cms_mcp_error('Revoke failed');
        default:
            return cms_mcp_error('Unknown mcp_endpoints action: ' . $subAction);
    }
}

/** @return array<string, mixed> */
function cms_mcp_handler_ai_agents(string $subAction, array $input, array $aiUser): array
{
    require_once __DIR__ . '/ai_agents.php';

    switch ($subAction) {
        case 'list':
            return cms_mcp_ok(['agents' => birkly_ai_agents_list_for_user($aiUser)]);
        case 'create':
            return cms_mcp_ok(birkly_ai_agents_create($input, (string) ($aiUser['id'] ?? 'mcp')));
        case 'update':
            $id = (string) ($input['id'] ?? '');
            $updated = birkly_ai_agents_update($id, $input, $aiUser);
            return $updated ? cms_mcp_ok(['agent' => $updated]) : cms_mcp_error('Update failed');
        case 'revoke':
            $id = (string) ($input['id'] ?? '');
            return birkly_ai_agents_revoke($id, $aiUser) ? cms_mcp_ok(['revoked' => true]) : cms_mcp_error('Revoke failed');
        case 'roles':
            require_once __DIR__ . '/roles.php';
            $rolesFile = birkly_roles_file_path();
            $roles = is_file($rolesFile) ? (json_decode((string) file_get_contents($rolesFile), true) ?: []) : [];
            return cms_mcp_ok(['roles' => $roles]);
        default:
            return cms_mcp_error('Unknown ai_agents action: ' . $subAction);
    }
}

/** @return array<string, mixed> */
function cms_mcp_handler_users(string $subAction, array $input, array $aiUser): array
{
    cms_mcp_bootstrap_session($aiUser);
    require_once dirname(__DIR__) . '/core/birkly_users.php';
    require_once dirname(__DIR__) . '/core/security_policy.php';
    require_once dirname(__DIR__) . '/api/validate.php';

    switch ($subAction) {
        case 'create':
            $email = trim((string) ($input['email'] ?? ''));
            $password = (string) ($input['password'] ?? '');
            $role = (string) ($input['role'] ?? 'viewer');
            $name = trim((string) ($input['name'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return cms_mcp_error('Valid email required');
            }
            if ($password === '') {
                return cms_mcp_error('password required');
            }
            $strength = validate_password_strength($password);
            if (!($strength['valid'] ?? false)) {
                return cms_mcp_error($strength['message'] ?? 'Weak password');
            }
            $userId = md5($email);
            if (!birkly_is_human_user_id($userId)) {
                return cms_mcp_error('Invalid email for user id');
            }
            $storage = new Storage(birkly_users_write_dir());
            if ($storage->read($userId . '.json')) {
                return cms_mcp_error('User already exists');
            }
            $record = [
                'id' => $userId,
                'email' => $email,
                'name' => $name !== '' ? $name : explode('@', $email)[0],
                'role' => $role,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'created_at' => date('c'),
                'created_by' => (string) ($aiUser['id'] ?? 'mcp'),
            ];
            $record = birkly_save_user_with_2fa_policy($record);
            if (!$storage->write($userId . '.json', $record)) {
                return cms_mcp_error('Failed to create user');
            }
            unset($record['password']);
            return cms_mcp_ok(['user' => $record]);

        case 'revoke_mcp_sessions':
            $userId = (string) ($input['user_id'] ?? $input['id'] ?? '');
            require_once __DIR__ . '/mcp_oauth.php';
            $count = birkly_mcp_oauth_revoke_user_sessions($userId);
            return cms_mcp_ok(['revoked' => $count]);

        case 'list':
            return cms_mcp_ok(['users' => birkly_list_human_user_records(true)]);

        case 'update':
            $userId = (string) ($input['id'] ?? '');
            $updates = is_array($input['updates'] ?? null) ? $input['updates'] : $input;
            unset($updates['id'], $updates['action']);
            if ($userId === '' || $updates === [] || !birkly_is_human_user_id($userId)) {
                return cms_mcp_error('User id and updates required');
            }
            $storage = new Storage(birkly_users_write_dir());
            $user = $storage->read($userId . '.json');
            if (!is_array($user)) {
                return cms_mcp_error('User not found');
            }
            foreach (['role', 'email', '2fa_grace_until'] as $field) {
                if (array_key_exists($field, $updates)) {
                    $user[$field] = $updates[$field];
                }
            }
            if (!empty($updates['password'])) {
                $strength = validate_password_strength((string) $updates['password']);
                if (!($strength['valid'] ?? false)) {
                    return cms_mcp_error($strength['message'] ?? 'Weak password');
                }
                $user['password'] = password_hash((string) $updates['password'], PASSWORD_DEFAULT);
            }
            $user = birkly_save_user_with_2fa_policy($user);
            $user['updated_at'] = date('c');
            $user['updated_by'] = (string) ($aiUser['id'] ?? 'mcp');
            if (!$storage->write($userId . '.json', $user)) {
                return cms_mcp_error('Failed to update user');
            }
            unset($user['password'], $user['password_hash']);
            return cms_mcp_ok(['user' => $user]);

        case 'delete':
            $userId = (string) ($input['id'] ?? '');
            if ($userId === '' || !birkly_is_human_user_id($userId)) {
                return cms_mcp_error('User id required');
            }
            if ($userId === (string) ($aiUser['id'] ?? '')) {
                return cms_mcp_error('Cannot delete your own account');
            }
            $storage = new Storage(birkly_users_write_dir());
            if (!$storage->delete($userId . '.json')) {
                return cms_mcp_error('Failed to delete user');
            }
            return cms_mcp_ok(['deleted' => true]);

        default:
            return cms_mcp_error('Unknown users action: ' . $subAction);
    }
}

/** @return array<string, mixed> */
function cms_mcp_handler_roles(string $subAction, array $input, array $aiUser): array
{
    cms_mcp_bootstrap_session($aiUser);
    require_once dirname(__DIR__) . '/core/roles.php';
    require_once dirname(__DIR__) . '/api/storage.php';

    $storage = new Storage(dirname(__DIR__) . '/settings');
    $rolesFile = 'roles.json';

    switch ($subAction) {
        case 'list':
            return cms_mcp_ok(['roles' => birkly_ensure_default_roles()]);
        case 'list_plugins':
            if (!function_exists('get_all_plugins')) {
                require_once dirname(__DIR__) . '/core/plugins.php';
            }
            $plugins = [];
            foreach (get_all_plugins() as $id => $plugin) {
                if (empty($plugin['active'])) {
                    continue;
                }
                $plugins[] = [
                    'id' => (string) $id,
                    'name' => (string) ($plugin['name'] ?? $id),
                ];
            }
            return cms_mcp_ok(['plugins' => $plugins]);
        case 'get':
            $id = (string) ($input['id'] ?? '');
            foreach (birkly_ensure_default_roles() as $role) {
                if (($role['id'] ?? '') === $id) {
                    return cms_mcp_ok(['role' => $role]);
                }
            }
            return cms_mcp_error('Role not found');
        case 'create':
            $role = is_array($input['role'] ?? null) ? $input['role'] : $input;
            if (empty($role['id'])) {
                return cms_mcp_error('role.id required');
            }
            $roles = $storage->read($rolesFile);
            if (!is_array($roles) || $roles === []) {
                $roles = birkly_ensure_default_roles();
            }
            $roles[] = $role;
            $storage->write($rolesFile, $roles);
            return cms_mcp_ok(['role' => $role]);
        case 'update':
            $role = is_array($input['role'] ?? null) ? $input['role'] : $input;
            $roleId = (string) ($role['id'] ?? '');
            if ($roleId === '') {
                return cms_mcp_error('role.id required');
            }
            $roles = $storage->read($rolesFile);
            if (!is_array($roles) || $roles === []) {
                $roles = birkly_ensure_default_roles();
            }
            $found = false;
            foreach ($roles as &$existing) {
                if (($existing['id'] ?? '') === $roleId) {
                    $existing = $role;
                    $found = true;
                    break;
                }
            }
            unset($existing);
            if (!$found) {
                return cms_mcp_error('Role not found');
            }
            $storage->write($rolesFile, $roles);
            return cms_mcp_ok(['role' => $role]);
        case 'delete':
            $roleId = (string) ($input['id'] ?? '');
            if ($roleId === '') {
                return cms_mcp_error('id required');
            }
            $roles = array_values(array_filter(
                $storage->read($rolesFile) ?: [],
                static fn (array $role): bool => ($role['id'] ?? '') !== $roleId
            ));
            $storage->write($rolesFile, $roles);
            return cms_mcp_ok(['deleted' => true]);
        default:
            return cms_mcp_error('Unknown roles action: ' . $subAction);
    }
}

/** @return array<string, mixed> */
function cms_mcp_handler_marketplace(string $subAction, array $input, array $aiUser): array
{
    require_once __DIR__ . '/marketplace.php';
    require_once __DIR__ . '/marketplace/install.php';
    require_once __DIR__ . '/marketplace/consent.php';
    require_once __DIR__ . '/marketplace/hub_licenses.php';

    switch ($subAction) {
        case 'browse':
            return cms_mcp_ok(birkly_marketplace_browse(
                (string) ($input['query'] ?? ''),
                (string) ($input['category'] ?? ''),
                (string) ($input['offer_type'] ?? ''),
                (string) ($input['sort'] ?? 'name'),
                (string) ($input['price'] ?? '')
            ));
        case 'offer':
            $slug = (string) ($input['slug'] ?? $input['offer_slug'] ?? '');
            return cms_mcp_ok(birkly_marketplace_offer_detail($slug));
        case 'install':
            $slug = (string) ($input['slug'] ?? $input['offer_slug'] ?? '');
            $result = birkly_marketplace_install_offer(
                $slug,
                (string) ($input['version'] ?? ''),
                (string) ($input['license_key'] ?? ''),
                (string) ($input['offer_type'] ?? 'plugin'),
                !empty($input['upgrade']),
                !empty($input['consent_accepted']),
                $input
            );
            return ($result['ok'] ?? false) ? cms_mcp_ok($result) : cms_mcp_error($result['message'] ?? $result['error'] ?? 'Install failed');
        case 'list_hub_licenses':
            return cms_mcp_ok(['licenses' => birkly_marketplace_pending_hub_licenses()]);
        case 'activate_license':
            $key = (string) ($input['license_key'] ?? $input['key'] ?? '');
            $result = birkly_marketplace_activate_license($key, $input);
            return ($result['ok'] ?? false) ? cms_mcp_ok($result) : cms_mcp_error($result['message'] ?? 'Activation failed');
        case 'deactivate_license':
            $key = (string) ($input['license_key'] ?? $input['key'] ?? '');
            $result = birkly_marketplace_deactivate_license($key);
            return ($result['ok'] ?? false) ? cms_mcp_ok($result) : cms_mcp_error($result['message'] ?? 'Deactivation failed');
        case 'check_updates':
            $slug = (string) ($input['slug'] ?? $input['plugin_id'] ?? '');
            $plugins = function_exists('get_all_plugins') ? get_all_plugins() : [];
            $current = (string) ($plugins[$slug]['version'] ?? '0.0.0');
            return cms_mcp_ok(birkly_marketplace_check_plugin_update($slug, $current));
        case 'upgrade':
            if (!function_exists('get_all_plugins')) {
                require_once dirname(__DIR__) . '/core/plugins.php';
            }
            require_once __DIR__ . '/marketplace/consent.php';
            $pluginId = trim((string) ($input['plugin_id'] ?? $input['id'] ?? ''));
            if ($pluginId === '') {
                return cms_mcp_error('plugin_id required');
            }
            $plugins = get_all_plugins();
            if (!isset($plugins[$pluginId])) {
                return cms_mcp_error('Plugin not found');
            }
            $plugin = $plugins[$pluginId];
            $slug = (string) ($plugin['marketplace_slug'] ?? '');
            if ($slug === '') {
                return cms_mcp_error('Plugin was not installed from marketplace');
            }
            $current = (string) ($plugin['version'] ?? '0.0.0');
            $licenseRef = trim((string) ($plugin['license_ref'] ?? ''));
            $check = birkly_marketplace_check_plugin_update($slug, $current, $licenseRef !== '' ? $licenseRef : null);
            if (!empty($check['license_lapsed'])) {
                return cms_mcp_error($check['license_warning'] ?? 'Marketplace license is inactive');
            }
            if (empty($check['update_available'])) {
                return cms_mcp_error('No update available');
            }
            $targetVersion = (string) ($check['latest_version'] ?? $current);
            $consentAccepted = !empty($input['consent_accepted']) || !empty($input['consentAccepted']);
            $consentCheck = birkly_marketplace_require_install_consent($slug, $targetVersion, $consentAccepted);
            if (!$consentCheck['ok']) {
                return cms_mcp_error($consentCheck['error'] ?? 'Permission consent required');
            }
            $wasActive = !empty($plugin['active']);
            if ($wasActive) {
                set_plugin_state($pluginId, false);
            }
            $install = birkly_marketplace_install_plugin($slug, $targetVersion, '', true, $consentAccepted);
            if (!$install['ok']) {
                if ($wasActive) {
                    set_plugin_state($pluginId, true);
                }
                return cms_mcp_error('Update failed: ' . implode('; ', $install['errors'] ?? []));
            }
            if ($wasActive) {
                set_plugin_state($pluginId, true);
            }
            return cms_mcp_ok($install);
        case 'list_installed_template_packs':
            require_once __DIR__ . '/marketplace/template_pack.php';
            return cms_mcp_ok(['packs' => birkly_marketplace_get_installed_template_packs()]);
        case 'preflight_template_pack':
            require_once __DIR__ . '/marketplace/template_pack.php';
            $slug = trim((string) ($input['slug'] ?? ''));
            if ($slug === '') {
                return cms_mcp_error('slug required');
            }
            $resolved = birkly_marketplace_resolve_template_pack_zip_for_slug($slug, $input);
            if (!($resolved['ok'] ?? false) || empty($resolved['path'])) {
                return cms_mcp_error($resolved['error'] ?? 'Could not resolve template pack');
            }
            $preflight = birkly_marketplace_preflight_template_pack((string) $resolved['path'], $input);
            return (!empty($preflight['ok']) || !empty($preflight['requires_plugin_confirmation']))
                ? cms_mcp_ok($preflight)
                : cms_mcp_error(implode('; ', $preflight['errors'] ?? ['Preflight failed']));
        case 'template_pack_status':
            require_once __DIR__ . '/marketplace/template_pack.php';
            $slug = trim((string) ($input['slug'] ?? ''));
            if ($slug === '') {
                return cms_mcp_error('slug required');
            }
            return cms_mcp_ok(birkly_marketplace_template_pack_update_status($slug));
        default:
            return cms_mcp_error('Unknown marketplace action: ' . $subAction);
    }
}

/** @return array<string, mixed> */
function cms_mcp_handler_project_git(string $subAction, array $input, array $aiUser): array
{
    require_once __DIR__ . '/project_git.php';
    require_once __DIR__ . '/project_git_settings.php';

    $git = birkly_project_git_engine();

    switch ($subAction) {
        case 'capability':
            return cms_mcp_ok(['capability' => $git->capability()]);
        case 'status':
            return cms_mcp_ok($git->status());
        case 'connect':
            $remote = (string) ($input['remote_url'] ?? $input['remote'] ?? '');
            $branch = (string) ($input['branch'] ?? 'main');
            $git->initialize();
            $git->setRemote($remote);
            return cms_mcp_ok(['connected' => true, 'remote' => $remote, 'branch' => $branch]);
        case 'test':
            return cms_mcp_ok($git->testConnection());
        case 'disconnect':
            birkly_project_git_write(['remote_url' => '', 'branch' => 'main', 'connected' => false]);
            return cms_mcp_ok(['disconnected' => true]);
        case 'push':
            return cms_mcp_ok($git->push((string) ($input['message'] ?? 'Update from MCP')));
        case 'pull_preview':
            return cms_mcp_ok($git->pullPreview());
        case 'pull_apply':
            return cms_mcp_ok($git->pullApply());
        case 'resolve_conflict':
            $strategy = (string) ($input['strategy'] ?? 'ours');
            return cms_mcp_ok($git->resolveConflict($strategy, is_array($input['files'] ?? null) ? $input['files'] : []));
        default:
            return cms_mcp_error('Unknown project_git action: ' . $subAction);
    }
}

/** @return array<string, mixed> */
function cms_mcp_handler_project_site(string $subAction, array $input, array $aiUser): array
{
    require_once __DIR__ . '/project_site_filesystem.php';
    require_once __DIR__ . '/project_settings.php';
    require_once __DIR__ . '/project_ssr.php';

    $fs = new ProjectSiteFileSystem();

    switch ($subAction) {
        case 'overview':
            return cms_mcp_ok(birkly_project_site_info());
        case 'save_settings':
            $settings = is_array($input['settings'] ?? null) ? $input['settings'] : $input;
            birkly_save_project_settings($settings);
            return cms_mcp_ok(['saved' => true]);
        case 'invalidate_ssr_cache':
            birkly_project_ssr_invalidate_all();
            return cms_mcp_ok(['invalidated' => true]);
        case 'create_folder':
            $path = (string) ($input['path'] ?? '');
            $fs->createDirectory($path);
            return cms_mcp_ok(['path' => $path]);
        case 'list':
            return cms_mcp_ok($fs->listFiles((string) ($input['path'] ?? '')));
        case 'read':
            return cms_mcp_ok($fs->readFile((string) ($input['path'] ?? '')));
        default:
            return cms_mcp_error('Unknown project_site action: ' . $subAction);
    }
}

/** @return array<string, mixed> */
function cms_mcp_handler_webhooks(string $subAction, array $input, array $aiUser): array
{
    $GLOBALS['__HARU_INTERNAL_CALL'] = true;
    require_once dirname(__DIR__) . '/api/webhooks.php';
    unset($GLOBALS['__HARU_INTERNAL_CALL']);

    switch ($subAction) {
        case 'list':
            return cms_mcp_ok(listWebhooks());
        case 'get':
            return cms_mcp_ok(getWebhook((string) ($input['id'] ?? '')));
        case 'create':
            return createWebhook($input);
        case 'update':
            return updateWebhook($input);
        case 'delete':
            return deleteWebhook($input);
        case 'test':
            return testWebhook($input);
        default:
            return cms_mcp_error('Unknown webhooks action: ' . $subAction);
    }
}

/** @return array<string, mixed> */
function cms_mcp_handler_redirects(string $subAction, array $input, array $aiUser): array
{
    require_once dirname(__DIR__) . '/api/redirects.php';

    switch ($subAction) {
        case 'list':
            return cms_mcp_ok(['redirects' => birkly_load_redirects()]);
        case 'save':
            $redirects = birkly_load_redirects();
            $record = birkly_normalize_redirect_record($input);
            $found = false;
            foreach ($redirects as $i => $row) {
                if (($row['id'] ?? '') === $record['id']) {
                    $redirects[$i] = $record;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $redirects[] = $record;
            }
            return birkly_save_redirects($redirects) ? cms_mcp_ok(['redirect' => $record]) : cms_mcp_error('Save failed');
        case 'delete':
            $id = (string) ($input['id'] ?? '');
            $redirects = array_values(array_filter(birkly_load_redirects(), static fn ($r) => ($r['id'] ?? '') !== $id));
            return birkly_save_redirects($redirects) ? cms_mcp_ok(['deleted' => true]) : cms_mcp_error('Delete failed');
        default:
            return cms_mcp_error('Unknown redirects action: ' . $subAction);
    }
}

/** @return array<string, mixed> */
function cms_mcp_handler_plugins(string $subAction, array $input, array $aiUser): array
{
    require_once __DIR__ . '/plugins.php';
    require_once __DIR__ . '/marketplace.php';

    switch ($subAction) {
        case 'get_settings':
            $pluginId = (string) ($input['plugin_id'] ?? $input['id'] ?? '');
            $plugins = get_all_plugins();
            if (!isset($plugins[$pluginId])) {
                return cms_mcp_error('Plugin not found');
            }
            return cms_mcp_ok(['settings' => $plugins[$pluginId]['settings'] ?? [], 'schema' => $plugins[$pluginId]['settings_schema'] ?? []]);
        case 'save_settings':
            $pluginId = (string) ($input['plugin_id'] ?? $input['id'] ?? '');
            $settings = is_array($input['settings'] ?? null) ? $input['settings'] : $input;
            foreach ($settings as $key => $value) {
                if ($key === 'plugin_id' || $key === 'id') {
                    continue;
                }
                set_plugin_setting($pluginId, (string) $key, $value);
            }
            return cms_mcp_ok(['saved' => true]);
        case 'health':
            return cms_mcp_ok(['plugins' => get_all_plugins()]);
        case 'check_updates':
            $pluginId = (string) ($input['plugin_id'] ?? '');
            return cms_mcp_ok(birkly_marketplace_check_plugin_update($pluginId));
        case 'update':
            $pluginId = (string) ($input['plugin_id'] ?? '');
            $plugins = get_all_plugins();
            $current = (string) ($plugins[$pluginId]['version'] ?? '0.0.0');
            $check = birkly_marketplace_check_plugin_update($pluginId, $current);
            if (!($check['update_available'] ?? false)) {
                return cms_mcp_error('No update available');
            }
            $result = birkly_marketplace_install_plugin(
                $pluginId,
                (string) ($check['latest_version'] ?? ''),
                (string) ($input['license_key'] ?? ''),
                true
            );
            return ($result['ok'] ?? false) ? cms_mcp_ok($result) : cms_mcp_error($result['message'] ?? 'Update failed');
        case 'delete':
            $pluginId = (string) ($input['plugin_id'] ?? '');
            set_plugin_state($pluginId, false);
            return cms_mcp_ok(['deactivated' => true, 'note' => 'Plugin deactivated; filesystem cleanup may require manual step']);
        default:
            return cms_mcp_error('Unknown plugins action: ' . $subAction);
    }
}

/** @return array<string, mixed> */
function cms_mcp_handler_plugins_permissions(string $subAction, array $input, array $aiUser): array
{
    require_once __DIR__ . '/plugins/permissions.php';

    $pluginId = (string) ($input['plugin_id'] ?? '');

    switch ($subAction) {
        case 'get':
            return cms_mcp_ok(get_plugin_permissions($pluginId));
        case 'grant_capability':
            grant_plugin_capability($pluginId, (string) ($input['capability'] ?? ''));
            return cms_mcp_ok(['granted' => true]);
        case 'revoke_capability':
            revoke_plugin_capability($pluginId, (string) ($input['capability'] ?? ''));
            return cms_mcp_ok(['revoked' => true]);
        case 'get_denylist':
            return cms_mcp_ok(['denylist' => get_plugin_denylist($pluginId)]);
        case 'set_denylist':
            set_plugin_denylist($pluginId, is_array($input['denylist'] ?? null) ? $input['denylist'] : []);
            return cms_mcp_ok(['saved' => true]);
        default:
            return cms_mcp_error('Unknown plugins_permissions action: ' . $subAction);
    }
}

/** @return array<string, mixed> */
function cms_mcp_handler_search(string $subAction, array $input, array $aiUser): array
{
    cms_mcp_bootstrap_session($aiUser);

    if ($subAction !== 'admin_search') {
        return cms_mcp_error('Unknown search action');
    }

    $GLOBALS['__HARU_INTERNAL_CALL'] = true;
    require_once dirname(__DIR__) . '/api/search.php';
    unset($GLOBALS['__HARU_INTERNAL_CALL']);

    $query = trim((string) ($input['query'] ?? $input['q'] ?? ''));
    $limit = max(1, (int) ($input['limit'] ?? 50));
    if ($query === '') {
        return cms_mcp_ok([
            'collections' => [],
            'entries' => [],
            'media' => [],
            'users' => [],
        ]);
    }

    require_once dirname(__DIR__) . '/api/../core/storage/StorageFactory.php';
    $storage = StorageFactory::getStorage(dirname(__DIR__) . '/content');
    if ($storage === null) {
        return cms_mcp_error('Storage initialization failed');
    }

    return cms_mcp_ok([
        'collections' => searchCollections($storage, $query, $limit),
        'entries' => searchEntries($storage, $query, $limit),
        'media' => searchMedia($query, $limit),
        'users' => searchUsers($query, $limit),
    ]);
}

/** @return array<string, mixed> */
function cms_mcp_handler_analytics(string $subAction, array $input, array $aiUser): array
{
    require_once __DIR__ . '/analytics_presets.php';
    require_once __DIR__ . '/analytics_config.php';

    switch ($subAction) {
        case 'get_settings':
            return cms_mcp_ok(birkly_analytics_load_settings());
        case 'save_settings':
            $settings = is_array($input['settings'] ?? null) ? $input['settings'] : $input;
            birkly_analytics_save_settings($settings);
            return cms_mcp_ok(['saved' => true]);
        case 'presets':
            return cms_mcp_ok(['presets' => birkly_analytics_preset_definitions(), 'active' => birkly_analytics_active_preset()]);
        case 'data':
            $GLOBALS['__HARU_INTERNAL_CALL'] = true;
            require_once dirname(__DIR__) . '/api/analytics.php';
            unset($GLOBALS['__HARU_INTERNAL_CALL']);
            if (!function_exists('getAnalyticsData')) {
                return cms_mcp_error('Analytics data unavailable');
            }
            $trackingId = (string) ($input['tracking_id'] ?? 'all');
            $startDate = (string) ($input['start_date'] ?? date('Y-m-d', strtotime('-30 days')));
            $endDate = (string) ($input['end_date'] ?? date('Y-m-d'));
            return cms_mcp_ok(getAnalyticsData($trackingId, $startDate, $endDate));
        default:
            return cms_mcp_error('Unknown analytics action: ' . $subAction);
    }
}

/** @return array<string, mixed> */
function cms_mcp_handler_webmcp(string $subAction, array $input, array $aiUser): array
{
    $path = dirname(__DIR__) . '/settings/webmcp.json';
    switch ($subAction) {
        case 'get':
            if (!is_file($path)) {
                return cms_mcp_ok(['config' => []]);
            }
            $data = json_decode((string) file_get_contents($path), true);
            return cms_mcp_ok(['config' => is_array($data) ? $data : []]);
        case 'save':
            $config = is_array($input['config'] ?? null) ? $input['config'] : $input;
            file_put_contents($path, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return cms_mcp_ok(['saved' => true]);
        default:
            return cms_mcp_error('Unknown webmcp action: ' . $subAction);
    }
}

/** @return array<string, mixed> */
function cms_mcp_handler_settings(string $subAction, array $input, array $aiUser): array
{
    cms_mcp_bootstrap_session($aiUser);
    require_once dirname(__DIR__) . '/api/storage.php';
    $settingsStorage = new Storage(dirname(__DIR__) . '/settings');

    $readCategory = static function (string $category) use ($settingsStorage): array {
        $data = $settingsStorage->read($category . '.json') ?? [];
        if ($category === 'security' && is_array($data)) {
            unset($data['db_password']);
        }
        return is_array($data) ? $data : [];
    };

    switch ($subAction) {
        case 'get':
            $category = (string) ($input['category'] ?? 'all');
            $allowed = ['site', 'admin', 'media', 'security', 'branding', 'monitoring', 'all'];
            if (!in_array($category, $allowed, true)) {
                return cms_mcp_error('Invalid category');
            }
            if ($category === 'all') {
                $settings = [];
                foreach (['site', 'admin', 'media', 'security', 'branding', 'monitoring'] as $cat) {
                    $settings[$cat] = $readCategory($cat);
                }
                return cms_mcp_ok(['settings' => $settings]);
            }
            return cms_mcp_ok(['category' => $category, 'settings' => $readCategory($category)]);
        case 'save':
            $category = (string) ($input['category'] ?? '');
            $settings = is_array($input['settings'] ?? null) ? $input['settings'] : [];
            $blocked = ['reset', 'db_password'];
            foreach ($blocked as $key) {
                unset($settings[$key]);
            }
            if ($category === '' || $settings === []) {
                return cms_mcp_error('category and settings required');
            }
            $allowedCats = ['site', 'admin', 'media', 'security', 'branding', 'monitoring'];
            if (!in_array($category, $allowedCats, true)) {
                return cms_mcp_error('Invalid category');
            }
            $existing = $settingsStorage->read("$category.json") ?? [];
            if (!is_array($existing)) {
                $existing = [];
            }
            $updated = array_merge($existing, $settings);
            $updated['_updated_at'] = date('c');
            $updated['_updated_by'] = (string) ($aiUser['id'] ?? 'mcp');
            if (!$settingsStorage->write("$category.json", $updated)) {
                return cms_mcp_error('Failed to save settings');
            }
            return cms_mcp_ok(['category' => $category, 'settings' => $updated]);
        case 'rebuild_system':
            require_once dirname(__DIR__) . '/core/page_bundle_helpers.php';
            birkly_rebuild_system_cache();
            return cms_mcp_ok(['rebuilt' => true]);
        case 'migrate_storage':
            return cms_mcp_error('migrate_storage requires admin UI confirmation');
        case 'test_database':
            return cms_mcp_ok(['ok' => true, 'note' => 'File-based storage active']);
        default:
            return cms_mcp_error('Unknown settings action: ' . $subAction);
    }
}

function cms_mcp_ensure_invitations_helpers_loaded(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $GLOBALS['__CMS_MCP_LOAD_FUNCTIONS_ONLY'] = true;
    require_once dirname(__DIR__) . '/api/invitations.php';
    unset($GLOBALS['__CMS_MCP_LOAD_FUNCTIONS_ONLY']);
    $loaded = true;
}

/** @return array<string, mixed> */
function cms_mcp_handler_invitations(string $subAction, array $input, array $aiUser): array
{
    cms_mcp_bootstrap_session($aiUser);
    cms_mcp_ensure_invitations_helpers_loaded();

    switch ($subAction) {
        case 'list':
            return cms_mcp_ok(['invitations' => birkly_invitations_for_admin()]);
        case 'create':
            ob_start();
            createInvitation($input);
            $out = ob_get_clean();
            break;
        case 'resend':
            ob_start();
            resendInvitation($input);
            $out = ob_get_clean();
            break;
        case 'cancel':
            ob_start();
            cancelInvitation($input);
            $out = ob_get_clean();
            break;
        default:
            return cms_mcp_error('Unknown invitations action: ' . $subAction);
    }

    $decoded = json_decode($out ?? '', true);
    if (!is_array($decoded)) {
        return cms_mcp_error('Invitation action failed');
    }
    if (($decoded['status'] ?? '') !== 'ok') {
        return cms_mcp_error((string) ($decoded['message'] ?? 'Invitation action failed'));
    }
    return cms_mcp_ok(is_array($decoded['data'] ?? null) ? $decoded['data'] : []);
}

function cms_mcp_ensure_components_helpers_loaded(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $GLOBALS['__CMS_MCP_LOAD_FUNCTIONS_ONLY'] = true;
    require_once dirname(__DIR__) . '/api/components.php';
    unset($GLOBALS['__CMS_MCP_LOAD_FUNCTIONS_ONLY']);
    $loaded = true;
}

/** @return array<string, mixed> */
function cms_mcp_handler_components(string $subAction, array $input, array $aiUser): array
{
    require_once dirname(__DIR__) . '/fieldtypes/ContentEditor/components/ComponentRegistry.php';
    $registry = new \Birkly\FieldTypes\ContentEditor\ComponentRegistry();

    switch ($subAction) {
        case 'list':
            return cms_mcp_ok(['components' => $registry->listAll()]);
        case 'get':
            $slug = (string) ($input['slug'] ?? $input['id'] ?? '');
            if ($slug === '') {
                return cms_mcp_error('slug required');
            }
            $component = $registry->get($slug);
            return $component ? cms_mcp_ok(['component' => $component]) : cms_mcp_error('Not found');
        case 'save':
            $registry->save($input);
            return cms_mcp_ok(['saved' => true]);
        case 'delete':
            $slug = (string) ($input['slug'] ?? $input['id'] ?? '');
            if ($slug === '') {
                return cms_mcp_error('slug required');
            }
            $registry->delete($slug);
            return cms_mcp_ok(['deleted' => true]);
        case 'render':
            $slug = (string) ($input['slug'] ?? '');
            if ($slug === '') {
                return cms_mcp_error('slug required');
            }
            $component = $registry->get($slug);
            if (!$component) {
                return cms_mcp_error('Component not found');
            }
            $entryData = is_array($input['entry_data'] ?? null) ? $input['entry_data'] : [];
            return cms_mcp_ok(['component' => $component, 'entry_data' => $entryData]);
        case 'find_entries':
            cms_mcp_ensure_components_helpers_loaded();
            $slug = (string) ($input['slug'] ?? '');
            if ($slug === '') {
                return cms_mcp_error('slug required');
            }
            return cms_mcp_ok(['entries' => findEntriesUsingComponent($slug)]);
        case 'update_entries':
            cms_mcp_ensure_components_helpers_loaded();
            $slug = (string) ($input['slug'] ?? '');
            if ($slug === '') {
                return cms_mcp_error('slug required');
            }
            $component = $registry->get($slug);
            if (!$component) {
                return cms_mcp_error('Component not found');
            }
            $result = updateComponentInEntries($slug, $component);
            return ($result['success'] ?? false) ? cms_mcp_ok($result) : cms_mcp_error($result['error'] ?? 'Update failed');
        default:
            return cms_mcp_error('Unknown components action: ' . $subAction);
    }
}

/** @return array<string, mixed> */
function cms_mcp_handler_notifications(string $subAction, array $input, array $aiUser): array
{
    cms_mcp_bootstrap_session($aiUser);
    cms_mcp_ensure_notifications_helpers_loaded();

    $userId = (string) ($_SESSION['user']['id'] ?? '');

    switch ($subAction) {
        case 'list_recent':
            $limit = max(1, min(100, (int) ($input['limit'] ?? 20)));
            return cms_mcp_ok(birkly_notifications_list_recent($limit));
        case 'unread_count':
            return cms_mcp_ok(birkly_notifications_unread_count_data());
        case 'mark_seen':
            $ids = is_array($input['ids'] ?? null) ? $input['ids'] : [];
            markNotificationsSeen($userId, $ids);
            return cms_mcp_ok(['seen' => true]);
        case 'mark_read':
            $notificationId = (string) ($input['id'] ?? '');
            if ($notificationId === '') {
                return cms_mcp_error('Notification id required');
            }
            markNotificationRead($userId, $notificationId);
            birkly_notifications_load_plugin_activity();
            if (function_exists('birkly_plugin_activity_mark_read')) {
                birkly_plugin_activity_mark_read([$notificationId]);
            }
            return cms_mcp_ok(['read' => true]);
        case 'mark_all_read':
            $recent = birkly_notifications_list_recent(500);
            $notificationIds = array_values(array_filter(array_map(
                static fn (array $row): string => (string) ($row['id'] ?? ''),
                $recent['notifications'] ?? []
            )));
            birkly_notifications_load_plugin_activity();
            if (function_exists('birkly_plugin_activity_notify_ids')) {
                $notificationIds = array_values(array_unique(array_merge(
                    $notificationIds,
                    birkly_plugin_activity_notify_ids(false)
                )));
                if (function_exists('birkly_plugin_activity_mark_read')) {
                    birkly_plugin_activity_mark_read($notificationIds);
                }
            }
            markAllNotificationsRead($userId, $notificationIds);
            return cms_mcp_ok(['marked' => count($notificationIds)]);
        default:
            return cms_mcp_error('Unknown notifications action: ' . $subAction);
    }
}

function cms_mcp_ensure_notifications_helpers_loaded(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $GLOBALS['__CMS_MCP_LOAD_FUNCTIONS_ONLY'] = true;
    require_once dirname(__DIR__) . '/api/notifications.php';
    unset($GLOBALS['__CMS_MCP_LOAD_FUNCTIONS_ONLY']);
    $loaded = true;
}

/** @return array<string, mixed> */
function cms_mcp_handler_dashboard(string $subAction, array $input, array $aiUser): array
{
    cms_mcp_bootstrap_session($aiUser);
    switch ($subAction) {
        case 'stats':
            require_once dirname(__DIR__) . '/core/storage/StorageFactory.php';
            if (!function_exists('get_active_plugins')) {
                require_once dirname(__DIR__) . '/core/plugins.php';
            }
            $storage = get_storage();
            $collections = $storage->list('collections');
            $collectionCount = count($collections);
            $totalEntries = 0;
            $published = 0;
            $drafts = 0;
            foreach ($collections as $collectionDir) {
                $entries = $storage->list("collections/$collectionDir/entries");
                foreach ($entries as $entryFile) {
                    $entry = $storage->read("collections/$collectionDir/entries/$entryFile");
                    if (!$entry) {
                        continue;
                    }
                    $totalEntries++;
                    $status = $entry['status'] ?? 'draft';
                    if ($status === 'published') {
                        $published++;
                    } else {
                        $drafts++;
                    }
                }
            }
            $stats = [
                'collections' => $collectionCount,
                'total_entries' => $totalEntries,
                'published' => $published,
                'drafts' => $drafts,
            ];
            foreach (get_active_plugins() as $plugin) {
                if (!isset($plugin['dashboard_stats_callback'])) {
                    continue;
                }
                $pluginStats = call_user_func($plugin['dashboard_stats_callback']);
                if (is_array($pluginStats)) {
                    $stats = array_merge($stats, $pluginStats);
                }
            }
            return cms_mcp_ok(['stats' => $stats]);
        case 'pending_review':
            require_once dirname(__DIR__) . '/core/dashboard_widgets.php';
            $limit = max(1, min(50, (int) ($input['limit'] ?? 12)));
            $assignedTo = trim((string) ($input['assigned_to'] ?? 'all'));
            $currentUserId = (string) ($_SESSION['user']['id'] ?? '');
            $entries = birkly_collect_pending_review_entries($limit, $assignedTo, $currentUserId);
            return cms_mcp_ok(['entries' => $entries, 'total' => count($entries)]);
        case 'activity':
            require_once dirname(__DIR__) . '/core/activity_format.php';
            $limit = max(1, min(100, (int) ($input['limit'] ?? 20)));
            $filter = (string) ($input['filter'] ?? 'all');
            $activity = birkly_activity_read_security_log($limit, 0, $filter, 'all');
            return cms_mcp_ok(['activity' => $activity, 'total' => count($activity)]);
        default:
            return cms_mcp_error('Unknown dashboard action: ' . $subAction);
    }
}

function cms_mcp_ensure_admin_media_loaded(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $GLOBALS['__CMS_MCP_LOAD_FUNCTIONS_ONLY'] = true;
    require_once dirname(__DIR__) . '/api/admin_media.php';
    unset($GLOBALS['__CMS_MCP_LOAD_FUNCTIONS_ONLY']);
    $loaded = true;
}

/** @param array<string, mixed> $input */
function cms_mcp_media_apply_query(array $input): void
{
    $_GET['folder'] = (string) ($input['folder'] ?? '');
    $_GET['search'] = (string) ($input['search'] ?? '');
    $_GET['type'] = (string) ($input['type'] ?? '');
    $_GET['sort'] = (string) ($input['sort'] ?? 'date');
    $_GET['order'] = (string) ($input['order'] ?? 'desc');
    if (isset($input['id'])) {
        $_GET['id'] = (string) $input['id'];
    }
}

/** @param array<string, mixed> $input */
function cms_mcp_media_apply_post(array $input): void
{
    $_POST = $input;
}

/** @return array<string, mixed> */
function cms_mcp_handler_media(string $subAction, array $input, array $aiUser): array
{
    cms_mcp_bootstrap_session($aiUser);
    cms_mcp_ensure_admin_media_loaded();

    return cms_mcp_with_json_input($input, static function () use ($subAction, $input): array {
        $prevGet = $_GET;
        $prevPost = $_POST;
        try {
            switch ($subAction) {
                case 'list':
                    cms_mcp_media_apply_query($input);
                    $listed = listMedia();
                    if (isset($listed['error'])) {
                        return cms_mcp_error((string) $listed['error']);
                    }
                    if (($listed['status'] ?? '') === 'ok') {
                        return cms_mcp_ok([
                            'media' => $listed['data'] ?? [],
                            'count' => count($listed['data'] ?? []),
                        ]);
                    }
                    return cms_mcp_ok(['media' => is_array($listed) ? $listed : [], 'count' => 0]);
                case 'get':
                    cms_mcp_media_apply_query($input);
                    $item = getMediaItem();
                    if (($item['status'] ?? '') === 'ok') {
                        return cms_mcp_ok($item['data'] ?? $item);
                    }
                    if (isset($item['error'])) {
                        return cms_mcp_error((string) $item['error']);
                    }
                    return cms_mcp_ok($item);
                case 'folders':
                    $folders = listFolders();
                    if (($folders['status'] ?? '') === 'ok') {
                        return cms_mcp_ok($folders['data'] ?? $folders);
                    }
                    return cms_mcp_ok($folders);
                case 'upload':
                    return ['status' => 'error', 'message' => 'Use upload_media tool for url/base64 uploads'];
                case 'update':
                    cms_mcp_media_apply_post([
                        'id' => (string) ($input['id'] ?? ''),
                        'title' => (string) ($input['title'] ?? $input['name'] ?? ''),
                        'alt_text' => array_key_exists('alt_text', $input)
                            ? (string) $input['alt_text']
                            : (array_key_exists('alt', $input) ? (string) $input['alt'] : null),
                        'focal_x' => $input['focal_x'] ?? ($input['focal_point']['x'] ?? null),
                        'focal_y' => $input['focal_y'] ?? ($input['focal_point']['y'] ?? null),
                    ]);
                    return updateMedia();
                case 'delete':
                    cms_mcp_media_apply_post(['id' => (string) ($input['id'] ?? '')]);
                    return deleteMedia();
                case 'create_folder':
                    cms_mcp_media_apply_post(['name' => (string) ($input['name'] ?? '')]);
                    return createFolder();
                case 'rename_folder':
                    cms_mcp_media_apply_post([
                        'old_name' => (string) ($input['old_name'] ?? $input['name'] ?? ''),
                        'new_name' => (string) ($input['new_name'] ?? $input['to'] ?? ''),
                    ]);
                    return renameFolder();
                case 'delete_folder':
                    $handle = (string) ($input['handle_files'] ?? 'move');
                    if ($handle !== 'delete') {
                        $handle = 'move';
                    }
                    cms_mcp_media_apply_post([
                        'name' => (string) ($input['name'] ?? ''),
                        'handle_files' => $handle,
                    ]);
                    return deleteFolder();
                case 'move':
                    cms_mcp_media_apply_post([
                        'id' => (string) ($input['id'] ?? ''),
                        'folder' => (string) ($input['folder'] ?? ''),
                    ]);
                    return moveToFolder();
                default:
                    return cms_mcp_error('Unknown media action: ' . $subAction);
            }
        } finally {
            $_GET = $prevGet;
            $_POST = $prevPost;
        }
    });
}

function cms_mcp_ensure_hosting_loaded(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $GLOBALS['__CMS_MCP_LOAD_FUNCTIONS_ONLY'] = true;
    require_once dirname(__DIR__) . '/api/hosting.php';
    unset($GLOBALS['__CMS_MCP_LOAD_FUNCTIONS_ONLY']);
    $loaded = true;
}

/** @return array<string, mixed> */
function cms_mcp_handler_hosting(string $subAction, array $input, array $aiUser): array
{
    cms_mcp_bootstrap_session($aiUser);
    cms_mcp_ensure_hosting_loaded();

    switch ($subAction) {
        case 'get':
            return cms_mcp_ok(birkly_hosting_api_payload());
        case 'probe':
            require_once dirname(__DIR__) . '/core/hosting_probe.php';
            $deep = !empty($input['deep']);
            return cms_mcp_ok(['delivery' => birkly_delivery_probe(['deep' => $deep])]);
        case 'set_domain_role':
            $host = trim((string) ($input['host'] ?? $input['domain'] ?? ''));
            $role = trim((string) ($input['role'] ?? ''));
            if ($host === '' || $role === '') {
                return cms_mcp_error('host and role are required');
            }
            $result = birkly_hosting_set_domain_delivery_role($host, $role);
            if (($result['status'] ?? '') !== 'ok') {
                return cms_mcp_error((string) ($result['message'] ?? 'Could not set domain role'));
            }
            return cms_mcp_ok(array_merge(
                ['message' => (string) ($result['message'] ?? 'Domain role updated')],
                birkly_hosting_api_payload()
            ));
        default:
            return cms_mcp_error('Unknown hosting action: ' . $subAction);
    }
}

/** @return array<string, mixed> */
function cms_mcp_handler_discovery(string $subAction, array $input, array $aiUser): array
{
    cms_mcp_bootstrap_session($aiUser);
    require_once dirname(__DIR__) . '/core/discovery.php';

    switch ($subAction) {
        case 'get':
            return cms_mcp_ok(birkly_discovery_status());
        case 'save_seo':
            require_once dirname(__DIR__) . '/core/site_seo.php';
            $payload = $input['config'] ?? $input;
            if (!is_array($payload)) {
                return cms_mcp_error('config object is required');
            }
            unset($payload['action'], $payload['_csrf']);
            $result = birkly_site_seo_save($payload);
            if (empty($result['ok'])) {
                return cms_mcp_error((string) ($result['error'] ?? 'Could not save SEO config'));
            }
            return cms_mcp_ok([
                'seo' => $result['config'] ?? [],
                'status' => birkly_discovery_status(),
            ]);
        default:
            return cms_mcp_error('Unknown discovery action: ' . $subAction);
    }
}

/** @return array<string, mixed> */
function cms_mcp_dispatch_domain(string $domain, string $subAction, array $input, array $aiUser): array
{
    return match ($domain) {
        'entries' => cms_mcp_handler_entries($subAction, $input, $aiUser),
        'content' => cms_mcp_handler_content($subAction, $input, $aiUser),
        'sub_entries' => cms_mcp_handler_sub_entries($subAction, $input, $aiUser),
        'connections' => cms_mcp_handler_connections($subAction, $input, $aiUser),
        'agent_guide' => cms_mcp_handler_agent_guide($subAction, $input, $aiUser),
        'mcp_endpoints' => cms_mcp_handler_mcp_endpoints($subAction, $input, $aiUser),
        'ai_agents' => cms_mcp_handler_ai_agents($subAction, $input, $aiUser),
        'users' => cms_mcp_handler_users($subAction, $input, $aiUser),
        'roles' => cms_mcp_handler_roles($subAction, $input, $aiUser),
        'marketplace' => cms_mcp_handler_marketplace($subAction, $input, $aiUser),
        'project_git' => cms_mcp_handler_project_git($subAction, $input, $aiUser),
        'project_site' => cms_mcp_handler_project_site($subAction, $input, $aiUser),
        'webhooks' => cms_mcp_handler_webhooks($subAction, $input, $aiUser),
        'redirects' => cms_mcp_handler_redirects($subAction, $input, $aiUser),
        'plugins' => cms_mcp_handler_plugins($subAction, $input, $aiUser),
        'plugins_permissions' => cms_mcp_handler_plugins_permissions($subAction, $input, $aiUser),
        'search' => cms_mcp_handler_search($subAction, $input, $aiUser),
        'analytics' => cms_mcp_handler_analytics($subAction, $input, $aiUser),
        'webmcp' => cms_mcp_handler_webmcp($subAction, $input, $aiUser),
        'settings' => cms_mcp_handler_settings($subAction, $input, $aiUser),
        'invitations' => cms_mcp_handler_invitations($subAction, $input, $aiUser),
        'notifications' => cms_mcp_handler_notifications($subAction, $input, $aiUser),
        'components' => cms_mcp_handler_components($subAction, $input, $aiUser),
        'dashboard' => cms_mcp_handler_dashboard($subAction, $input, $aiUser),
        'hosting' => cms_mcp_handler_hosting($subAction, $input, $aiUser),
        'discovery' => cms_mcp_handler_discovery($subAction, $input, $aiUser),
        'media' => cms_mcp_handler_media($subAction, $input, $aiUser),
        default => cms_mcp_error('Unknown domain: ' . $domain),
    };
}
