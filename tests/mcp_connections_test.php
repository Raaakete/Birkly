<?php
/**
 * MCP Connections tools tests (Phase 65 M12).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/birkly_paths.php';

$testRoot = sys_get_temp_dir() . '/birkly-mcp-conn-test-' . uniqid();
mkdir($testRoot, 0755, true);
mkdir($testRoot . '/settings', 0755, true);
mkdir($testRoot . '/logs', 0755, true);

$GLOBALS['__birkly_project_root'] = $testRoot;
putenv('BIRKLY_SECRETS_KEY=' . bin2hex(random_bytes(32)));

require_once dirname(__DIR__) . '/core/ai_tool_registry.php';

$failures = 0;

function assert_true(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '  ' . $detail . "\n";
        }
    }
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
    }
    rmdir($dir);
}

$adminUser = [
    'id' => 'ai_user_1',
    'type' => 'mcp_endpoint',
    'role' => 'admin',
    'require_approval' => false,
    'name' => 'Admin',
];

$viewerUser = [
    'id' => 'ai_user_2',
    'type' => 'mcp_endpoint',
    'role' => 'viewer',
    'require_approval' => false,
    'name' => 'Viewer',
];

// Health on empty registry.
$result = birkly_ai_dispatch_tool_call('connections_health', [], $adminUser, 'mcp');
$text = $result['content'][0]['text'] ?? '';
$data = json_decode($text, true);
assert_true('health returns ok', is_array($data) && ($data['status'] ?? '') === 'ok');
assert_true('health summary has total', is_array($data['summary'] ?? null) && ($data['summary']['total_connections'] ?? null) === 0);

// Create connection.
$result = birkly_ai_dispatch_tool_call('connections_configure', [
    'operation' => 'create',
    'connection' => [
        'type' => 'email',
        'provider' => 'log',
        'alias' => 'mcp-log',
        'name' => 'MCP Log Connection',
        'from_email' => 'test@example.com',
    ],
], $adminUser, 'mcp');
$text = $result['content'][0]['text'] ?? '';
$data = json_decode($text, true);
assert_true('configure create returns ok', is_array($data) && ($data['status'] ?? '') === 'ok');
assert_true('create response masks secrets', isset($data['connection']['secrets']['_masked']));
assert_true('create response has no secrets_ciphertext', !isset($data['connection']['secrets_ciphertext']));

// Test connection.
$result = birkly_ai_dispatch_tool_call('connections_test', [
    'alias' => 'mcp-log',
    'test_email' => 'test@example.com',
], $adminUser, 'mcp');
$text = $result['content'][0]['text'] ?? '';
$data = json_decode($text, true);
assert_true('test returns ok', is_array($data) && ($data['status'] ?? '') === 'ok');
assert_true('test result status honest', in_array($data['send_result']['status'] ?? '', ['logged', 'accepted'], true));
assert_true('test response does not echo secrets', strpos($text, 'secret') === false && strpos($text, 'password') === false);

// Health now shows one connection.
$result = birkly_ai_dispatch_tool_call('connections_health', [], $adminUser, 'mcp');
$text = $result['content'][0]['text'] ?? '';
$data = json_decode($text, true);
assert_true('health shows one connection', ($data['summary']['total_connections'] ?? 0) === 1);

// Update connection.
$result = birkly_ai_dispatch_tool_call('connections_configure', [
    'operation' => 'update',
    'connection' => [
        'alias' => 'mcp-log',
        'name' => 'Renamed MCP Log',
    ],
], $adminUser, 'mcp');
$text = $result['content'][0]['text'] ?? '';
$data = json_decode($text, true);
assert_true('configure update returns ok', is_array($data) && ($data['status'] ?? '') === 'ok');
assert_true('updated name stored', ($data['connection']['name'] ?? '') === 'Renamed MCP Log');

// Delete connection.
$result = birkly_ai_dispatch_tool_call('connections_configure', [
    'operation' => 'delete',
    'connection' => ['alias' => 'mcp-log'],
], $adminUser, 'mcp');
$text = $result['content'][0]['text'] ?? '';
$data = json_decode($text, true);
assert_true('configure delete returns ok', is_array($data) && ($data['status'] ?? '') === 'ok');

// Approval gating for admin chat source.
$approvingUser = $adminUser;
$approvingUser['require_approval'] = true;
$result = birkly_ai_dispatch_tool_call('connections_configure', [
    'operation' => 'create',
    'connection' => [
        'type' => 'email',
        'provider' => 'log',
        'alias' => 'mcp-log2',
        'name' => 'Gated',
    ],
], $approvingUser, 'chat');
$text = $result['content'][0]['text'] ?? '';
assert_true('approval-gated tool returns pending message', strpos($text, 'approval ID') !== false);

// Permission denied for viewer.
$result = birkly_ai_dispatch_tool_call('connections_health', [], $viewerUser, 'mcp');
$text = $result['content'][0]['text'] ?? '';
assert_true('viewer denied connections_health', $result['isError'] === true && strpos($text, 'Permission denied') !== false);

rrmdir($testRoot);

echo "\nmcp_connections_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
