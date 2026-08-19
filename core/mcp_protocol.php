<?php
/**
 * Shared MCP JSON-RPC protocol handlers (P5-M2).
 * Used by legacy api/mcp_server.php and Streamable HTTP api/mcp.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/build_info.php';
require_once __DIR__ . '/ai_tool_registry.php';
require_once __DIR__ . '/ai_playbooks.php';
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
        'serverInfo' => ['name' => 'Birkly MCP Server', 'version' => BIRKLY_MCP_SERVER_VERSION],
        'instructions' => birkly_ai_mcp_initialize_instructions(),
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
        ['uri' => 'birkly://project', 'name' => 'Public site (project/)', 'description' => 'Paths, collections, and templating cheat sheet for AI site building', 'mimeType' => 'application/json'],
        ['uri' => 'birkly://brand-context', 'name' => 'Brand context (AI Presence)', 'description' => 'Published authoritative brand context for external AI systems', 'mimeType' => 'application/json'],
    ];
    $resources = array_merge($resources, birkly_ai_playbook_mcp_resources());

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

    if (preg_match('#^birkly://docs/([a-z0-9\-_]+)$#', $uri, $matches)) {
        $topic = readBirklyDocsTopic($matches[1]);
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
 * Build a JSON-RPC 2.0 response with either result or error — never both (Cursor/Zod rejects extra keys).
 */
function birkly_mcp_build_jsonrpc_response(array $input, ?array $result, ?array $error): array
{
    $response = [
        'jsonrpc' => '2.0',
        'id' => $input['id'] ?? null,
    ];

    if ($error !== null) {
        $response['error'] = $error;
    } else {
        $response['result'] = $result;
    }

    return $response;
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

/**
 * Whether a browser Origin header is permitted for MCP Streamable HTTP and OAuth CORS.
 *
 * Policy (not a vendor allowlist): OAuth + Bearer tokens gate real access. We accept
 * any HTTPS origin so Claude, ChatGPT, Cursor, and future MCP clients work without
 * per-vendor server updates. Native/desktop clients often send no Origin (allowed).
 * HTTP is limited to localhost for local dev; plain HTTP remote origins are rejected.
 */
function birkly_mcp_is_permitted_request_origin(string $origin): bool
{
    if ($origin === '') {
        return true;
    }

    $parsed = parse_url($origin);
    if (!is_array($parsed)) {
        return false;
    }

    $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
    $host = strtolower((string) ($parsed['host'] ?? ''));
    if ($host === '') {
        return false;
    }

    $requestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($requestHost !== '') {
        $normalizedRequestHost = preg_replace('/:\d+$/', '', $requestHost) ?? $requestHost;
        $normalizedOriginHost = preg_replace('/:\d+$/', '', $host) ?? $host;
        if ($normalizedOriginHost === $normalizedRequestHost) {
            return true;
        }
    }

    if ($scheme === 'https') {
        return true;
    }

    if ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1'], true)) {
        return true;
    }

    return false;
}

function birkly_mcp_validate_origin(): bool
{
    return birkly_mcp_is_permitted_request_origin((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
}

/**
 * GET on MCP endpoint — Streamable HTTP listen probe (not legacy HTTP+SSE redirect).
 *
 * Do not emit legacy `event: endpoint` here: that switches clients to deprecated
 * HTTP+SSE (responses on the GET stream). Birkly speaks Streamable HTTP (responses
 * on POST). A ping SSE stream is enough for health probes.
 */
function birkly_mcp_handle_get_request(): void
{
    if (!birkly_mcp_validate_origin()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid Origin']);
        return;
    }

    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    if ($accept !== '' && !str_contains($accept, 'text/event-stream')) {
        http_response_code(405);
        header('Allow: GET, POST');
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Use GET with Accept: text/event-stream or POST for MCP.']);
        return;
    }

    http_response_code(200);
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-transform');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');

    echo ': streamable-http' . "\n\n";
    if (function_exists('ob_flush')) {
        @ob_flush();
    }
    flush();
}

/**
 * Send a JSON-RPC payload as JSON or SSE depending on Accept (Streamable HTTP).
 */
function birkly_mcp_send_jsonrpc_response(array $payload, int $httpCode = 200): void
{
    http_response_code($httpCode);
    if (birkly_mcp_client_prefers_sse_response()) {
        birkly_mcp_emit_sse_jsonrpc($payload);
        return;
    }

    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * Emit one JSON-RPC payload as a Streamable HTTP SSE message (optional response shape).
 */
function birkly_mcp_emit_sse_jsonrpc(array $payload): void
{
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-transform');
    header('X-Accel-Buffering: no');
    echo 'data: ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
    if (function_exists('ob_flush')) {
        @ob_flush();
    }
    flush();
}

function birkly_mcp_client_prefers_sse_response(): bool
{
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    if ($accept === '') {
        return false;
    }
    $wantsStream = str_contains($accept, 'text/event-stream');
    $wantsJson = str_contains($accept, 'application/json');
    return $wantsStream && !$wantsJson;
}
