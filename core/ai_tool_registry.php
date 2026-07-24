<?php
/**
 * P3-M4: Role-scoped AI tool definitions derived from core/ai_permissions.php.
 */

require_once __DIR__ . '/ai_permissions.php';

/** @return array<string, array{name: string, description: string, category: string, inputSchema: array}> */
function birkly_ai_tool_definitions(): array
{
    return [
        'read_entry' => ['name' => 'read_entry', 'description' => 'Read a specific entry by collection slug and entry slug or ID', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['collection' => ['type' => 'string'], 'slug' => ['type' => 'string'], 'id' => ['type' => 'string']], 'required' => ['collection']]],
        'list_entries' => ['name' => 'list_entries', 'description' => 'List entries in a collection. Use the slug from list_collections (or the display name — both work). Returns id, slug, title, and status summaries.', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['collection' => ['type' => 'string', 'description' => 'Collection slug or name from list_collections'], 'limit' => ['type' => 'number']], 'required' => ['collection']]],
        'read_collection' => ['name' => 'read_collection', 'description' => 'Get details and schema of a collection', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['slug' => ['type' => 'string']], 'required' => ['slug']]],
        'list_collections' => ['name' => 'list_collections', 'description' => 'List all content collections', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'get_collection' => ['name' => 'get_collection', 'description' => 'Get details of a specific collection (MCP alias)', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['slug' => ['type' => 'string']], 'required' => ['slug']]],
        'get_system_context' => ['name' => 'get_system_context', 'description' => 'Get curated system context', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'read_birkly_docs' => ['name' => 'read_birkly_docs', 'description' => 'Read a Birkly documentation topic (templating, admin, plugins, ai, mcp, or active plugin slug e.g. commerce)', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['topic' => ['type' => 'string', 'description' => 'Topic slug: templating, admin, plugins, ai, mcp, commerce, …']], 'required' => ['topic']]],
        'list_media' => ['name' => 'list_media', 'description' => 'List media library files', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'read_media' => ['name' => 'read_media', 'description' => 'Read metadata for a media file', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']], 'required' => ['id']]],
        'list_users' => ['name' => 'list_users', 'description' => 'List human users', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'read_user' => ['name' => 'read_user', 'description' => 'Read a user by ID or email', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string'], 'email' => ['type' => 'string']]]],
        'read_settings' => ['name' => 'read_settings', 'description' => 'Read system settings', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'list_roles' => ['name' => 'list_roles', 'description' => 'List defined roles', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'read_role' => ['name' => 'read_role', 'description' => 'Read a role by ID or name', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string'], 'name' => ['type' => 'string']]]],
        'list_pages' => ['name' => 'list_pages', 'description' => 'List site pages', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'read_page' => ['name' => 'read_page', 'description' => 'Read a page by slug or ID', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['slug' => ['type' => 'string'], 'id' => ['type' => 'string']]]],
        'list_activity' => ['name' => 'list_activity', 'description' => 'List recent activity log entries', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['limit' => ['type' => 'number']]]],
        'list_plugins' => ['name' => 'list_plugins', 'description' => 'List installed plugins', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'list_sub_entries' => ['name' => 'list_sub_entries', 'description' => 'List sub-entries for a parent entry. Requires parent collection slug, parent entry ID, and sub-collection slug.', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['collection' => ['type' => 'string', 'description' => 'Parent collection slug'], 'entry_id' => ['type' => 'string', 'description' => 'Parent entry ID'], 'sub_collection' => ['type' => 'string', 'description' => 'Sub-collection slug (e.g. comments)'], 'limit' => ['type' => 'number']], 'required' => ['collection', 'entry_id', 'sub_collection']]],
        'read_sub_entry' => ['name' => 'read_sub_entry', 'description' => 'Read a single sub-entry by ID', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['collection' => ['type' => 'string', 'description' => 'Parent collection slug'], 'entry_id' => ['type' => 'string', 'description' => 'Parent entry ID'], 'sub_collection' => ['type' => 'string', 'description' => 'Sub-collection slug'], 'sub_entry_id' => ['type' => 'string', 'description' => 'Sub-entry ID']], 'required' => ['collection', 'entry_id', 'sub_collection', 'sub_entry_id']]],
        'create_sub_entry' => ['name' => 'create_sub_entry', 'description' => 'Create a new sub-entry under a parent entry (approval-gated)', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['collection' => ['type' => 'string', 'description' => 'Parent collection slug'], 'entry_id' => ['type' => 'string', 'description' => 'Parent entry ID'], 'sub_collection' => ['type' => 'string', 'description' => 'Sub-collection slug'], 'data' => ['type' => 'object', 'description' => 'Sub-entry field values']], 'required' => ['collection', 'entry_id', 'sub_collection']]],
        'update_sub_entry' => ['name' => 'update_sub_entry', 'description' => 'Update an existing sub-entry (approval-gated)', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['collection' => ['type' => 'string'], 'entry_id' => ['type' => 'string'], 'sub_collection' => ['type' => 'string'], 'sub_entry_id' => ['type' => 'string'], 'data' => ['type' => 'object']], 'required' => ['collection', 'entry_id', 'sub_collection', 'sub_entry_id']]],
        'delete_sub_entry' => ['name' => 'delete_sub_entry', 'description' => 'Delete a sub-entry (approval-gated)', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['collection' => ['type' => 'string'], 'entry_id' => ['type' => 'string'], 'sub_collection' => ['type' => 'string'], 'sub_entry_id' => ['type' => 'string']], 'required' => ['collection', 'entry_id', 'sub_collection', 'sub_entry_id']]],
        'create_collection' => ['name' => 'create_collection', 'description' => 'Create a new content collection', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string'], 'slug' => ['type' => 'string'], 'fields' => ['type' => 'array']], 'required' => ['name', 'slug']]],
        'update_collection' => ['name' => 'update_collection', 'description' => 'Update an existing collection', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['slug' => ['type' => 'string'], 'data' => ['type' => 'object']], 'required' => ['slug']]],
        'delete_collection' => ['name' => 'delete_collection', 'description' => 'Delete a collection', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['slug' => ['type' => 'string']], 'required' => ['slug']]],
        'create_entry' => ['name' => 'create_entry', 'description' => 'Create a new entry in a collection', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['collection' => ['type' => 'string'], 'data' => ['type' => 'object']], 'required' => ['collection']]],
        'update_entry' => ['name' => 'update_entry', 'description' => 'Update an existing entry', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['collection' => ['type' => 'string'], 'id' => ['type' => 'string'], 'data' => ['type' => 'object']], 'required' => ['collection', 'id']]],
        'delete_entry' => ['name' => 'delete_entry', 'description' => 'Delete an entry', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['collection' => ['type' => 'string'], 'id' => ['type' => 'string']], 'required' => ['collection', 'id']]],
        'upload_media' => ['name' => 'upload_media', 'description' => 'Upload a media file', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'delete_media' => ['name' => 'delete_media', 'description' => 'Delete a media file', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']], 'required' => ['id']]],
        'update_media' => ['name' => 'update_media', 'description' => 'Update media metadata', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string'], 'data' => ['type' => 'object']], 'required' => ['id']]],
        'create_media_folder' => ['name' => 'create_media_folder', 'description' => 'Create a media folder', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']], 'required' => ['name']]],
        'delete_media_folder' => ['name' => 'delete_media_folder', 'description' => 'Delete a media folder', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']], 'required' => ['name']]],
        'move_media' => ['name' => 'move_media', 'description' => 'Move a media file', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string'], 'folder' => ['type' => 'string']], 'required' => ['id']]],
        'update_settings' => ['name' => 'update_settings', 'description' => 'Update system settings', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['category' => ['type' => 'string'], 'data' => ['type' => 'object']]]],
        'create_page' => ['name' => 'create_page', 'description' => 'Create a new page', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'update_page' => ['name' => 'update_page', 'description' => 'Update an existing page', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['slug' => ['type' => 'string'], 'data' => ['type' => 'object']]]],
        'update_user' => ['name' => 'update_user', 'description' => 'Update a user account', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string'], 'data' => ['type' => 'object']]]],
        'delete_user' => ['name' => 'delete_user', 'description' => 'Delete a user account', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]]],
        'create_role' => ['name' => 'create_role', 'description' => 'Create a new role', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'update_role' => ['name' => 'update_role', 'description' => 'Update a role', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'delete_role' => ['name' => 'delete_role', 'description' => 'Delete a role', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]]],
        'delete_page' => ['name' => 'delete_page', 'description' => 'Delete a page', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['slug' => ['type' => 'string']]]],
        'activate_plugin' => ['name' => 'activate_plugin', 'description' => 'Activate a plugin', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]]],
        'deactivate_plugin' => ['name' => 'deactivate_plugin', 'description' => 'Deactivate a plugin', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]]],
        'list_project_files' => ['name' => 'list_project_files', 'description' => 'List files and folders in the public site (project/). Optional path relative to project root.', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['path' => ['type' => 'string', 'description' => 'Subdirectory under project/, e.g. css or pages']]]],
        'read_project_file' => ['name' => 'read_project_file', 'description' => 'Read a file from the public site (project/). Allowed: html, css, js, json, md, svg, webp, txt', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['path' => ['type' => 'string', 'description' => 'Relative path, e.g. index.html or css/site.css']], 'required' => ['path']]],
        'get_project_info' => ['name' => 'get_project_info', 'description' => 'Get public site paths, collections, and templating cheat sheet for building CMS-integrated HTML', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'write_project_file' => ['name' => 'write_project_file', 'description' => 'Create or overwrite a file in project/. PHP and dotfiles are blocked.', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['path' => ['type' => 'string'], 'content' => ['type' => 'string']], 'required' => ['path', 'content']]],
        'create_project_file' => ['name' => 'create_project_file', 'description' => 'Create a new file in project/ (fails if path exists). PHP and dotfiles are blocked.', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['path' => ['type' => 'string'], 'content' => ['type' => 'string']], 'required' => ['path']]],
        'delete_project_file' => ['name' => 'delete_project_file', 'description' => 'Delete a file from project/ (admin role by default). Directories are not deleted.', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['path' => ['type' => 'string']], 'required' => ['path']]],
    ];
}

/** @return array<string, array> */
function birkly_ai_tools_for_user(array $aiUser): array
{
    $definitions = birkly_ai_tool_definitions();
    $allowed = [];
    foreach (birkly_ai_action_permission_map() as $actionType => $_) {
        if (!isset($definitions[$actionType])) {
            continue;
        }
        if (birkly_ai_user_can_action($aiUser, $actionType)) {
            $allowed[$actionType] = $definitions[$actionType];
        }
    }
    return $allowed;
}

function birkly_ai_is_read_action(string $actionType): bool
{
    $definitions = birkly_ai_tool_definitions();
    return isset($definitions[$actionType]) && ($definitions[$actionType]['category'] ?? '') === 'read';
}

function birkly_ai_normalize_tool_input_schema(array $schema): array
{
    if (isset($schema['properties']) && is_array($schema['properties']) && $schema['properties'] === []) {
        $schema['properties'] = (object) [];
    } elseif (isset($schema['properties']) && is_array($schema['properties'])) {
        foreach ($schema['properties'] as $key => $prop) {
            if (is_array($prop)) {
                $schema['properties'][$key] = birkly_ai_normalize_tool_input_schema($prop);
            }
        }
    }
    return $schema;
}

function birkly_ai_tools_openai_format(array $aiUser): array
{
    $tools = [];
    foreach (birkly_ai_tools_for_user($aiUser) as $def) {
        $tools[] = [
            'type' => 'function',
            'function' => [
                'name' => $def['name'],
                'description' => $def['description'],
                'parameters' => birkly_ai_normalize_tool_input_schema($def['inputSchema']),
            ],
        ];
    }
    return $tools;
}

function birkly_ai_tools_mcp_format(array $aiUser): array
{
    $tools = [];
    foreach (birkly_ai_tools_for_user($aiUser) as $def) {
        $tools[] = [
            'name' => $def['name'],
            'description' => $def['description'],
            'inputSchema' => birkly_ai_normalize_tool_input_schema($def['inputSchema']),
        ];
    }
    return $tools;
}

function birkly_ai_tools_prompt_section(array $aiUser): string
{
    $lines = ["AVAILABLE TOOLS (use JSON actions or native tool calls):"];
    foreach (birkly_ai_tools_for_user($aiUser) as $def) {
        $lines[] = '- ' . $def['name'] . ' [' . $def['category'] . ']: ' . $def['description'];
    }
    return implode("\n", $lines) . "\n";
}

function birkly_ai_tool_calls_to_actions(array $toolCalls): array
{
    $actions = [];
    foreach ($toolCalls as $call) {
        $name = $call['function']['name'] ?? $call['name'] ?? '';
        if ($name === '') {
            continue;
        }
        $rawArgs = $call['function']['arguments'] ?? $call['arguments'] ?? '{}';
        $args = is_string($rawArgs) ? (json_decode($rawArgs, true) ?: []) : (is_array($rawArgs) ? $rawArgs : []);
        if (in_array($name, ['create_entry', 'update_entry'], true) && isset($args['data']) && is_array($args['data'])) {
            $inner = $args['data'];
            unset($args['data']);
            $args = array_merge($args, $inner);
        }
        $type = $name === 'get_collection' ? 'read_collection' : $name;
        if ($type === 'read_collection' && isset($args['slug']) && !isset($args['collection'])) {
            $args = ['slug' => $args['slug']];
        }
        $actions[] = ['type' => $type, 'data' => $args, 'description' => $name];
    }
    return $actions;
}

function birkly_ai_dispatch_tool_call(string $toolName, array $arguments, array $aiUser, string $source = 'mcp'): array
{
    require_once __DIR__ . '/../api/storage.php';
    require_once __DIR__ . '/../api/ai_helpers.php';

    $actionType = $toolName === 'get_collection' ? 'read_collection' : $toolName;
    if ($toolName === 'get_collection' && isset($arguments['slug'])) {
        $arguments = ['slug' => $arguments['slug']];
    }

    $denied = birkly_ai_enforce_action_permission($aiUser, $actionType);
    if ($denied !== null) {
        return ['isError' => true, 'content' => [['type' => 'text', 'text' => $denied['message']]]];
    }

    if ($actionType === 'get_system_context') {
        return ['content' => [['type' => 'text', 'text' => json_encode(generateSystemContext($aiUser), JSON_PRETTY_PRINT)]]];
    }

    if ($actionType === 'read_birkly_docs') {
        $topic = readBirklyDocsTopic((string) ($arguments['topic'] ?? ''));
        if ($topic === null) {
            return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Unknown or missing docs topic. Use: templating, admin, plugins, ai, mcp, or an active plugin slug (e.g. commerce)']]];
        }
        return ['content' => [['type' => 'text', 'text' => "# {$topic['title']}\n\n{$topic['text']}"]]];
    }

    if (birkly_ai_is_read_action($actionType)) {
        require_once __DIR__ . '/../api/ai_read_actions.php';
        if ($source === 'mcp' && in_array($actionType, ['list_entries'], true)) {
            $arguments['summary'] = true;
        }
        $result = executeReadAction(['type' => $actionType, 'data' => $arguments], $aiUser);
        if (!($result['success'] ?? false)) {
            return ['isError' => true, 'content' => [['type' => 'text', 'text' => $result['message'] ?? 'Action failed']]];
        }
        return ['content' => [['type' => 'text', 'text' => json_encode($result['data'] ?? $result, JSON_PRETTY_PRINT)]]];
    }

    // Inbound MCP: the client app (Claude, ChatGPT, Cursor) handles approval UX.
    // Birkly only enforces role permissions. Outbound admin chat keeps require_approval.
    $requireApproval = false;
    if ($source !== 'mcp') {
        $requireApproval = $aiUser['require_approval'] ?? true;
        if (is_string($requireApproval)) {
            $requireApproval = in_array(strtolower($requireApproval), ['1', 'true', 'yes', 'on'], true);
        }
    }

    if ($requireApproval) {
        $approvalRoot = dirname(__DIR__) . '/data/ai_approvals';
        $approvalStorage = new Storage($approvalRoot);
        $approvalId = 'approval_' . bin2hex(random_bytes(8));
        $approvalStorage->write($approvalId . '.json', [
            'id' => $approvalId,
            'ai_user_id' => $aiUser['id'],
            'action_type' => $actionType,
            'action_data' => $arguments,
            'description' => ucfirst(str_replace('_', ' ', $actionType)),
            'status' => 'pending',
            'created_at' => date('c'),
            'source' => $source,
            'authorized_by' => (string) ($aiUser['authorized_by'] ?? ''),
            'agent_type' => (string) ($aiUser['agent_type'] ?? ''),
        ]);
        $message = 'Write action queued in Birkly (approval ID: ' . $approvalId . '). '
            . 'Open your Birkly admin panel → floating AI button → Approvals tab to approve. '
            . 'This is separate from Claude\'s "always allow" tool setting. '
            . 'To auto-run writes from Claude, disable "Ask before changes" on the personal connector in Settings → AI.';
        return ['content' => [['type' => 'text', 'text' => $message]]];
    }

    $GLOBALS['__HARU_INTERNAL_CALL'] = true;
    require_once __DIR__ . '/../api/ai_approvals.php';
    $result = executeAiAction([
        'action_type' => $actionType,
        'action_data' => $arguments,
        'ai_user_id' => $aiUser['id'],
        'ai_user' => $aiUser,
    ]);
    unset($GLOBALS['__HARU_INTERNAL_CALL']);
    if (!($result['success'] ?? false)) {
        return ['isError' => true, 'content' => [['type' => 'text', 'text' => $result['message'] ?? 'Execution failed']]];
    }
    return ['content' => [['type' => 'text', 'text' => json_encode($result, JSON_PRETTY_PRINT)]]];
}

function birkly_ai_mcp_scheme(): string
{
    $scheme = 'http';
    if (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
        || (!empty($_SERVER['REQUEST_SCHEME']) && strtolower((string) $_SERVER['REQUEST_SCHEME']) === 'https')
    ) {
        $scheme = 'https';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']);
        if ($proto === 'https' || $proto === 'http') {
            $scheme = $proto;
        }
    }
    return $scheme;
}

function birkly_ai_mcp_server_url(): string
{
    require_once __DIR__ . '/birkly_paths.php';
    require_once __DIR__ . '/mcp_protocol.php';
    return birkly_ai_mcp_scheme() . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . birkly_url(birkly_mcp_connector_path());
}

function birkly_ai_mcp_legacy_server_url(): string
{
    require_once __DIR__ . '/birkly_paths.php';
    require_once __DIR__ . '/mcp_protocol.php';
    return birkly_ai_mcp_scheme() . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . birkly_url(birkly_mcp_legacy_path());
}
