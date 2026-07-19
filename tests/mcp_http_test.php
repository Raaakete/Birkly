<?php
/**
 * P4-M6 — MCP HTTP integration: create endpoint → tools/list via mcp_server.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/http_test_helpers.php';
require_once dirname(__DIR__) . '/core/mcp_endpoints.php';

test_reset_failures();
echo "MCP HTTP integration tests\n\n";

$root = dirname(__DIR__);
$port = (int) (getenv('MCP_HTTP_TEST_PORT') ?: 18085);
$settingsFile = $root . '/settings/mcp_endpoints.json';
$backupSettings = $settingsFile . '.bak.' . getmypid();

$serverPid = birkly_test_http_server_start($root, $port);
$port = birkly_test_http_server_port();
$base = "http://127.0.0.1:$port";

try {
    if (is_file($settingsFile)) {
        copy($settingsFile, $backupSettings);
    } else {
        @unlink($backupSettings);
    }

    $endpoint = birkly_mcp_create_endpoint([
        'name' => 'HTTP Test Endpoint',
        'role' => 'admin',
        'require_approval' => true,
    ], 'test');

    $apiKey = $endpoint['mcp_api_key'];

    $init = birkly_test_http_request($base . '/api/mcp_server.php', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json', 'X-MCP-API-Key: ' . $apiKey],
        'body' => json_encode(['jsonrpc' => '2.0', 'method' => 'initialize', 'id' => 1]),
    ]);
    $initData = birkly_test_json_decode($init['body']);
    test_assert('initialize HTTP 200', $init['code'] === 200);
    test_assert('initialize returns serverInfo', !empty($initData['result']['serverInfo']['name']));

    $tools = birkly_test_http_request($base . '/api/mcp_server.php', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json', 'X-MCP-API-Key: ' . $apiKey],
        'body' => json_encode(['jsonrpc' => '2.0', 'method' => 'tools/list', 'id' => 2]),
    ]);
    $toolsData = birkly_test_json_decode($tools['body']);
    test_assert('tools/list HTTP 200', $tools['code'] === 200);
    test_assert('tools/list returns array', is_array($toolsData['result']['tools'] ?? null));
    test_assert('list_collections tool available', (function ($tools) {
        foreach ($tools as $t) {
            if (($t['name'] ?? '') === 'list_collections') {
                return true;
            }
        }
        return false;
    })($toolsData['result']['tools'] ?? []));

    $bad = birkly_test_http_request($base . '/api/mcp_server.php', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json', 'X-MCP-API-Key: invalid'],
        'body' => json_encode(['jsonrpc' => '2.0', 'method' => 'tools/list', 'id' => 3]),
    ]);
    test_assert('invalid key rejected', $bad['code'] === 401);

    birkly_mcp_revoke_endpoint($endpoint['id']);
} finally {
    if (is_file($backupSettings)) {
        copy($backupSettings, $settingsFile);
        unlink($backupSettings);
    } elseif (is_file($settingsFile)) {
        unlink($settingsFile);
    }
    birkly_test_http_server_stop($serverPid);
}

exit(test_finish('mcp_http_test'));
