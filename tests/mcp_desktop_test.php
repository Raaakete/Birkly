<?php
/**
 * Desktop MCP connector tests (P90).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/birkly_paths.php';

$testRoot = sys_get_temp_dir() . '/birkly-mcp-desktop-test-' . uniqid();
mkdir($testRoot, 0755, true);
mkdir($testRoot . '/data', 0755, true);
mkdir($testRoot . '/scripts', 0755, true);
file_put_contents($testRoot . '/scripts/birkly-mcp-desktop.php', "<?php\n");

$GLOBALS['__birkly_project_root'] = $testRoot;
putenv('BIRKLY_SECRETS_KEY=' . bin2hex(random_bytes(32)));

require_once dirname(__DIR__) . '/core/mcp_desktop.php';

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
    if (!is_dir($dir)) {
        return;
    }
    $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
    }
    rmdir($dir);
}

$userId = 'user_test_1';
$sample = [
    'version' => BIRKLY_MCP_DESKTOP_CREDENTIALS_VERSION,
    'user_id' => $userId,
    'mcp_url' => 'http://127.0.0.1:8080/mcp/agents/ai_agent_personal',
    'access_token' => 'at_test',
    'refresh_token' => 'rt_test',
    'expires_at' => time() + 3600,
    'paired_at' => date('c'),
];

assert_true('save credentials', birkly_mcp_desktop_save_credentials($userId, $sample));
$path = birkly_mcp_desktop_credentials_path($userId);
assert_true('credentials file exists', is_file($path));
assert_true('credentials mode 0600', (fileperms($path) & 0777) === 0600);

$loaded = birkly_mcp_desktop_load_credentials($path);
assert_true('load credentials round-trip', is_array($loaded) && ($loaded['access_token'] ?? '') === 'at_test');

$status = birkly_mcp_desktop_status_for_user($userId);
assert_true('status paired', ($status['paired'] ?? false) === true);
assert_true('status includes config', is_array($status['claude_desktop_config']['mcpServers']['birkly'] ?? null));

$cmd = birkly_mcp_desktop_terminal_install_command(
    $testRoot . '/Birkly-main 6/data/mcp_desktop/credentials_test.json',
    'claude'
);
assert_true(
    'terminal command quotes paths with spaces',
    str_contains($cmd, 'cd ') && str_contains($cmd, 'Birkly-main 6') && str_contains($cmd, "'"),
    $cmd
);

$tokenUrl = birkly_mcp_desktop_token_endpoint_from_mcp_url('http://127.0.0.1:8080/mcp/agents/ai_agent_personal');
assert_true(
    'token endpoint from mcp url',
    is_string($tokenUrl) && str_contains($tokenUrl, '127.0.0.1:8080') && str_contains($tokenUrl, '/oauth/token'),
    (string) $tokenUrl
);

assert_true(
    'should handle initialized notification locally',
    birkly_mcp_desktop_should_handle_stdio_locally(['method' => 'notifications/initialized'])
);

$broken = [
    'jsonrpc' => '2.0',
    'id' => 1,
    'result' => [
        'protocolVersion' => '2025-03-26',
        'capabilities' => ['tools' => [], 'resources' => []],
    ],
];
$fixed = birkly_mcp_desktop_encode_stdio_jsonrpc($broken);
assert_true(
    'stdio encode uses objects for capability maps',
    str_contains($fixed, '"resources":{}') && str_contains($fixed, '"tools":{}'),
    $fixed
);

$toolsList = [
    'jsonrpc' => '2.0',
    'id' => 2,
    'result' => [
        'tools' => [
            [
                'name' => 'list_collections',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
        ],
    ],
];
$toolsFixed = birkly_mcp_desktop_encode_stdio_jsonrpc($toolsList);
assert_true(
    'tools/list properties encode as object',
    str_contains($toolsFixed, '"properties":{}'),
    $toolsFixed
);

$sse = "event: message\ndata: {\"jsonrpc\":\"2.0\",\"id\":2,\"result\":{\"ok\":true}}\n\n";
$parsed = birkly_mcp_desktop_parse_http_jsonrpc_body($sse);
assert_true('parse sse jsonrpc', is_array($parsed) && ($parsed['id'] ?? null) === 2);

assert_true('revoke credentials', birkly_mcp_desktop_revoke_credentials($userId));
assert_true('status after revoke', (birkly_mcp_desktop_status_for_user($userId)['paired'] ?? true) === false);

$snippet = birkly_mcp_desktop_client_config_snippet($path);
$args = $snippet['mcpServers']['birkly']['args'] ?? [];
assert_true(
    'config uses separate --token-file arg',
    is_array($args)
        && ($args[1] ?? '') === '--token-file'
        && ($args[2] ?? '') === $path,
    json_encode($args)
);
$macScript = birkly_mcp_desktop_mac_command_script($snippet, 'claude');
assert_true('mac helper mentions Claude', str_contains($macScript, 'Claude Desktop'));
assert_true('mac helper merges birkly', str_contains($macScript, '"birkly"'));
$winScript = birkly_mcp_desktop_windows_bat_script($snippet, 'cursor');
assert_true('win helper mentions Cursor', str_contains($winScript, 'Cursor'));

$mergeDir = $testRoot . '/fakehome/Library/Application Support/Claude';
mkdir($mergeDir, 0755, true);
putenv('HOME=' . $testRoot . '/fakehome');
$merge = birkly_mcp_desktop_merge_into_app_config('claude', $snippet);
assert_true('merge install ok', ($merge['ok'] ?? false) === true);
assert_true('merge wrote file', is_file($mergeDir . '/claude_desktop_config.json'));

rrmdir($testRoot);

echo "\nmcp_desktop_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
