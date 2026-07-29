<?php
/**
 * MCP (Model Context Protocol) Server — legacy HTTP JSON-RPC shim.
 * Canonical connector: POST /mcp (Streamable HTTP, P5-M2).
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
require_once __DIR__ . '/../core/mcp_protocol.php';
require_once __DIR__ . '/../core/mcp_endpoints.php';
require_once __DIR__ . '/../core/mcp_oauth.php';

$input = json_decode(file_get_contents('php://input'), true);
$method = $input['method'] ?? $_GET['method'] ?? '';

$apiKey = $_SERVER['HTTP_X_MCP_API_KEY'] ?? $_GET['api_key'] ?? '';
$bearerToken = birkly_mcp_oauth_extract_bearer();

if ($apiKey === '' && $bearerToken === '') {
    http_response_code(401);
    header('WWW-Authenticate: ' . birkly_mcp_oauth_www_authenticate_header());
    echo json_encode([
        'jsonrpc' => '2.0',
        'id' => $input['id'] ?? null,
        'error' => ['code' => -32000, 'message' => 'Authentication required'],
    ]);
    exit;
}

$aiUser = birkly_mcp_resolve_actor_with_auth($apiKey, $bearerToken);
$endpointId = $aiUser['id'] ?? 'unknown';

if (!$aiUser) {
    if ($apiKey !== '') {
        birkly_mcp_record_failed_auth($apiKey);
    }
    http_response_code(401);
    echo json_encode([
        'jsonrpc' => '2.0',
        'id' => $input['id'] ?? null,
        'error' => ['code' => -32000, 'message' => 'Invalid credentials'],
    ]);
    exit;
}

if (($aiUser['type'] ?? '') === 'mcp_endpoint' && !birkly_mcp_check_rate_limit($endpointId)) {
    http_response_code(429);
    echo json_encode([
        'jsonrpc' => '2.0',
        'id' => $input['id'] ?? null,
        'error' => ['code' => -32000, 'message' => 'Rate limit exceeded'],
    ]);
    exit;
}

if (($aiUser['type'] ?? '') === 'mcp_endpoint') {
    birkly_mcp_reset_failed_auth($endpointId);
}

[$result, $error] = birkly_mcp_dispatch_jsonrpc($input ?? [], $aiUser);

echo json_encode(
    birkly_mcp_build_jsonrpc_response($input ?? [], $result, $error),
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
