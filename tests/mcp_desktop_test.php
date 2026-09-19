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

$credPath = $testRoot . '/Birkly-main 6/data/mcp_desktop/credentials_test.json';
$cmd = birkly_mcp_desktop_terminal_install_command($credPath, 'claude');
$installScript = birkly_mcp_desktop_install_script_path();
assert_true(
    'terminal command uses absolute install script path',
    str_contains($cmd, 'birkly-mcp-desktop-install.php')
        && (str_contains($cmd, $installScript) || str_contains($cmd, str_replace('\\', '/', $installScript))),
    $cmd
);
assert_true(
    'terminal command uses --target=claude without spaced equals',
    str_contains($cmd, '--target=claude') && !str_contains($cmd, '--target= claude'),
    $cmd
);
if (PHP_OS_FAMILY === 'Windows') {
    assert_true(
        'windows terminal command uses cmd /c for PowerShell compatibility',
        str_starts_with($cmd, 'cmd /c '),
        $cmd
    );
    assert_true(
        'windows terminal command quotes paths with spaces',
        str_contains($cmd, 'Birkly-main 6'),
        $cmd
    );
} else {
    assert_true(
        'terminal command quotes paths with spaces',
        str_contains($cmd, 'Birkly-main 6'),
        $cmd
    );
}

$manifestPath = dirname(__DIR__) . '/scripts/cms-release-manifest.json';
$manifest = json_decode((string) file_get_contents($manifestPath), true);
$manifestExcludes = is_array($manifest) ? ($manifest['excludes'] ?? []) : [];
assert_true(
    'release manifest ships mcp desktop install script',
    in_array('!scripts/birkly-mcp-desktop-install.php', $manifestExcludes, true)
);
assert_true(
    'release manifest ships mcp desktop bridge script',
    in_array('!scripts/birkly-mcp-desktop.php', $manifestExcludes, true)
);

$winInner = birkly_mcp_desktop_windows_install_inner_line(
    'C:\\Users\\dbayr\\Desktop\\birkly-0.0.2\\runtime\\php\\php.exe',
    'C:\\Users\\dbayr\\Desktop\\birkly-0.0.2\\scripts\\birkly-mcp-desktop-install.php',
    'claude',
    'C:/Users/dbayr/Desktop/birkly-0.0.2/data/mcp_desktop/credentials_test.json'
);
$winCmd = birkly_mcp_desktop_windows_terminal_cmd_string($winInner);
assert_true(
    'windows inner uses separate --token-file argument',
    str_contains($winInner, '--token-file "C:/Users/dbayr/Desktop/birkly-0.0.2/data/mcp_desktop/credentials_test.json"'),
    $winInner
);
assert_true(
    'windows inner avoids --token-file= with trailing space',
    !preg_match('/--token-file=\s/', $winInner),
    $winInner
);
assert_true(
    'windows terminal wrapper avoids PHP escapeshellarg',
    str_starts_with($winCmd, 'cmd /c "') && !str_contains($winCmd, 'cmd /c " '),
    $winCmd
);

$installScriptPath = dirname(__DIR__) . '/scripts/birkly-mcp-desktop-install.php';
$parseProbe = PHP_BINARY . ' ' . escapeshellarg($installScriptPath)
    . ' --target=claude --token-file= ' . escapeshellarg($path) . ' 2>&1';
$parseOut = shell_exec($parseProbe);
assert_true(
    'install script accepts --token-file= with path as next argv',
    is_string($parseOut) && !str_contains($parseOut, 'Missing credentials'),
    (string) $parseOut
);

$resolvedRel = birkly_mcp_desktop_resolve_credentials_path('data/mcp_desktop/' . basename($path));
assert_true(
    'resolve relative credentials path',
    $resolvedRel !== '' && is_file($resolvedRel),
    $resolvedRel
);

$snippetAbs = birkly_mcp_desktop_client_config_snippet($credPath);
$tokenArg = $snippetAbs['mcpServers']['birkly']['args'][2] ?? '';
assert_true(
    'config uses absolute token path',
    is_string($tokenArg) && $tokenArg !== '' && (str_contains($tokenArg, 'credentials_test.json') && !str_starts_with($tokenArg, 'data/')),
    (string) $tokenArg
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
$macScript = birkly_mcp_desktop_mac_command_script($path, 'claude');
assert_true('mac helper mentions Claude', str_contains($macScript, 'Claude Desktop'));
assert_true('mac helper runs install script', str_contains($macScript, 'birkly-mcp-desktop-install.php'));
$winScript = birkly_mcp_desktop_windows_bat_script($path, 'cursor');
assert_true('win helper mentions Cursor', str_contains($winScript, 'Cursor'));
assert_true('win helper runs install script', str_contains($winScript, 'birkly-mcp-desktop-install.php'));

$fakeHome = $testRoot . '/fakehome';
putenv('HOME=' . $fakeHome);

$copilotSnippet = birkly_mcp_desktop_client_config_snippet($path, 'copilot');
assert_true(
    'copilot snippet uses local type',
    ($copilotSnippet['mcpServers']['birkly']['type'] ?? '') === 'local'
        && ($copilotSnippet['mcpServers']['birkly']['tools'] ?? null) === ['*'],
    json_encode($copilotSnippet)
);
$copilotDir = $fakeHome . '/.copilot';
mkdir($copilotDir, 0755, true);
$copilotMerge = birkly_mcp_desktop_merge_into_app_config('copilot', $copilotSnippet);
assert_true('copilot merge ok', ($copilotMerge['ok'] ?? false) === true);
$copilotJson = json_decode((string) file_get_contents($copilotDir . '/mcp-config.json'), true);
assert_true('copilot merge wrote birkly server', is_array($copilotJson) && isset($copilotJson['mcpServers']['birkly']));

$vscodeSnippet = birkly_mcp_desktop_client_config_snippet($path, 'vscode');
assert_true(
    'vscode snippet uses servers + stdio',
    isset($vscodeSnippet['servers']['birkly']['type'])
        && ($vscodeSnippet['servers']['birkly']['type'] ?? '') === 'stdio',
    json_encode($vscodeSnippet)
);
$vscodeDir = $fakeHome . '/Library/Application Support/Code/User';
mkdir($vscodeDir, 0755, true);
$vscodeMerge = birkly_mcp_desktop_merge_into_app_config('vscode', $vscodeSnippet);
assert_true('vscode merge ok', ($vscodeMerge['ok'] ?? false) === true);
$vscodeJson = json_decode((string) file_get_contents($vscodeDir . '/mcp.json'), true);
assert_true('vscode merge wrote birkly server', is_array($vscodeJson) && isset($vscodeJson['servers']['birkly']));

$mergeDir = $fakeHome . '/Library/Application Support/Claude';
mkdir($mergeDir, 0755, true);
$merge = birkly_mcp_desktop_merge_into_app_config('claude', $snippet);
assert_true('merge install ok', ($merge['ok'] ?? false) === true);
assert_true('merge wrote file', is_file($mergeDir . '/claude_desktop_config.json'));

$remove = birkly_mcp_desktop_remove_from_app_config('claude');
assert_true('uninstall ok', ($remove['ok'] ?? false) === true && ($remove['removed'] ?? false) === true);
$mergedJson = json_decode((string) file_get_contents($mergeDir . '/claude_desktop_config.json'), true);
assert_true('uninstall removed birkly', is_array($mergedJson) && !isset($mergedJson['mcpServers']['birkly']));

$uninstallCmd = birkly_mcp_desktop_terminal_uninstall_command('claude');
assert_true('uninstall command mentions --uninstall', str_contains($uninstallCmd, '--uninstall'));

rrmdir($testRoot);

echo "\nmcp_desktop_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
