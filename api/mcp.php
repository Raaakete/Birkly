<?php
/**
 * MCP Streamable HTTP transport (P5-M2).
 * Canonical connector endpoint: POST /mcp
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ai_helpers.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/../core/mcp_protocol.php';
require_once __DIR__ . '/../core/mcp_endpoints.php';
require_once __DIR__ . '/../core/mcp_oauth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed. Use POST for MCP Streamable HTTP.']);
    exit;
}

if (!birkly_mcp_validate_origin()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'jsonrpc' => '2.0',
        'error' => ['code' => -32000, 'message' => 'Invalid Origin'],
    ]);
    exit;
}

$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
if ($accept !== '' && !str_contains($accept, 'application/json') && !str_contains($accept, 'text/event-stream')) {
    http_response_code(406);
    header('Content-Type: application/json');
    echo json_encode([
        'jsonrpc' => '2.0',
        'error' => ['code' => -32000, 'message' => 'Accept must include application/json and text/event-stream'],
    ]);
    exit;
}

$rawBody = file_get_contents('php://input');
$input = json_decode($rawBody ?: 'null', true);
if (!is_array($input)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'jsonrpc' => '2.0',
        'id' => null,
        'error' => ['code' => -32700, 'message' => 'Parse error'],
    ]);
    exit;
}

$apiKey = $_SERVER['HTTP_X_MCP_API_KEY'] ?? $_GET['api_key'] ?? '';
$bearerToken = birkly_mcp_oauth_extract_bearer();

if ($apiKey === '' && $bearerToken === '') {
    http_response_code(401);
    header('WWW-Authenticate: ' . birkly_mcp_oauth_www_authenticate_header());
    header('Content-Type: application/json');
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
    header('Content-Type: application/json');
    echo json_encode([
        'jsonrpc' => '2.0',
        'id' => $input['id'] ?? null,
        'error' => ['code' => -32000, 'message' => 'Invalid credentials'],
    ]);
    exit;
}

if (($aiUser['type'] ?? '') === 'mcp_endpoint' && !birkly_mcp_check_rate_limit($endpointId)) {
    http_response_code(429);
    header('Content-Type: application/json');
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

if (birkly_mcp_is_notification($input)) {
    http_response_code(202);
    exit;
}

[$result, $error] = birkly_mcp_dispatch_jsonrpc($input, $aiUser);

header('Content-Type: application/json');
echo json_encode([
    'jsonrpc' => '2.0',
    'id' => $input['id'] ?? null,
    'result' => $result,
    'error' => $error,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
