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
    require_once __DIR__ . '/mcp_oauth.php';
    birkly_mcp_oauth_revoke_client_sessions_for_user($userId, BIRKLY_MCP_DESKTOP_CLIENT_ID);

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

    $data = birkly_mcp_oauth_exchange_refresh_token($refresh, $clientId);
    if ($data === null) {
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

function birkly_mcp_desktop_bootstrap_runtime(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $ready = true;

    $root = birkly_project_root();
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    $logDir = $root . '/logs';
    if (is_dir($logDir) || @mkdir($logDir, 0755, true)) {
        ini_set('error_log', $logDir . '/php_errors.log');
    }

    require_once $root . '/api/storage.php';
    require_once __DIR__ . '/mcp_oauth.php';
    require_once __DIR__ . '/mcp_protocol.php';
}

/**
 * @param array<string, mixed> $credentials
 */
function birkly_mcp_desktop_ensure_fresh_credentials(array &$credentials): void
{
    if (($credentials['expires_at'] ?? 0) >= time() + 30) {
        return;
    }
    $refreshed = birkly_mcp_desktop_refresh_credentials($credentials);
    if ($refreshed !== null) {
        $credentials = $refreshed;
    }
}

/**
 * @param mixed $result
 * @return array<string, mixed>|null
 */
function birkly_mcp_desktop_normalize_dispatch_result(mixed $result): ?array
{
    if ($result === null) {
        return null;
    }
    if (is_array($result)) {
        return $result;
    }
    if ($result instanceof stdClass) {
        return (array) $result;
    }
    if (is_object($result)) {
        $decoded = json_decode(json_encode($result), true);
        return is_array($decoded) ? $decoded : null;
    }

    return null;
}

/**
 * Handle JSON-RPC in-process (no HTTP server required). Preferred for Claude Desktop stdio.
 *
 * @param array<string, mixed> $credentials
 * @param array<string, mixed> $message
 * @return array<string, mixed>|null JSON-RPC response envelope
 */
function birkly_mcp_desktop_dispatch_jsonrpc_inprocess(array &$credentials, array $message): ?array
{
    birkly_mcp_desktop_bootstrap_runtime();
    birkly_mcp_desktop_ensure_fresh_credentials($credentials);

    $token = (string) ($credentials['access_token'] ?? '');
    if ($token === '') {
        return null;
    }

    $aiUser = birkly_mcp_resolve_actor_with_auth('', $token);
    if ($aiUser === null) {
        return birkly_mcp_desktop_normalize_jsonrpc_for_stdio(birkly_mcp_build_jsonrpc_response(
            $message,
            null,
            ['code' => -32001, 'message' => 'Desktop MCP token expired or revoked. Re-pair in Admin → Settings → AI.']
        ));
    }

    try {
        [$result, $error] = birkly_mcp_dispatch_jsonrpc($message, $aiUser);
    } catch (Throwable $e) {
        error_log('Desktop MCP dispatch: ' . $e->getMessage());
        return birkly_mcp_desktop_normalize_jsonrpc_for_stdio(birkly_mcp_build_jsonrpc_response(
            $message,
            null,
            ['code' => -32603, 'message' => 'Birkly MCP tool error. See logs/php_errors.log on this machine.']
        ));
    }

    $result = birkly_mcp_desktop_normalize_dispatch_result($result);
    $response = birkly_mcp_build_jsonrpc_response($message, $result, $error);

    return birkly_mcp_desktop_normalize_jsonrpc_for_stdio($response);
}

/**
 * POST JSON-RPC to Streamable HTTP MCP (fallback when BIRKLY_DESKTOP_MCP_USE_HTTP=1).
 *
 * @param array<string, mixed> $credentials
 * @param array<string, mixed> $message
 * @return array<string, mixed>|null Decoded JSON-RPC response
 */
function birkly_mcp_desktop_proxy_jsonrpc(array &$credentials, array $message): ?array
{
    $mcpUrl = (string) ($credentials['mcp_url'] ?? '');
    $token = (string) ($credentials['access_token'] ?? '');
    if ($mcpUrl === '' || $token === '') {
        return null;
    }

    birkly_mcp_desktop_ensure_fresh_credentials($credentials);
    $token = (string) ($credentials['access_token'] ?? '');

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

    $decoded = birkly_mcp_desktop_parse_http_jsonrpc_body($raw);
    if ($decoded === null) {
        return null;
    }

    return birkly_mcp_desktop_normalize_jsonrpc_for_stdio($decoded);
}

/**
 * Parse JSON-RPC from Streamable HTTP (JSON body or SSE `data:` lines).
 *
 * @return array<string, mixed>|null
 */
function birkly_mcp_desktop_parse_http_jsonrpc_body(string $raw): ?array
{
    $trimmed = trim($raw);
    if ($trimmed === '') {
        return null;
    }

    if ($trimmed[0] === '{') {
        $decoded = json_decode($trimmed, true);
        return is_array($decoded) ? $decoded : null;
    }

    if (!str_contains($trimmed, 'data:')) {
        return null;
    }

    $last = null;
    foreach (preg_split('/\r\n|\n|\r/', $trimmed) as $line) {
        $line = trim($line);
        if (!str_starts_with($line, 'data:')) {
            continue;
        }
        $json = trim(substr($line, 5));
        if ($json === '' || $json === '[DONE]') {
            continue;
        }
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            $last = $decoded;
        }
    }

    return $last;
}

/**
 * PHP json_decode(true) turns JSON {} into []; Claude/Zod expect objects for schema maps.
 *
 * @param mixed $value
 * @return mixed
 */
function birkly_mcp_desktop_fixup_for_json_encode(mixed $value, ?string $parentKey = null): mixed
{
    if (!is_array($value)) {
        return $value;
    }

    if ($value === []) {
        if (in_array($parentKey, ['properties', 'patternProperties', 'tools', 'resources', 'prompts', 'logging', 'capabilities'], true)) {
            return new stdClass();
        }

        return $value;
    }

    if (array_is_list($value)) {
        $out = [];
        foreach ($value as $item) {
            $out[] = birkly_mcp_desktop_fixup_for_json_encode($item, null);
        }

        return $out;
    }

    $out = [];
    foreach ($value as $key => $item) {
        $out[$key] = birkly_mcp_desktop_fixup_for_json_encode($item, is_string($key) ? $key : null);
    }

    return $out;
}

/**
 * @param array<string, mixed> $response
 * @return array<string, mixed>
 */
function birkly_mcp_desktop_normalize_jsonrpc_for_stdio(array $response): array
{
    $fixed = birkly_mcp_desktop_fixup_for_json_encode($response, null);

    return is_array($fixed) ? $fixed : $response;
}

/**
 * @param array<string, mixed> $payload
 */
function birkly_mcp_desktop_encode_stdio_jsonrpc(array $payload): string
{
    $normalized = birkly_mcp_desktop_normalize_jsonrpc_for_stdio($payload);

    return json_encode($normalized, JSON_UNESCAPED_SLASHES) ?: '{}';
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

function birkly_mcp_desktop_attach_stdio_process_guards(): void
{
    static $attached = false;
    if ($attached) {
        return;
    }
    $attached = true;

    $root = birkly_project_root();
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    @ini_set('max_execution_time', '0');
    @set_time_limit(0);
    $logDir = $root . '/logs';
    if (is_dir($logDir) || @mkdir($logDir, 0755, true)) {
        ini_set('error_log', $logDir . '/php_errors.log');
    }

    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return true;
        }
        error_log("Birkly desktop MCP [$severity] $message in $file:$line");

        return true;
    });

    register_shutdown_function(static function (): void {
        $err = error_get_last();
        if ($err === null) {
            return;
        }
        if (!in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }
        fwrite(STDERR, 'Birkly desktop MCP fatal: ' . $err['message'] . "\n");
    });
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

/**
 * @param array<string, mixed> $credentials
 * @param array<string, mixed> $message
 * @return array<string, mixed>|null
 */
function birkly_mcp_desktop_handle_stdio_jsonrpc(array &$credentials, array $message): ?array
{
    if (getenv('BIRKLY_DESKTOP_MCP_USE_HTTP') === '1') {
        return birkly_mcp_desktop_proxy_jsonrpc($credentials, $message);
    }

    $response = birkly_mcp_desktop_dispatch_jsonrpc_inprocess($credentials, $message);
    if ($response !== null) {
        return $response;
    }

    return birkly_mcp_desktop_proxy_jsonrpc($credentials, $message);
}

/**
 * @param array<string, mixed> $credentials
 */
function birkly_mcp_desktop_process_stdio_line(array &$credentials, string $line): void
{
    $line = trim($line);
    if ($line === '') {
        return;
    }
    $message = json_decode($line, true);
    if (!is_array($message)) {
        return;
    }
    if (birkly_mcp_desktop_should_handle_stdio_locally($message)) {
        return;
    }

    if (!array_key_exists('id', $message)) {
        return;
    }

    try {
        $response = birkly_mcp_desktop_handle_stdio_jsonrpc($credentials, $message);
    } catch (Throwable $e) {
        error_log('Birkly desktop MCP request: ' . $e->getMessage());
        $response = [
            'jsonrpc' => '2.0',
            'id' => $message['id'] ?? null,
            'error' => ['code' => -32603, 'message' => 'Birkly MCP error. Re-pair in Admin → Settings → AI or check logs/php_errors.log.'],
        ];
    }
    if ($response === null) {
        $response = [
            'jsonrpc' => '2.0',
            'id' => $message['id'] ?? null,
            'error' => ['code' => -32603, 'message' => 'Could not run Birkly MCP. Re-pair in Admin → Settings → AI or check logs/php_errors.log.'],
        ];
    }
    fwrite(STDOUT, birkly_mcp_desktop_encode_stdio_jsonrpc($response) . "\n");
    fflush(STDOUT);
}

function birkly_mcp_desktop_stdio_loop(?string $tokenFile = null): int
{
    birkly_mcp_desktop_attach_stdio_process_guards();

    $credentials = birkly_mcp_desktop_load_credentials($tokenFile);
    if ($credentials === null) {
        fwrite(STDERR, "Birkly desktop connector: missing or invalid token file. Pair again in Settings → AI.\n");
        return 1;
    }

    $stdin = fopen('php://stdin', 'rb');
    if ($stdin === false) {
        return 1;
    }

    stream_set_blocking($stdin, false);
    $buffer = '';

    while (true) {
        @set_time_limit(0);

        $read = [$stdin];
        $write = null;
        $except = null;
        $ready = @stream_select($read, $write, $except, 25, 0);
        if ($ready === false) {
            break;
        }

        if ($ready === 0) {
            if (feof($stdin)) {
                break;
            }
            continue;
        }

        $chunk = fread($stdin, 65536);
        if ($chunk === false) {
            break;
        }
        if ($chunk === '') {
            if (feof($stdin)) {
                break;
            }
            continue;
        }

        $buffer .= $chunk;
        while (($pos = strpos($buffer, "\n")) !== false) {
            $line = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 1);
            birkly_mcp_desktop_process_stdio_line($credentials, $line);
        }
    }

    return 0;
}

/**
 * @return list<string>
 */
function birkly_mcp_desktop_valid_targets(): array
{
    return ['claude', 'cursor', 'vscode', 'gemini'];
}

function birkly_mcp_desktop_is_valid_target(string $target): bool
{
    return in_array($target, birkly_mcp_desktop_valid_targets(), true);
}

function birkly_mcp_desktop_php_binary(): string
{
    $root = birkly_project_root();
    if (PHP_OS_FAMILY === 'Windows') {
        $portable = $root . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'php.exe';
        if (is_file($portable)) {
            return $portable;
        }
    }

    return PHP_BINARY !== '' ? PHP_BINARY : 'php';
}

function birkly_mcp_desktop_relative_token_path(string $credentialsPath): string
{
    $root = birkly_project_root();
    $normalizedRoot = str_replace('\\', '/', $root);
    $normalizedPath = str_replace('\\', '/', $credentialsPath);
    if (str_starts_with($normalizedPath, $normalizedRoot . '/')) {
        return ltrim(substr($normalizedPath, strlen($normalizedRoot)), '/');
    }

    return $credentialsPath;
}

/**
 * @return array{command: string, args: list<string>}
 */
function birkly_mcp_desktop_stdio_command_entry(string $credentialsPath): array
{
    $phpBinary = birkly_mcp_desktop_php_binary();
    $bridgeScript = birkly_project_root() . '/scripts/birkly-mcp-desktop.php';

    return [
        'command' => $phpBinary,
        'args' => [
            $bridgeScript,
            '--token-file',
            $credentialsPath,
        ],
    ];
}

/**
 * @return array<string, mixed> App-specific MCP config fragment (no secrets in JSON).
 */
function birkly_mcp_desktop_client_config_snippet(string $credentialsPath, ?string $target = null): array
{
    $entry = birkly_mcp_desktop_stdio_command_entry($credentialsPath);
    if ($target === 'vscode') {
        return [
            'servers' => [
                'birkly' => array_merge(['type' => 'stdio'], $entry),
            ],
        ];
    }

    return [
        'mcpServers' => [
            'birkly' => $entry,
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
    if ($target === 'vscode') {
        return [
            'app_label' => 'VS Code (GitHub Copilot)',
            'mac_path' => '~/Library/Application Support/Code/User/mcp.json',
            'win_path' => '%APPDATA%\\Code\\User\\mcp.json',
        ];
    }
    if ($target === 'gemini') {
        return [
            'app_label' => 'Gemini CLI',
            'mac_path' => '~/.gemini/settings.json',
            'win_path' => '%USERPROFILE%\\.gemini\\settings.json',
        ];
    }

    return [
        'app_label' => 'Claude Desktop',
        'mac_path' => '~/Library/Application Support/Claude/claude_desktop_config.json',
        'win_path' => '%APPDATA%\\Claude\\claude_desktop_config.json',
    ];
}

/**
 * macOS double-click helper (.command) — runs the same PHP installer as Terminal.
 */
function birkly_mcp_desktop_mac_command_script(string $credentialsPath, string $target = 'claude'): string
{
    if (!birkly_mcp_desktop_is_valid_target($target)) {
        throw new InvalidArgumentException('Unknown install target');
    }
    $meta = birkly_mcp_desktop_install_target_meta($target);
    $root = birkly_project_root();
    $php = birkly_mcp_desktop_php_binary();
    $tokenArg = birkly_mcp_desktop_relative_token_path($credentialsPath);
    $install = $root . '/scripts/birkly-mcp-desktop-install.php';
    $rootEsc = str_replace("'", "'\\''", $root);
    $phpEsc = str_replace("'", "'\\''", $php);
    $installEsc = str_replace("'", "'\\''", $install);
    $tokenEsc = str_replace("'", "'\\''", $tokenArg);

    return <<<BASH
#!/bin/bash
set -e
echo "Birkly — connecting {$meta['app_label']} on this Mac..."
cd '{$rootEsc}'
'{$phpEsc}' '{$installEsc}' --target={$target} --token-file='{$tokenEsc}'
echo "Fully quit {$meta['app_label']}, then open it again."
read -r -p "Press Enter to close."
BASH;
}

/**
 * Windows helper (.bat) — runs the same PHP installer as PowerShell / cmd.
 */
function birkly_mcp_desktop_windows_bat_script(string $credentialsPath, string $target = 'claude'): string
{
    if (!birkly_mcp_desktop_is_valid_target($target)) {
        throw new InvalidArgumentException('Unknown install target');
    }
    $meta = birkly_mcp_desktop_install_target_meta($target);
    $root = birkly_project_root();
    $php = birkly_mcp_desktop_php_binary();
    $tokenArg = birkly_mcp_desktop_relative_token_path($credentialsPath);
    $install = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'birkly-mcp-desktop-install.php';
    $rootQ = '"' . str_replace('"', '""', $root) . '"';
    $phpQ = '"' . str_replace('"', '""', $php) . '"';
    $installQ = '"' . str_replace('"', '""', $install) . '"';
    $tokenQ = '"' . str_replace('"', '""', $tokenArg) . '"';
    $appLabel = $meta['app_label'];

    return "@echo off\r\n"
        . "title Birkly — {$appLabel}\r\n"
        . "echo Connecting {$appLabel} on this PC...\r\n"
        . "cd /d {$rootQ}\r\n"
        . "if errorlevel 1 (\r\n"
        . "  echo Could not open your Birkly folder. Use the copy-paste command from Settings -^> AI instead.\r\n"
        . "  pause\r\n"
        . "  exit /b 1\r\n"
        . ")\r\n"
        . "{$phpQ} {$installQ} --target={$target} --token-file={$tokenQ}\r\n"
        . "echo Fully quit {$appLabel}, then open it again.\r\n"
        . "pause\r\n";
}

/**
 * Absolute path to Claude / Cursor MCP config on this machine (CLI install).
 */
function birkly_mcp_desktop_user_home(): ?string
{
    if (PHP_OS_FAMILY === 'Windows') {
        $profile = (string) (getenv('USERPROFILE') ?: '');
        return $profile !== '' ? $profile : null;
    }
    $home = (string) (getenv('HOME') ?: '');
    return $home !== '' ? $home : null;
}

function birkly_mcp_desktop_app_config_path(string $target): ?string
{
    if (!birkly_mcp_desktop_is_valid_target($target)) {
        return null;
    }

    $home = birkly_mcp_desktop_user_home();
    if ($home === null) {
        return null;
    }

    if (PHP_OS_FAMILY === 'Windows') {
        $appData = (string) (getenv('APPDATA') ?: '');
        if ($target === 'cursor') {
            return $home . DIRECTORY_SEPARATOR . '.cursor' . DIRECTORY_SEPARATOR . 'mcp.json';
        }
        if ($target === 'gemini') {
            return $home . DIRECTORY_SEPARATOR . '.gemini' . DIRECTORY_SEPARATOR . 'settings.json';
        }
        if ($target === 'vscode') {
            if ($appData === '') {
                return null;
            }

            return $appData . DIRECTORY_SEPARATOR . 'Code' . DIRECTORY_SEPARATOR . 'User' . DIRECTORY_SEPARATOR . 'mcp.json';
        }

        if ($appData === '') {
            return null;
        }

        return $appData . DIRECTORY_SEPARATOR . 'Claude' . DIRECTORY_SEPARATOR . 'claude_desktop_config.json';
    }

    if ($target === 'cursor') {
        return $home . '/.cursor/mcp.json';
    }
    if ($target === 'gemini') {
        return $home . '/.gemini/settings.json';
    }
    if ($target === 'vscode') {
        return $home . '/Library/Application Support/Code/User/mcp.json';
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

    if ($target === 'vscode') {
        $servers = $data['servers'] ?? [];
        if (!is_array($servers)) {
            $servers = [];
        }
        $incoming = $mcpConfig['servers'] ?? [];
        if (is_array($incoming)) {
            foreach ($incoming as $name => $entry) {
                $servers[$name] = $entry;
            }
        }
        $data['servers'] = $servers;
    } else {
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
    }

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return ['ok' => false, 'message' => 'Could not encode config'];
    }
    if (file_put_contents($path, $json . "\n", LOCK_EX) === false) {
        return ['ok' => false, 'message' => 'Could not write config file'];
    }

    return ['ok' => true, 'path' => $path];
}

/**
 * Remove the birkly MCP server entry from Claude Desktop / Cursor config on this machine.
 *
 * @return array{ok: bool, path?: string, message?: string, removed?: bool}
 */
function birkly_mcp_desktop_remove_from_app_config(string $target, string $serverName = 'birkly'): array
{
    $path = birkly_mcp_desktop_app_config_path($target);
    if ($path === null) {
        return ['ok' => false, 'message' => 'Could not resolve config path on this computer'];
    }
    if (!is_file($path)) {
        return ['ok' => true, 'path' => $path, 'removed' => false];
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'message' => 'Config file is not valid JSON'];
    }

    if ($target === 'vscode') {
        $servers = $decoded['servers'] ?? [];
        if (!is_array($servers) || !isset($servers[$serverName])) {
            return ['ok' => true, 'path' => $path, 'removed' => false];
        }
        unset($servers[$serverName]);
        $decoded['servers'] = $servers;
    } else {
        $servers = $decoded['mcpServers'] ?? [];
        if (!is_array($servers) || !isset($servers[$serverName])) {
            return ['ok' => true, 'path' => $path, 'removed' => false];
        }
        unset($servers[$serverName]);
        $decoded['mcpServers'] = $servers;
    }

    $json = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return ['ok' => false, 'message' => 'Could not encode config'];
    }
    if (file_put_contents($path, $json . "\n", LOCK_EX) === false) {
        return ['ok' => false, 'message' => 'Could not write config file'];
    }

    return ['ok' => true, 'path' => $path, 'removed' => true];
}

function birkly_mcp_desktop_terminal_install_command(string $credentialsPath, string $target): string
{
    $root = birkly_project_root();
    $php = birkly_mcp_desktop_php_binary();
    $scriptRel = 'scripts/birkly-mcp-desktop-install.php';

    $tokenArg = birkly_mcp_desktop_relative_token_path($credentialsPath);

    if (PHP_OS_FAMILY === 'Windows') {
        return 'cd /d ' . escapeshellarg($root)
            . ' && ' . escapeshellarg($php)
            . ' ' . escapeshellarg($scriptRel)
            . ' --target=' . escapeshellarg($target)
            . ' --token-file=' . escapeshellarg($tokenArg);
    }

    return 'cd ' . escapeshellarg($root)
        . ' && ' . escapeshellarg($php)
        . ' ' . escapeshellarg($scriptRel)
        . ' --target=' . escapeshellarg($target)
        . ' --token-file=' . escapeshellarg($tokenArg);
}

function birkly_mcp_desktop_terminal_uninstall_command(string $target): string
{
    $root = birkly_project_root();
    $php = birkly_mcp_desktop_php_binary();
    $scriptRel = 'scripts/birkly-mcp-desktop-install.php';

    if (PHP_OS_FAMILY === 'Windows') {
        return 'cd /d ' . escapeshellarg($root)
            . ' && ' . escapeshellarg($php)
            . ' ' . escapeshellarg($scriptRel)
            . ' --uninstall --target=' . escapeshellarg($target);
    }

    return 'cd ' . escapeshellarg($root)
        . ' && ' . escapeshellarg($php)
        . ' ' . escapeshellarg($scriptRel)
        . ' --uninstall --target=' . escapeshellarg($target);
}

/**
 * @return array<string, string>
 */
function birkly_mcp_desktop_helper_download_names(): array
{
    $names = [];
    foreach (birkly_mcp_desktop_valid_targets() as $target) {
        $label = match ($target) {
            'cursor' => 'Cursor',
            'vscode' => 'VS-Code',
            'gemini' => 'Gemini',
            default => 'Claude',
        };
        $names[$target . '_mac'] = 'Connect-Birkly-to-' . $label . '.command';
        $names[$target . '_win'] = 'Connect-Birkly-to-' . $label . '.bat';
    }

    return $names;
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
        'terminal_install' => array_combine(
            birkly_mcp_desktop_valid_targets(),
            array_map(
                static fn (string $t) => birkly_mcp_desktop_terminal_install_command($path, $t),
                birkly_mcp_desktop_valid_targets()
            )
        ),
        'terminal_uninstall' => array_combine(
            birkly_mcp_desktop_valid_targets(),
            array_map(
                static fn (string $t) => birkly_mcp_desktop_terminal_uninstall_command($t),
                birkly_mcp_desktop_valid_targets()
            )
        ),
        'config_paths' => array_combine(
            birkly_mcp_desktop_valid_targets(),
            array_map(
                static fn (string $t) => birkly_mcp_desktop_app_config_path($t),
                birkly_mcp_desktop_valid_targets()
            )
        ),
        'claude_config_path' => birkly_mcp_desktop_app_config_path('claude'),
        'cursor_config_path' => birkly_mcp_desktop_app_config_path('cursor'),
        'install_targets' => array_combine(
            birkly_mcp_desktop_valid_targets(),
            array_map(
                static fn (string $t) => birkly_mcp_desktop_install_target_meta($t),
                birkly_mcp_desktop_valid_targets()
            )
        ),
        'helper_downloads' => birkly_mcp_desktop_helper_download_names(),
    ];
}
