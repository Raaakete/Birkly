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
$backupSettings = $settingsFile . '.bak.' . getmypid();
$backupOAuth = $oauthFile . '.bak.' . getmypid();

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
    @unlink($oauthFile);

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
    test_assert('protected resource has resource URL', str_contains($prData['resource'] ?? '', '/api/mcp_server.php'));
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
    $authUrl = $base . '/oauth/authorize?' . http_build_query([
        'response_type' => 'code',
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'state' => 'test-state',
        'code_challenge' => $pkce['challenge'],
        'code_challenge_method' => 'S256',
        'scope' => 'mcp',
    ]);
    $auth = birkly_test_http_request($authUrl);
    test_assert('authorize redirects 302', $auth['code'] === 302);
    preg_match('/code=([^&\s]+)/', $auth['headers'] ?? '', $codeMatch);
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
    birkly_test_http_server_stop($serverPid);
}

exit(test_finish('mcp_oauth_test'));
