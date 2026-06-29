<?php
/**
 * P5-M2 — MCP Streamable HTTP (/mcp) tests.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/http_test_helpers.php';
require_once dirname(__DIR__) . '/core/mcp_endpoints.php';
require_once dirname(__DIR__) . '/core/mcp_oauth.php';
require_once dirname(__DIR__) . '/core/mcp_protocol.php';

test_reset_failures();
echo "MCP Streamable HTTP tests\n\n";

$root = dirname(__DIR__);
$port = (int) (getenv('MCP_STREAMABLE_TEST_PORT') ?: 18187);
$base = "http://127.0.0.1:$port";
$settingsFile = $root . '/settings/mcp_endpoints.json';
$oauthFile = $root . '/settings/mcp_oauth.json';
$backupSettings = $settingsFile . '.bak.' . getmypid();
$backupOAuth = $oauthFile . '.bak.' . getmypid();

$serverPid = birkly_test_http_server_start($root, $port);

try {
    if (is_file($settingsFile)) {
        copy($settingsFile, $backupSettings);
    }
    if (is_file($oauthFile)) {
        copy($oauthFile, $backupOAuth);
    }
    @unlink($oauthFile);

    $endpoint = birkly_mcp_create_endpoint([
        'name' => 'Streamable Test',
        'role' => 'viewer',
        'auth_mode' => 'api_key',
    ], 'test');
    $apiKey = $endpoint['mcp_api_key'];

    $get = birkly_test_http_request($base . '/mcp', ['method' => 'GET']);
    test_assert('GET /mcp returns 405', $get['code'] === 405);

    $unauth = birkly_test_http_request($base . '/mcp', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json', 'Accept: application/json, text/event-stream'],
        'body' => json_encode(['jsonrpc' => '2.0', 'method' => 'tools/list', 'id' => 1]),
    ]);
    test_assert('unauthenticated /mcp returns 401', $unauth['code'] === 401);
    test_assert(
        'WWW-Authenticate includes resource_metadata',
        str_contains($unauth['headers'] ?? '', 'resource_metadata=')
    );

    $init = birkly_test_http_request($base . '/mcp', [
        'method' => 'POST',
        'headers' => [
            'Content-Type: application/json',
            'Accept: application/json, text/event-stream',
            'X-MCP-API-Key: ' . $apiKey,
        ],
        'body' => json_encode(['jsonrpc' => '2.0', 'method' => 'initialize', 'id' => 1, 'params' => []]),
    ]);
    $initData = birkly_test_json_decode($init['body']);
    test_assert('initialize HTTP 200', $init['code'] === 200);
    test_assert('initialize returns protocolVersion', !empty($initData['result']['protocolVersion']));

    $tools = birkly_test_http_request($base . '/mcp', [
        'method' => 'POST',
        'headers' => [
            'Content-Type: application/json',
            'Accept: application/json, text/event-stream',
            'X-MCP-API-Key: ' . $apiKey,
        ],
        'body' => json_encode(['jsonrpc' => '2.0', 'method' => 'tools/list', 'id' => 2]),
    ]);
    $toolsData = birkly_test_json_decode($tools['body']);
    test_assert('tools/list HTTP 200', $tools['code'] === 200);
    test_assert('tools/list has tools array', isset($toolsData['result']['tools']));

    $pr = birkly_test_http_request($base . '/.well-known/oauth-protected-resource');
    $prData = birkly_test_json_decode($pr['body']);
    test_assert('protected resource points to /mcp', str_contains($prData['resource'] ?? '', '/mcp'));

} finally {
    birkly_test_http_server_stop($serverPid);
    if (is_file($backupSettings)) {
        copy($backupSettings, $settingsFile);
        unlink($backupSettings);
    }
    if (is_file($backupOAuth)) {
        copy($backupOAuth, $oauthFile);
        unlink($backupOAuth);
    } else {
        @unlink($oauthFile);
    }
}

exit(test_finish('mcp_streamable_test'));
