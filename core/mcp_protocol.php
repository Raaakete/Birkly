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
    return [
        'resources' => [
            ['uri' => 'birkly://collections', 'name' => 'All Collections', 'description' => 'List of all content collections', 'mimeType' => 'application/json'],
            ['uri' => 'birkly://documentation', 'name' => 'Documentation', 'description' => 'Curated Birkly context pack', 'mimeType' => 'text/markdown'],
        ],
    ];
}

function birkly_mcp_handle_resource_read(array $params, array $aiUser): array
{
    require_once __DIR__ . '/../api/ai_helpers.php';

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
