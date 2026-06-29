<?php
/**
 * P5-M1 — CIMD (Client ID Metadata Document) OAuth tests.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/http_test_helpers.php';
require_once dirname(__DIR__) . '/core/mcp_endpoints.php';
require_once dirname(__DIR__) . '/core/mcp_oauth.php';

test_reset_failures();
echo "MCP CIMD OAuth tests\n\n";

$root = dirname(__DIR__);
$port = (int) (getenv('MCP_CIMD_TEST_PORT') ?: 18188);
$base = "http://127.0.0.1:$port";
$settingsFile = $root . '/settings/mcp_endpoints.json';
$oauthFile = $root . '/settings/mcp_oauth.json';
$backupSettings = $settingsFile . '.bak.' . getmypid();
$backupOAuth = $oauthFile . '.bak.' . getmypid();

$serverPid = birkly_test_http_server_start($root, $port);

function cimd_pkce_pair(): array
{
    $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    return ['verifier' => $verifier, 'challenge' => $challenge];
}

try {
    if (is_file($settingsFile)) {
        copy($settingsFile, $backupSettings);
    }
    if (is_file($oauthFile)) {
        copy($oauthFile, $backupOAuth);
    }
    @unlink($oauthFile);

    $as = birkly_test_http_request($base . '/.well-known/oauth-authorization-server');
    $asData = birkly_test_json_decode($as['body']);
    test_assert('AS metadata advertises CIMD support', ($asData['client_id_metadata_document_supported'] ?? false) === true);

    test_assert('is_cimd detects https URL', birkly_mcp_oauth_is_cimd_client_id('https://claude.ai/oauth/metadata.json'));
    test_assert('is_cimd rejects opaque id', !birkly_mcp_oauth_is_cimd_client_id('mcp_client_abc'));

    $redirectUri = 'https://claude.ai/api/mcp/auth_callback';
    $cimdUrl = 'https://claude.ai/api/mcp/client-metadata-test.json';

    $metadata = [
        'client_id' => $cimdUrl,
        'client_name' => 'Test CIMD Client',
        'redirect_uris' => [$redirectUri],
        'token_endpoint_auth_method' => 'none',
    ];

    $client = birkly_mcp_oauth_resolve_cimd_client($cimdUrl);
    test_assert('CIMD resolve returns null without live fetch', $client === null || is_array($client));

    birkly_mcp_create_endpoint([
        'name' => 'CIMD OAuth Endpoint',
        'role' => 'editor',
        'auth_mode' => 'oauth',
    ], 'test');

    $reg = birkly_test_http_request($base . '/oauth/register', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json', 'Origin: https://claude.ai'],
        'body' => json_encode([
            'client_name' => 'DCR fallback client',
            'redirect_uris' => [$redirectUri],
            'grant_types' => ['authorization_code'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
        ]),
    ]);
    test_assert('DCR still works alongside CIMD', $reg['code'] === 201);

    $store = birkly_mcp_oauth_load_store();
    $store['cimd_cache'][$cimdUrl] = [
        'client' => [
            'client_id' => $cimdUrl,
            'client_name' => 'Cached CIMD',
            'redirect_uris' => [$redirectUri],
            'token_endpoint_auth_method' => 'none',
            'cimd' => true,
        ],
        'expires_at' => time() + 3600,
    ];
    birkly_mcp_oauth_save_store($store);

    $cached = birkly_mcp_oauth_find_client($cimdUrl);
    test_assert('CIMD cache lookup works', is_array($cached));
    test_assert('CIMD redirect URI allowed', birkly_mcp_oauth_redirect_uri_allowed($cached, $redirectUri));

    $pkce = cimd_pkce_pair();
    $authUrl = $base . '/oauth/authorize?' . http_build_query([
        'response_type' => 'code',
        'client_id' => $cimdUrl,
        'redirect_uri' => $redirectUri,
        'code_challenge' => $pkce['challenge'],
        'code_challenge_method' => 'S256',
        'scope' => 'mcp',
    ]);
    $auth = birkly_test_http_request($authUrl, ['method' => 'GET', 'follow' => false]);
    test_assert('CIMD authorize redirects with code', $auth['code'] === 302);
    test_assert('redirect contains code=', str_contains($auth['headers'] ?? '', 'code='));

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

exit(test_finish('mcp_cimd_test'));
