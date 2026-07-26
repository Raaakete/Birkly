<?php
/**
 * Shared MCP JSON-RPC protocol handlers (P5-M2).
 * Used by legacy api/mcp_server.php and Streamable HTTP api/mcp.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/ai_tool_registry.php';
require_once __DIR__ . '/mcp_endpoints.php';

define('BIRKLY_MCP_PROTOCOL_VERSION', '2025-03-26');

function birkly_mcp_connector_path(): string
{
    return '/mcp';
}

function birkly_mcp_valid_agent_id(string $agentId): bool
{
    $agentId = trim($agentId);
    if ($agentId === '') {
        return false;
    }

    return (bool) preg_match('/^ai_agent_(?:personal|[a-f0-9]{16})$/', $agentId);
}

function birkly_mcp_connector_path_for_agent(string $agentId): string
{
    $agentId = trim($agentId);
    if (!birkly_mcp_valid_agent_id($agentId)) {
        throw new InvalidArgumentException('Invalid AI agent id');
    }

    return birkly_mcp_connector_path() . '/agents/' . $agentId;
}

function birkly_mcp_request_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (!is_string($uri) || $uri === '') {
        return '/';
    }

    require_once __DIR__ . '/birkly_paths.php';
    $base = birkly_base_path();
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
        if ($uri === '') {
            return '/';
        }
    }

    return $uri;
}

function birkly_mcp_agent_id_from_path(?string $path = null): ?string
{
    $path = $path ?? birkly_mcp_request_path();
    if (preg_match('#/mcp/agents/(ai_agent_(?:personal|[a-f0-9]{16}))#', $path, $matches)) {
        return $matches[1];
    }

    return null;
}

function birkly_mcp_agent_id_from_resource_url(string $resourceUrl): ?string
{
    $path = parse_url($resourceUrl, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return null;
    }

    return birkly_mcp_agent_id_from_path($path);
}

function birkly_mcp_legacy_path(): string
{
    return '/api/mcp_server.php';
}

function birkly_mcp_handle_initialize(array $aiUser): array
{
    return [
        'protocolVersion' => BIRKLY_MCP_PROTOCOL_VERSION,
        'capabilities' => ['tools' => (object) [], 'resources' => (object) []],
        'serverInfo' => ['name' => 'Birkly MCP Server', 'version' => '1.2.0'],
    ];
}

function birkly_mcp_handle_tools_list(array $aiUser): array
{
    return ['tools' => birkly_ai_tools_mcp_format($aiUser)];
}

function birkly_mcp_handle_tool_call(array $params, array $aiUser): array
{
    $toolName = $params['name'] ?? '';
    $arguments = $params['arguments'] ?? [];
    if ($toolName === '') {
        return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Tool name is required']]];
    }
    return birkly_ai_dispatch_tool_call($toolName, $arguments, $aiUser, 'mcp');
}

function birkly_mcp_handle_resources_list(): array
{
    if (function_exists('birkly_bootstrap_plugins_for_docs')) {
        birkly_bootstrap_plugins_for_docs();
    }

    $resources = [
        ['uri' => 'birkly://collections', 'name' => 'All Collections', 'description' => 'List of all content collections', 'mimeType' => 'application/json'],
        ['uri' => 'birkly://documentation', 'name' => 'Documentation', 'description' => 'Curated Birkly context pack', 'mimeType' => 'text/markdown'],
        ['uri' => 'birkly://docs/templating', 'name' => 'Templating', 'description' => 'Client-site template language spec', 'mimeType' => 'text/markdown'],
        ['uri' => 'birkly://docs/admin', 'name' => 'Admin', 'description' => 'Admin templating and platform overview', 'mimeType' => 'text/markdown'],
        ['uri' => 'birkly://docs/plugins', 'name' => 'Plugins', 'description' => 'Plugin architecture and client embed', 'mimeType' => 'text/markdown'],
        ['uri' => 'birkly://docs/ai', 'name' => 'AI & MCP', 'description' => 'BYOAI and MCP architecture', 'mimeType' => 'text/markdown'],
        ['uri' => 'birkly://docs/sub-collections', 'name' => 'Sub-collections', 'description' => 'Sub-collections and sub-entries: schema, disk layout, admin URLs, MCP tools', 'mimeType' => 'text/markdown'],
        ['uri' => 'birkly://project', 'name' => 'Public site (project/)', 'description' => 'Paths, collections, and templating cheat sheet for AI site building', 'mimeType' => 'application/json'],
        ['uri' => 'birkly://brand-context', 'name' => 'Brand context (AI Presence)', 'description' => 'Published authoritative brand context for external AI systems', 'mimeType' => 'application/json'],
    ];

    if (function_exists('get_active_plugin_docs')) {
        foreach (get_active_plugin_docs() as $plugin_id => $doc) {
            $slug = (string) ($doc['mcp_topic_slug'] ?? $plugin_id);
            $resources[] = [
                'uri' => 'birkly://docs/plugin/' . $slug,
                'name' => (string) ($doc['title'] ?? $plugin_id) . ' (plugin)',
                'description' => 'Plugin extension docs: ' . (string) ($doc['summary'] ?? $plugin_id),
                'mimeType' => 'text/markdown',
            ];
        }
    }

    return ['resources' => $resources];
}

function birkly_mcp_handle_resource_read(array $params, array $aiUser): array
{
    require_once __DIR__ . '/../api/ai_helpers.php';
    if (function_exists('birkly_bootstrap_plugins_for_docs')) {
        birkly_bootstrap_plugins_for_docs();
    }

    $uri = $params['uri'] ?? '';
    if ($uri === 'birkly://collections') {
        $result = birkly_ai_dispatch_tool_call('list_collections', [], $aiUser, 'mcp');
        return ['contents' => $result['content'] ?? []];
    }
    if ($uri === 'birkly://documentation') {
        $context = generateSystemContext($aiUser);
        return ['contents' => [[
            'uri' => $uri,
            'mimeType' => 'text/markdown',
            'text' => $context['context_pack'] ?? 'No context pack available.',
        ]]];
    }
    if ($uri === 'birkly://project') {
        require_once __DIR__ . '/project_site_filesystem.php';
        $info = birkly_project_site_info();
        return ['contents' => [[
            'uri' => $uri,
            'mimeType' => 'application/json',
            'text' => json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]]];
    }

    if ($uri === 'birkly://brand-context') {
        require_once __DIR__ . '/ai_presence_pro.php';
        $data = birkly_ai_presence_public_brand_for_tools('summary');
        if ($data === null) {
            return ['contents' => [['uri' => $uri, 'mimeType' => 'text/plain', 'text' => 'Brand context not published']]];
        }
        return ['contents' => [[
            'uri' => $uri,
            'mimeType' => 'application/json',
            'text' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]]];
    }

    $topicMap = [
        'birkly://docs/templating' => 'templating',
        'birkly://docs/admin' => 'admin',
        'birkly://docs/plugins' => 'plugins',
        'birkly://docs/ai' => 'ai',
        'birkly://docs/sub-collections' => 'sub-collections',
    ];
    if (isset($topicMap[$uri])) {
        $topic = readBirklyDocsTopic($topicMap[$uri]);
        if ($topic === null) {
            return ['contents' => [['uri' => $uri, 'mimeType' => 'text/plain', 'text' => 'Topic not found']]];
        }
        return ['contents' => [[
            'uri' => $uri,
            'mimeType' => 'text/markdown',
            'text' => "# {$topic['title']}\n\n{$topic['text']}",
        ]]];
    }

    if (preg_match('#^birkly://docs/plugin/([a-z0-9\-_]+)$#', $uri, $matches)) {
        $topic = readBirklyDocsTopic($matches[1]);
        if ($topic === null) {
            return ['contents' => [['uri' => $uri, 'mimeType' => 'text/plain', 'text' => 'Plugin docs not found or plugin inactive']]];
        }
        return ['contents' => [[
            'uri' => $uri,
            'mimeType' => 'text/markdown',
            'text' => "# {$topic['title']}\n\n{$topic['text']}",
        ]]];
    }

    return ['contents' => [['uri' => $uri, 'mimeType' => 'text/plain', 'text' => 'Resource not found']]];
}

/**
 * Dispatch a single JSON-RPC request; returns [result, error] (one null).
 */
function birkly_mcp_dispatch_jsonrpc(array $input, array $aiUser): array
{
    $method = $input['method'] ?? '';
    $params = $input['params'] ?? [];
    $toolName = $params['name'] ?? '';

    $result = null;
    $error = null;

    switch ($method) {
        case 'initialize':
            $result = birkly_mcp_handle_initialize($aiUser);
            break;
        case 'tools/list':
            $result = birkly_mcp_handle_tools_list($aiUser);
            break;
        case 'tools/call':
            $result = birkly_mcp_handle_tool_call($params, $aiUser);
            break;
        case 'resources/list':
            $result = birkly_mcp_handle_resources_list();
            break;
        case 'resources/read':
            $result = birkly_mcp_handle_resource_read($params, $aiUser);
            break;
        case 'ping':
            $result = (object) [];
            break;
        default:
            $error = ['code' => -32601, 'message' => 'Method not found'];
    }

    $success = $error === null && !($result['isError'] ?? false);
    $endpointId = $aiUser['id'] ?? 'unknown';
    if (($aiUser['type'] ?? '') === 'mcp_endpoint') {
        birkly_mcp_audit_log($endpointId, $method, $toolName, $success);
    }

    return [$result, $error];
}

/**
 * Ensure a bearer token issued for an MCP endpoint matches the agent in the URL path.
 */
function birkly_mcp_actor_matches_path_agent(array $aiUser, ?string $pathAgentId): bool
{
    if ($pathAgentId === null) {
        return true;
    }

    require_once __DIR__ . '/mcp_oauth.php';
    require_once __DIR__ . '/ai_agents.php';

    birkly_ai_agents_ensure_personal();

    $tokenEndpointId = (string) ($aiUser['id'] ?? '');
    if ($tokenEndpointId === '') {
        return false;
    }

    $expectedEndpoint = birkly_mcp_oauth_endpoint_for_agent_id($pathAgentId);
    if ($expectedEndpoint && ($expectedEndpoint['id'] ?? '') === $tokenEndpointId) {
        return true;
    }

    $agent = birkly_ai_agents_find_by_endpoint_id($tokenEndpointId);
    return $agent !== null && ($agent['id'] ?? '') === $pathAgentId;
}

function birkly_mcp_is_notification(array $input): bool
{
    return array_key_exists('method', $input) && !array_key_exists('id', $input);
}

function birkly_mcp_validate_origin(): bool
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') {
        return true;
    }

    $allowed = [
        'https://claude.ai',
        'https://chatgpt.com',
        'https://chat.openai.com',
    ];

    if (in_array($origin, $allowed, true)) {
        return true;
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') {
        return false;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $selfOrigin = $scheme . '://' . $host;
    return $origin === $selfOrigin;
}
