<?php
/**
 * Desktop MCP connector — local pairing + token file (Claude Desktop / Cursor on same machine).
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';

define('BIRKLY_MCP_DESKTOP_CLIENT_ID', 'birkly_desktop_connector');
define('BIRKLY_MCP_DESKTOP_CREDENTIALS_VERSION', 1);

function birkly_mcp_desktop_data_dir(): string
{
    return birkly_project_root() . '/data/mcp_desktop';
}

function birkly_mcp_desktop_credentials_path(string $userId): string
{
    $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $userId);
    if ($safe === '') {
        $safe = 'unknown';
    }

    return birkly_mcp_desktop_data_dir() . '/credentials_' . $safe . '.json';
}

function birkly_mcp_desktop_load_credentials(?string $path = null): ?array
{
    $path = $path ?? '';
    if ($path === '' && isset($_ENV['BIRKLY_DESKTOP_TOKEN_FILE'])) {
        $path = (string) $_ENV['BIRKLY_DESKTOP_TOKEN_FILE'];
    }
    if ($path === '' || !is_readable($path)) {
        return null;
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data) || (int) ($data['version'] ?? 0) !== BIRKLY_MCP_DESKTOP_CREDENTIALS_VERSION) {
        return null;
    }

    return $data;
}

/**
 * @param array<string, mixed> $credentials
 */
function birkly_mcp_desktop_save_credentials(string $userId, array $credentials): bool
{
    $dir = birkly_mcp_desktop_data_dir();
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        return false;
    }
    $path = birkly_mcp_desktop_credentials_path($userId);
    $json = json_encode($credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    $written = file_put_contents($path, $json . "\n", LOCK_EX);
    if ($written === false) {
        return false;
    }
    @chmod($path, 0600);

    return true;
}

function birkly_mcp_desktop_revoke_credentials(string $userId): bool
{
    $path = birkly_mcp_desktop_credentials_path($userId);
    if (!is_file($path)) {
        return true;
    }

    return @unlink($path);
}

/**
 * Issue OAuth tokens for the current admin user and write the desktop credentials file.
 *
 * @param array<string, mixed> $user Session user row
 * @return array<string, mixed>
 */
function birkly_mcp_desktop_pair_user(array $user, string $agentId = BIRKLY_AI_AGENT_PERSONAL_ID): array
{
    require_once __DIR__ . '/mcp_oauth.php';
    require_once __DIR__ . '/ai_agents.php';
    require_once __DIR__ . '/mcp_protocol.php';

    birkly_ai_agents_ensure_personal();
    $agent = birkly_ai_agents_find($agentId);
    if ($agent === null || ($agent['status'] ?? 'active') === 'revoked') {
        throw new InvalidArgumentException('AI agent not available');
    }

    $endpointId = (string) ($agent['mcp_endpoint_id'] ?? '');
    if ($endpointId === '') {
        throw new RuntimeException('Agent has no MCP endpoint');
    }

    $userId = (string) ($user['id'] ?? '');
    if ($userId === '') {
        throw new InvalidArgumentException('User id required');
    }

    $tokens = birkly_mcp_oauth_issue_tokens(
        BIRKLY_MCP_DESKTOP_CLIENT_ID,
        $endpointId,
        BIRKLY_MCP_OAUTH_SCOPE,
        $userId,
        (string) ($user['role'] ?? 'viewer'),
        (string) ($agent['type'] ?? 'personal')
    );

    $mcpUrl = birkly_ai_agent_connector_url($agentId);
    $bridgeScript = birkly_project_root() . '/scripts/birkly-mcp-desktop.php';
    $credPath = birkly_mcp_desktop_credentials_path($userId);

    $credentials = [
        'version' => BIRKLY_MCP_DESKTOP_CREDENTIALS_VERSION,
        'user_id' => $userId,
        'agent_id' => $agentId,
        'mcp_url' => $mcpUrl,
        'client_id' => BIRKLY_MCP_DESKTOP_CLIENT_ID,
        'access_token' => (string) ($tokens['access_token'] ?? ''),
        'refresh_token' => (string) ($tokens['refresh_token'] ?? ''),
        'expires_at' => time() + (int) ($tokens['expires_in'] ?? BIRKLY_MCP_OAUTH_ACCESS_TTL),
        'paired_at' => date('c'),
    ];

    if (!birkly_mcp_desktop_save_credentials($userId, $credentials)) {
        throw new RuntimeException('Could not write desktop credentials file');
    }

    return [
        'paired' => true,
        'credentials_path' => $credPath,
        'mcp_url' => $mcpUrl,
        'agent_id' => $agentId,
        'bridge_script' => $bridgeScript,
        'claude_desktop_config' => birkly_mcp_desktop_client_config_snippet($credPath),
    ];
}

/**
 * @param array<string, mixed> $credentials
 * @return array<string, mixed>|null Updated credentials or null on failure
 */
function birkly_mcp_desktop_refresh_credentials(array $credentials): ?array
{
    require_once __DIR__ . '/mcp_oauth.php';

    $refresh = (string) ($credentials['refresh_token'] ?? '');
    $clientId = (string) ($credentials['client_id'] ?? BIRKLY_MCP_DESKTOP_CLIENT_ID);
    if ($refresh === '') {
        return null;
    }

    $mcpUrl = (string) ($credentials['mcp_url'] ?? '');
    if ($mcpUrl === '') {
        return null;
    }

    $tokenUrl = birkly_mcp_desktop_token_endpoint_from_mcp_url($mcpUrl);
    if ($tokenUrl === null) {
        return null;
    }

    $body = http_build_query([
        'grant_type' => 'refresh_token',
        'refresh_token' => $refresh,
        'client_id' => $clientId,
    ]);

    $response = birkly_mcp_desktop_http_post($tokenUrl, $body, 'application/x-www-form-urlencoded', '');
    if ($response === null) {
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || empty($data['access_token'])) {
        return null;
    }

    $credentials['access_token'] = (string) $data['access_token'];
    $credentials['refresh_token'] = (string) ($data['refresh_token'] ?? $refresh);
    $credentials['expires_at'] = time() + (int) ($data['expires_in'] ?? BIRKLY_MCP_OAUTH_ACCESS_TTL);

    $userId = (string) ($credentials['user_id'] ?? '');
    if ($userId !== '') {
        birkly_mcp_desktop_save_credentials($userId, $credentials);
    }

    return $credentials;
}

function birkly_mcp_desktop_token_endpoint_from_mcp_url(string $mcpUrl): ?string
{
    $parts = parse_url($mcpUrl);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return null;
    }
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    $basePath = rtrim((string) ($parts['path'] ?? ''), '/');
    if (str_ends_with($basePath, '/mcp/agents/ai_agent_personal')) {
        $basePath = substr($basePath, 0, -strlen('/mcp/agents/ai_agent_personal'));
    } elseif (preg_match('#/mcp/agents/[^/]+$#', $basePath)) {
        $basePath = preg_replace('#/mcp/agents/[^/]+$#', '', $basePath) ?? $basePath;
    } elseif (str_ends_with($basePath, '/mcp')) {
        $basePath = substr($basePath, 0, -strlen('/mcp'));
    }
    require_once __DIR__ . '/birkly_paths.php';
    $oauthPath = birkly_url('/oauth/token');

    return $parts['scheme'] . '://' . $parts['host'] . $port . $oauthPath;
}

/**
 * POST JSON-RPC to Streamable HTTP MCP.
 *
 * @param array<string, mixed> $message
 * @return array<string, mixed>|null Decoded JSON-RPC response
 */
function birkly_mcp_desktop_proxy_jsonrpc(array $credentials, array $message): ?array
{
    $mcpUrl = (string) ($credentials['mcp_url'] ?? '');
    $token = (string) ($credentials['access_token'] ?? '');
    if ($mcpUrl === '' || $token === '') {
        return null;
    }

    if (($credentials['expires_at'] ?? 0) < time() + 30) {
        $refreshed = birkly_mcp_desktop_refresh_credentials($credentials);
        if ($refreshed !== null) {
            $credentials = $refreshed;
            $token = (string) $credentials['access_token'];
        }
    }

    $payload = json_encode($message);
    if ($payload === false) {
        return null;
    }

    $raw = birkly_mcp_desktop_http_post(
        $mcpUrl,
        $payload,
        'application/json',
        'Bearer ' . $token,
        'application/json, text/event-stream'
    );
    if ($raw === null) {
        return null;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function birkly_mcp_desktop_http_post(
    string $url,
    string $body,
    string $contentType,
    string $authorization,
    string $accept = 'application/json'
): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        $headers = [
            'Content-Type: ' . $contentType,
            'Accept: ' . $accept,
        ];
        if ($authorization !== '') {
            $headers[] = 'Authorization: ' . $authorization;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
        ]);
        $result = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($result === false || $code >= 500) {
            return null;
        }

        return (string) $result;
    }

    $headerLines = "Content-Type: {$contentType}\r\nAccept: {$accept}\r\n";
    if ($authorization !== '') {
        $headerLines .= 'Authorization: ' . $authorization . "\r\n";
    }
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => $headerLines,
            'content' => $body,
            'timeout' => 120,
            'ignore_errors' => true,
        ],
    ]);
    $result = @file_get_contents($url, false, $context);
    if ($result === false) {
        return null;
    }

    return $result;
}

function birkly_mcp_desktop_should_handle_stdio_locally(array $message): bool
{
    $method = (string) ($message['method'] ?? '');
    if ($method === 'notifications/initialized') {
        return true;
    }
    if (str_starts_with($method, 'notifications/')) {
        return true;
    }

    return false;
}

function birkly_mcp_desktop_stdio_loop(?string $tokenFile = null): int
{
    $credentials = birkly_mcp_desktop_load_credentials($tokenFile);
    if ($credentials === null) {
        fwrite(STDERR, "Birkly desktop connector: missing or invalid token file. Pair again in Settings → AI.\n");
        return 1;
    }

    $stdin = fopen('php://stdin', 'r');
    if ($stdin === false) {
        return 1;
    }

    while (($line = fgets($stdin)) !== false) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $message = json_decode($line, true);
        if (!is_array($message)) {
            continue;
        }
        if (birkly_mcp_desktop_should_handle_stdio_locally($message)) {
            continue;
        }

        $response = birkly_mcp_desktop_proxy_jsonrpc($credentials, $message);
        if ($response === null) {
            $response = [
                'jsonrpc' => '2.0',
                'id' => $message['id'] ?? null,
                'error' => ['code' => -32603, 'message' => 'Could not reach Birkly MCP. Check that Birkly is running and re-pair in Settings → AI.'],
            ];
        }
        fwrite(STDOUT, json_encode($response, JSON_UNESCAPED_SLASHES) . "\n");
    }

    return 0;
}

/**
 * @return array<string, mixed>|null Claude Desktop / Cursor stdio config (no secrets in JSON).
 */
function birkly_mcp_desktop_client_config_snippet(string $credentialsPath): array
{
    $phpBinary = PHP_BINARY !== '' ? PHP_BINARY : 'php';
    $bridgeScript = birkly_project_root() . '/scripts/birkly-mcp-desktop.php';

    return [
        'mcpServers' => [
            'birkly' => [
                'command' => $phpBinary,
                'args' => [
                    $bridgeScript,
                    '--token-file=' . $credentialsPath,
                ],
            ],
        ],
    ];
}

/**
 * @return array{app_label: string, mac_path: string, win_path: string}
 */
function birkly_mcp_desktop_install_target_meta(string $target): array
{
    if ($target === 'cursor') {
        return [
            'app_label' => 'Cursor',
            'mac_path' => '~/.cursor/mcp.json',
            'win_path' => '%USERPROFILE%\\.cursor\\mcp.json',
        ];
    }

    return [
        'app_label' => 'Claude Desktop',
        'mac_path' => '~/Library/Application Support/Claude/claude_desktop_config.json',
        'win_path' => '%APPDATA%\\Claude\\claude_desktop_config.json',
    ];
}

/**
 * macOS double-click helper (.command) — merges MCP config using python3 (preinstalled on macOS).
 *
 * @param array<string, mixed> $mcpConfig
 */
function birkly_mcp_desktop_mac_command_script(array $mcpConfig, string $target = 'claude'): string
{
    $meta = birkly_mcp_desktop_install_target_meta($target);
    $json = json_encode($mcpConfig, JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Could not encode MCP config');
    }
    $pyConfig = str_replace('\\', '\\\\', $json);
    $pyConfig = str_replace("'", "\\'", $pyConfig);

    if ($target === 'cursor') {
        $pathExpr = "pathlib.Path.home() / '.cursor' / 'mcp.json'";
        $mkdirExpr = "(pathlib.Path.home() / '.cursor').mkdir(parents=True, exist_ok=True)";
    } else {
        $pathExpr = "pathlib.Path.home() / 'Library' / 'Application Support' / 'Claude' / 'claude_desktop_config.json'";
        $mkdirExpr = "path.parent.mkdir(parents=True, exist_ok=True)";
    }

    return <<<BASH
#!/bin/bash
set -e
echo "Birkly — connecting {$meta['app_label']} on this Mac..."
if ! command -v python3 >/dev/null 2>&1; then
  echo "Python 3 is required (usually preinstalled on macOS). Install Xcode Command Line Tools if needed."
  read -r -p "Press Enter to close."
  exit 1
fi
python3 <<'PY'
import json, pathlib
config = json.loads('{$pyConfig}')
path = {$pathExpr}
{$mkdirExpr}
data = {}
if path.exists():
    try:
        data = json.loads(path.read_text(encoding='utf-8'))
    except json.JSONDecodeError:
        data = {}
if not isinstance(data, dict):
    data = {}
servers = data.get('mcpServers')
if not isinstance(servers, dict):
    servers = {}
servers.update(config.get('mcpServers', {}))
data['mcpServers'] = servers
path.write_text(json.dumps(data, indent=2) + "\\n", encoding='utf-8')
print("Saved to", path)
print("Fully quit {$meta['app_label']}, then open it again.")
PY
read -r -p "Press Enter to close."
BASH;
}

/**
 * Windows helper (.bat) — runs PowerShell to merge MCP config.
 *
 * @param array<string, mixed> $mcpConfig
 */
function birkly_mcp_desktop_windows_bat_script(array $mcpConfig, string $target = 'claude'): string
{
    $meta = birkly_mcp_desktop_install_target_meta($target);
    $json = json_encode($mcpConfig, JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Could not encode MCP config');
    }
    $b64 = base64_encode($json);

    if ($target === 'cursor') {
        $pathPs = 'Join-Path $env:USERPROFILE ".cursor\\mcp.json"';
        $dirPs = 'Join-Path $env:USERPROFILE ".cursor"';
    } else {
        $pathPs = 'Join-Path $env:APPDATA "Claude\\claude_desktop_config.json"';
        $dirPs = 'Join-Path $env:APPDATA "Claude"';
    }

    $appLabel = $meta['app_label'];

    return "@echo off\r\n"
        . "title Birkly — {$appLabel}\r\n"
        . "echo Connecting {$appLabel} on this PC...\r\n"
        . "powershell -NoProfile -ExecutionPolicy Bypass -Command \""
        . "\$json = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String('{$b64}')); "
        . "\$config = \$json | ConvertFrom-Json; "
        . "\$dir = {$dirPs}; "
        . "New-Item -ItemType Directory -Force -Path \$dir | Out-Null; "
        . "\$path = {$pathPs}; "
        . "\$data = @{}; "
        . "if (Test-Path \$path) { try { \$data = Get-Content \$path -Raw | ConvertFrom-Json } catch {} }; "
        . "if (-not \$data.mcpServers) { \$data | Add-Member -NotePropertyName mcpServers -NotePropertyValue (@{}) -Force }; "
        . "foreach (\$p in \$config.mcpServers.PSObject.Properties) { \$data.mcpServers | Add-Member -NotePropertyName \$p.Name -NotePropertyValue \$p.Value -Force }; "
        . "\$data | ConvertTo-Json -Depth 10 | Set-Content -Path \$path -Encoding UTF8; "
        . "Write-Host 'Saved to' \$path; "
        . "Write-Host 'Fully quit {$appLabel}, then open it again.'\"\r\n"
        . "pause\r\n";
}

/**
 * Absolute path to Claude / Cursor MCP config on this machine (CLI install).
 */
function birkly_mcp_desktop_app_config_path(string $target): ?string
{
    if ($target !== 'claude' && $target !== 'cursor') {
        return null;
    }

    $home = (string) (getenv('HOME') ?: '');
    if ($home === '' && PHP_OS_FAMILY === 'Windows') {
        $home = (string) (getenv('USERPROFILE') ?: '');
    }
    if ($home === '') {
        return null;
    }

    if ($target === 'cursor') {
        return $home . '/.cursor/mcp.json';
    }

    return $home . '/Library/Application Support/Claude/claude_desktop_config.json';
}

/**
 * @param array<string, mixed> $mcpConfig
 * @return array{ok: bool, path?: string, message?: string}
 */
function birkly_mcp_desktop_merge_into_app_config(string $target, array $mcpConfig): array
{
    $path = birkly_mcp_desktop_app_config_path($target);
    if ($path === null) {
        return ['ok' => false, 'message' => 'Could not resolve config path on this computer'];
    }

    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        return ['ok' => false, 'message' => 'Could not create config folder'];
    }

    $data = [];
    if (is_file($path)) {
        $decoded = json_decode((string) file_get_contents($path), true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }

    $servers = $data['mcpServers'] ?? [];
    if (!is_array($servers)) {
        $servers = [];
    }
    $incoming = $mcpConfig['mcpServers'] ?? [];
    if (is_array($incoming)) {
        foreach ($incoming as $name => $entry) {
            $servers[$name] = $entry;
        }
    }
    $data['mcpServers'] = $servers;

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return ['ok' => false, 'message' => 'Could not encode config'];
    }
    if (file_put_contents($path, $json . "\n", LOCK_EX) === false) {
        return ['ok' => false, 'message' => 'Could not write config file'];
    }

    return ['ok' => true, 'path' => $path];
}

function birkly_mcp_desktop_terminal_install_command(string $credentialsPath, string $target): string
{
    $php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
    $script = birkly_project_root() . '/scripts/birkly-mcp-desktop-install.php';

    return $php . ' ' . $script
        . ' --target=' . $target
        . ' --token-file=' . $credentialsPath;
}

/**
 * @return array<string, string>
 */
function birkly_mcp_desktop_helper_download_names(): array
{
    return [
        'claude_mac' => 'Connect-Birkly-to-Claude.command',
        'claude_win' => 'Connect-Birkly-to-Claude.bat',
        'cursor_mac' => 'Connect-Birkly-to-Cursor.command',
        'cursor_win' => 'Connect-Birkly-to-Cursor.bat',
    ];
}

function birkly_mcp_desktop_status_for_user(string $userId): array
{
    $path = birkly_mcp_desktop_credentials_path($userId);
    if (!is_file($path)) {
        return ['paired' => false];
    }
    $cred = birkly_mcp_desktop_load_credentials($path);
    if ($cred === null) {
        return ['paired' => false, 'invalid' => true];
    }

    $snippet = birkly_mcp_desktop_client_config_snippet($path);

    return [
        'paired' => true,
        'paired_at' => (string) ($cred['paired_at'] ?? ''),
        'credentials_path' => $path,
        'mcp_url' => (string) ($cred['mcp_url'] ?? ''),
        'expires_at' => (int) ($cred['expires_at'] ?? 0),
        'claude_desktop_config' => $snippet,
        'terminal_install' => [
            'claude' => birkly_mcp_desktop_terminal_install_command($path, 'claude'),
            'cursor' => birkly_mcp_desktop_terminal_install_command($path, 'cursor'),
        ],
        'helper_downloads' => birkly_mcp_desktop_helper_download_names(),
    ];
}
