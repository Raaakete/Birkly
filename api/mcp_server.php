<?php
/**
 * MCP (Model Context Protocol) Server — P3-M5
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

header('Content-Type: application/json');

require_once 'storage.php';
require_once 'auth.php';
require_once 'ai_helpers.php';
require_once 'log.php';
require_once __DIR__ . '/../core/ai_tool_registry.php';

$input = json_decode(file_get_contents('php://input'), true);
$method = $input['method'] ?? $_GET['method'] ?? '';
$params = $input['params'] ?? [];

$apiKey = $_SERVER['HTTP_X_MCP_API_KEY'] ?? $_GET['api_key'] ?? '';

if (empty($apiKey)) {
    http_response_code(401);
    echo json_encode([
        'jsonrpc' => '2.0',
        'id' => $input['id'] ?? null,
        'error' => ['code' => -32000, 'message' => 'API key required'],
    ]);
    exit;
}

$aiUser = findAiUserByMcpKey($apiKey);

if (!$aiUser) {
    http_response_code(401);
    echo json_encode([
        'jsonrpc' => '2.0',
        'id' => $input['id'] ?? null,
        'error' => ['code' => -32000, 'message' => 'Invalid API key'],
    ]);
    exit;
}

$result = null;
$error = null;

switch ($method) {
    case 'initialize':
        $result = handleInitialize($aiUser);
        break;
    case 'tools/list':
        $result = handleToolsList($aiUser);
        break;
    case 'tools/call':
        $result = handleToolCall($params, $aiUser);
        break;
    case 'resources/list':
        $result = handleResourcesList();
        break;
    case 'resources/read':
        $result = handleResourceRead($params, $aiUser);
        break;
    default:
        $error = ['code' => -32601, 'message' => 'Method not found'];
}

echo json_encode([
    'jsonrpc' => '2.0',
    'id' => $input['id'] ?? null,
    'result' => $result,
    'error' => $error,
]);

function findAiUserByMcpKey(string $apiKey): ?array
{
    $usersStorage = new Storage('../users');
    foreach ($usersStorage->list('') as $userFile) {
        $user = $usersStorage->read($userFile);
        if ($user && ($user['type'] ?? '') === 'ai' && isset($user['mcp_api_key']) && hash_equals($user['mcp_api_key'], $apiKey)) {
            return $user;
        }
    }
    return null;
}

function handleInitialize(array $aiUser): array
{
    return [
        'protocolVersion' => '2024-11-05',
        'capabilities' => ['tools' => [], 'resources' => []],
        'serverInfo' => ['name' => 'Birkly MCP Server', 'version' => '1.0.0'],
    ];
}

function handleToolsList(array $aiUser): array
{
    return ['tools' => birkly_ai_tools_mcp_format($aiUser)];
}

function handleToolCall(array $params, array $aiUser): array
{
    $toolName = $params['name'] ?? '';
    $arguments = $params['arguments'] ?? [];
    if ($toolName === '') {
        return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Tool name is required']]];
    }
    return birkly_ai_dispatch_tool_call($toolName, $arguments, $aiUser, 'mcp');
}

function handleResourcesList(): array
{
    return [
        'resources' => [
            ['uri' => 'birkly://collections', 'name' => 'All Collections', 'description' => 'List of all content collections', 'mimeType' => 'application/json'],
            ['uri' => 'birkly://documentation', 'name' => 'Documentation', 'description' => 'Curated Birkly context pack', 'mimeType' => 'text/markdown'],
        ],
    ];
}

function handleResourceRead(array $params, array $aiUser): array
{
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
