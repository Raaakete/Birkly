<?php
/**
 * P4.1-M3 — MCP OAuth tests (metadata, PKCE, DCR, bearer auth).
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/http_test_helpers.php';
require_once dirname(__DIR__) . '/core/mcp_endpoints.php';
require_once dirname(__DIR__) . '/core/mcp_oauth.php';

test_reset_failures();
echo "MCP OAuth tests\n\n";

$root = dirname(__DIR__);
$port = (int) (getenv('MCP_OAUTH_TEST_PORT') ?: 18086);
$base = "http://127.0.0.1:$port";
$settingsFile = $root . '/settings/mcp_endpoints.json';
$oauthFile = $root . '/settings/mcp_oauth.json';
$agentsFile = $root . '/settings/ai_agents.json';
$backupSettings = $settingsFile . '.bak.' . getmypid();
$backupOAuth = $oauthFile . '.bak.' . getmypid();
$backupAgents = $agentsFile . '.bak.' . getmypid();

$serverPid = birkly_test_http_server_start($root, $port);

function oauth_pkce_pair(): array
{
    $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    return ['verifier' => $verifier, 'challenge' => $challenge];
}

try {
    if (is_file($settingsFile)) {
        copy($settingsFile, $backupSettings);
    } else {
        @unlink($backupSettings);
    }
    if (is_file($oauthFile)) {
        copy($oauthFile, $backupOAuth);
    } else {
        @unlink($backupOAuth);
    }
    if (is_file($agentsFile)) {
        copy($agentsFile, $backupAgents);
    } else {
        @unlink($backupAgents);
    }
    @unlink($oauthFile);
    @unlink($agentsFile);

    $unauth = birkly_test_http_request($base . '/api/mcp_server.php', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode(['jsonrpc' => '2.0', 'method' => 'tools/list', 'id' => 1]),
    ]);
    test_assert('unauthenticated MCP returns 401', $unauth['code'] === 401);
    test_assert(
        'WWW-Authenticate includes resource_metadata',
        str_contains($unauth['headers'] ?? '', 'resource_metadata=')
    );

    $pr = birkly_test_http_request($base . '/.well-known/oauth-protected-resource');
    $prData = birkly_test_json_decode($pr['body']);
    test_assert('protected resource HTTP 200', $pr['code'] === 200);
    test_assert('protected resource has resource URL', str_contains($prData['resource'] ?? '', '/mcp'));
    test_assert('protected resource lists authorization_servers', !empty($prData['authorization_servers']));

    $as = birkly_test_http_request($base . '/.well-known/oauth-authorization-server');
    $asData = birkly_test_json_decode($as['body']);
    test_assert('authorization server HTTP 200', $as['code'] === 200);
    test_assert('has authorization_endpoint', str_contains($asData['authorization_endpoint'] ?? '', '/oauth/authorize'));
    test_assert('has token_endpoint', str_contains($asData['token_endpoint'] ?? '', '/oauth/token'));
    test_assert('has registration_endpoint', str_contains($asData['registration_endpoint'] ?? '', '/oauth/register'));
    test_assert('PKCE S256 supported', in_array('S256', $asData['code_challenge_methods_supported'] ?? [], true));

    $redirectUri = 'https://claude.ai/api/mcp/auth_callback';
    $reg = birkly_test_http_request($base . '/oauth/register', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json', 'Origin: https://claude.ai'],
        'body' => json_encode([
            'client_name' => 'Test Claude Client',
            'redirect_uris' => [$redirectUri],
            'grant_types' => ['authorization_code'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
        ]),
    ]);
    $regData = birkly_test_json_decode($reg['body']);
    test_assert('DCR returns 201', $reg['code'] === 201);
    test_assert('DCR returns client_id', !empty($regData['client_id']));
    $clientId = $regData['client_id'];

    $endpoint = birkly_mcp_create_endpoint([
        'name' => 'OAuth Test Endpoint',
        'role' => 'admin',
        'require_approval' => true,
        'auth_mode' => 'both',
        'oauth_client_id' => $clientId,
    ], 'test');
    test_assert('endpoint auth_mode both', ($endpoint['auth_mode'] ?? '') === 'both');
    test_assert('endpoint oauth_client_id set', ($endpoint['oauth_client_id'] ?? '') === $clientId);

    $pkce = oauth_pkce_pair();
    $authQuery = [
        'response_type' => 'code',
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'state' => 'test-state',
        'code_challenge' => $pkce['challenge'],
        'code_challenge_method' => 'S256',
        'scope' => 'mcp',
    ];
    $authUrl = $base . '/oauth/authorize?' . http_build_query($authQuery);

    $usersDir = $root . '/users';
    $cookieFile = sys_get_temp_dir() . '/birkly-oauth-cookies-' . getmypid() . '.txt';
    $backupUsers = sys_get_temp_dir() . '/birkly-oauth-users-' . getmypid();
    $testEmail = 'oauth-test-' . getmypid() . '@birkly.test';
    $testPassword = 'SecurePass123!';
    @unlink($cookieFile);
    touch($cookieFile);
    birkly_test_backup_directory($usersDir, $backupUsers);
    birkly_test_clear_json_files($usersDir);

    $createUser = birkly_test_http_request($base . '/api/onboarding.php?action=create_first_user', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'name' => 'OAuth Test Admin',
            'email' => $testEmail,
            'password' => $testPassword,
        ]),
        'cookie_file' => $cookieFile,
    ]);
    test_assert('oauth test user created', ($createUser['code'] ?? 0) === 200);

    $consent = birkly_test_http_request($authUrl, ['cookie_file' => $cookieFile]);
    test_assert('authorize shows consent page', ($consent['code'] ?? 0) === 200);
    test_assert('consent page mentions agent role', str_contains($consent['body'] ?? '', 'admin'));
    preg_match('/name="_csrf"\s+value="([^"]+)"/', $consent['body'] ?? '', $csrfMatch);
    test_assert('consent page has csrf token', !empty($csrfMatch[1]));

    $approve = birkly_test_http_request($base . '/oauth/authorize', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
        'body' => http_build_query(array_merge($authQuery, [
            '_csrf' => $csrfMatch[1],
            'oauth_consent' => 'approve',
        ])),
        'cookie_file' => $cookieFile,
    ]);
    test_assert('consent approve redirects 302', $approve['code'] === 302);
    preg_match('/code=([^&\s]+)/', $approve['headers'] ?? '', $codeMatch);
    test_assert('authorize returns code in Location', !empty($codeMatch[1]));
    $code = urldecode($codeMatch[1]);

    $token = birkly_test_http_request($base . '/oauth/token', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
        'body' => http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'code_verifier' => $pkce['verifier'],
        ]),
    ]);
    $tokenData = birkly_test_json_decode($token['body']);
    test_assert('token endpoint HTTP 200', $token['code'] === 200);
    test_assert('access_token issued', !empty($tokenData['access_token']));
    $accessToken = $tokenData['access_token'];

    $tools = birkly_test_http_request($base . '/api/mcp_server.php', [
        'method' => 'POST',
        'headers' => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
        'body' => json_encode(['jsonrpc' => '2.0', 'method' => 'tools/list', 'id' => 2]),
    ]);
    $toolsData = birkly_test_json_decode($tools['body']);
    test_assert('bearer tools/list HTTP 200', $tools['code'] === 200);
    test_assert('bearer tools/list returns tools', is_array($toolsData['result']['tools'] ?? null));

    $apiKey = $endpoint['mcp_api_key'];
    $keyTools = birkly_test_http_request($base . '/api/mcp_server.php', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json', 'X-MCP-API-Key: ' . $apiKey],
        'body' => json_encode(['jsonrpc' => '2.0', 'method' => 'tools/list', 'id' => 3]),
    ]);
    test_assert('API key still works with both mode', $keyTools['code'] === 200);

    birkly_mcp_update_endpoint($endpoint['id'], ['auth_mode' => 'oauth']);
    $keyRejected = birkly_test_http_request($base . '/api/mcp_server.php', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json', 'X-MCP-API-Key: ' . $apiKey],
        'body' => json_encode(['jsonrpc' => '2.0', 'method' => 'tools/list', 'id' => 4]),
    ]);
    test_assert('oauth-only rejects API key', $keyRejected['code'] === 401);

    $options = birkly_test_http_request($base . '/oauth/token', [
        'method' => 'OPTIONS',
        'headers' => ['Origin: https://claude.ai', 'Access-Control-Request-Method: POST'],
    ]);
    test_assert('CORS preflight 204', $options['code'] === 204);
    test_assert('CORS allows claude.ai', str_contains($options['headers'] ?? '', 'https://claude.ai'));

    test_assert('normalize auth_mode default', birkly_mcp_normalize_auth_mode('invalid') === 'api_key');
    test_assert('www-authenticate header format', str_contains(
        birkly_mcp_oauth_www_authenticate_header(),
        'Bearer resource_metadata='
    ));

    birkly_mcp_revoke_endpoint($endpoint['id']);

    // P5.1 — hybrid agent visibility in OAuth
    require_once dirname(__DIR__) . '/core/ai_agents.php';
    birkly_ai_agents_ensure_personal();

    $adminOnlyService = birkly_ai_agents_create([
        'name' => 'Admin Only Service',
        'role' => 'admin',
        'visible_to_roles' => ['admin'],
        'require_approval' => true,
    ], 'test');

    $editorEmail = 'oauth-editor-' . getmypid() . '@birkly.test';
    $editorPassword = 'SecurePass123!';
    $editorCookie = sys_get_temp_dir() . '/birkly-oauth-editor-' . getmypid() . '.txt';
    @unlink($editorCookie);
    touch($editorCookie);

    $inviteEditor = birkly_test_http_request($base . '/api/users.php', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'action' => 'create_user',
            'email' => $editorEmail,
            'password' => $editorPassword,
            'role' => 'editor',
            'permissions' => ['manage_content', 'read', 'write'],
            '_csrf' => $csrfMatch[1] ?? '',
        ]),
        'cookie_file' => $cookieFile,
    ]);
    $inviteData = birkly_test_json_decode($inviteEditor['body'] ?? '');
    test_assert('editor user created for oauth visibility', ($inviteEditor['code'] ?? 0) === 200 && ($inviteData['status'] ?? '') === 'ok');

    $editorLogin = birkly_test_http_request($base . '/api/users.php', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'action' => 'login',
            'email' => $editorEmail,
            'password' => $editorPassword,
        ]),
        'cookie_file' => $editorCookie,
    ]);
    $editorLoginData = birkly_test_json_decode($editorLogin['body'] ?? '');
    test_assert('editor login for oauth visibility', ($editorLogin['code'] ?? 0) === 200 && ($editorLoginData['status'] ?? '') === 'ok');

    $serviceEndpoint = birkly_mcp_find_endpoint($adminOnlyService['mcp_endpoint_id']);
    $serviceAuthQuery = array_merge($authQuery, [
        'resource' => birkly_ai_agent_connector_url($adminOnlyService['id']),
    ]);
    $serviceAuthUrl = $base . '/oauth/authorize?' . http_build_query($serviceAuthQuery);
    $editorDenied = birkly_test_http_request($serviceAuthUrl, ['cookie_file' => $editorCookie]);
    test_assert('editor denied admin-only service agent oauth', ($editorDenied['code'] ?? 0) === 403);
    test_assert('denied page mentions not assigned', str_contains($editorDenied['body'] ?? '', 'not assigned'));

    $personalAuthQuery = array_merge($authQuery, [
        'resource' => birkly_ai_agent_connector_url(BIRKLY_AI_AGENT_PERSONAL_ID),
    ]);
    $personalAuthUrl = $base . '/oauth/authorize?' . http_build_query($personalAuthQuery);
    $editorPersonal = birkly_test_http_request($personalAuthUrl, ['cookie_file' => $editorCookie]);
    test_assert('editor sees personal agent consent', ($editorPersonal['code'] ?? 0) === 200);
    test_assert('personal consent mentions user role', str_contains($editorPersonal['body'] ?? '', 'editor'));

    birkly_ai_agents_revoke($adminOnlyService['id']);
    @unlink($editorCookie);

    if (isset($backupUsers, $usersDir)) {
        birkly_test_restore_directory($usersDir, $backupUsers);
        birkly_test_remove_directory($backupUsers);
    }
} finally {
    if (is_file($backupSettings)) {
        copy($backupSettings, $settingsFile);
        unlink($backupSettings);
    } elseif (is_file($settingsFile)) {
        unlink($settingsFile);
    }
    if (is_file($backupOAuth)) {
        copy($backupOAuth, $oauthFile);
        unlink($backupOAuth);
    } elseif (is_file($oauthFile)) {
        unlink($oauthFile);
    }
    if (is_file($backupAgents)) {
        copy($backupAgents, $agentsFile);
        unlink($backupAgents);
    } elseif (is_file($agentsFile)) {
        unlink($agentsFile);
    }
    birkly_test_http_server_stop($serverPid);
}

exit(test_finish('mcp_oauth_test'));
