<?php
/**
 * MCP OAuth — protected resource + authorization server (P4.1-M3).
 * RFC 9728, RFC 8414, PKCE, DCR. Storage: settings/mcp_oauth.json
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';
require_once __DIR__ . '/mcp_endpoints.php';

define('BIRKLY_MCP_OAUTH_ACCESS_TTL', 3600);
define('BIRKLY_MCP_OAUTH_REFRESH_TTL', 86400 * 30);
define('BIRKLY_MCP_OAUTH_CODE_TTL', 600);
define('BIRKLY_MCP_OAUTH_SCOPE', 'mcp');
define('BIRKLY_MCP_OAUTH_CORS_ORIGIN', 'https://claude.ai');

function birkly_mcp_oauth_storage(): Storage
{
    return new Storage(__DIR__ . '/../settings');
}

function birkly_mcp_oauth_default_store(): array
{
    return [
        'version' => 1,
        'clients' => [],
        'authorization_codes' => [],
        'tokens' => [],
        'cimd_cache' => [],
    ];
}

function birkly_mcp_oauth_load_store(): array
{
    $storage = birkly_mcp_oauth_storage();
    $data = $storage->read('mcp_oauth.json');
    if (!is_array($data)) {
        return birkly_mcp_oauth_default_store();
    }
    foreach (['clients', 'authorization_codes', 'tokens', 'cimd_cache'] as $key) {
        if (!isset($data[$key]) || !is_array($data[$key])) {
            $data[$key] = [];
        }
    }
    return $data;
}

function birkly_mcp_oauth_save_store(array $store): bool
{
    return (bool) birkly_mcp_oauth_storage()->write('mcp_oauth.json', $store);
}

function birkly_mcp_oauth_scheme(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']);
        if ($proto === 'https' || $proto === 'http') {
            return $proto;
        }
    }
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
}

function birkly_mcp_oauth_host(): string
{
    return (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function birkly_mcp_oauth_absolute_url(string $path): string
{
    return birkly_mcp_oauth_scheme() . '://' . birkly_mcp_oauth_host() . birkly_url($path);
}

function birkly_mcp_oauth_resource_url(): string
{
    require_once __DIR__ . '/mcp_protocol.php';
    return birkly_mcp_oauth_absolute_url(birkly_mcp_connector_path());
}

function birkly_mcp_oauth_protected_resource_metadata_url(): string
{
    return birkly_mcp_oauth_absolute_url('/.well-known/oauth-protected-resource');
}

function birkly_mcp_oauth_authorization_server_metadata_url(): string
{
    return birkly_mcp_oauth_absolute_url('/.well-known/oauth-authorization-server');
}

function birkly_mcp_oauth_www_authenticate_header(): string
{
    $meta = birkly_mcp_oauth_protected_resource_metadata_url();
    return 'Bearer resource_metadata="' . $meta . '"';
}

function birkly_mcp_oauth_apply_cors(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === BIRKLY_MCP_OAUTH_CORS_ORIGIN) {
        header('Access-Control-Allow-Origin: ' . BIRKLY_MCP_OAUTH_CORS_ORIGIN);
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Vary: Origin');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function birkly_mcp_oauth_json_response(array $data, int $code = 200): void
{
    birkly_mcp_oauth_apply_cors();
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function birkly_mcp_oauth_error(string $error, string $description = '', int $code = 400): void
{
    $payload = ['error' => $error];
    if ($description !== '') {
        $payload['error_description'] = $description;
    }
    birkly_mcp_oauth_json_response($payload, $code);
}

function birkly_mcp_oauth_protected_resource_metadata(): array
{
    return [
        'resource' => birkly_mcp_oauth_resource_url(),
        'authorization_servers' => [birkly_mcp_oauth_authorization_server_metadata_url()],
        'bearer_methods_supported' => ['header'],
        'scopes_supported' => [BIRKLY_MCP_OAUTH_SCOPE],
    ];
}

function birkly_mcp_oauth_authorization_server_metadata(): array
{
    return [
        'issuer' => birkly_mcp_oauth_absolute_url('/'),
        'authorization_endpoint' => birkly_mcp_oauth_absolute_url('/oauth/authorize'),
        'token_endpoint' => birkly_mcp_oauth_absolute_url('/oauth/token'),
        'registration_endpoint' => birkly_mcp_oauth_absolute_url('/oauth/register'),
        'response_types_supported' => ['code'],
        'grant_types_supported' => ['authorization_code', 'refresh_token'],
        'code_challenge_methods_supported' => ['S256'],
        'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post'],
        'scopes_supported' => [BIRKLY_MCP_OAUTH_SCOPE],
        'client_id_metadata_document_supported' => true,
    ];
}

function birkly_mcp_oauth_handle_protected_resource(): void
{
    birkly_mcp_oauth_json_response(birkly_mcp_oauth_protected_resource_metadata());
}

function birkly_mcp_oauth_handle_authorization_server(): void
{
    birkly_mcp_oauth_json_response(birkly_mcp_oauth_authorization_server_metadata());
}

function birkly_mcp_oauth_generate_id(string $prefix = ''): string
{
    return $prefix . bin2hex(random_bytes(16));
}

function birkly_mcp_oauth_find_client(string $clientId): ?array
{
    $client = birkly_mcp_oauth_load_store()['clients'][$clientId] ?? null;
    if (is_array($client)) {
        return $client;
    }

    if (birkly_mcp_oauth_is_cimd_client_id($clientId)) {
        return birkly_mcp_oauth_resolve_cimd_client($clientId);
    }

    return null;
}

function birkly_mcp_oauth_is_cimd_client_id(string $clientId): bool
{
    return str_starts_with($clientId, 'https://');
}

function birkly_mcp_oauth_is_private_ip(string $ip): bool
{
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return true;
    }
    return !filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    );
}

function birkly_mcp_oauth_fetch_cimd_metadata(string $url): ?array
{
    if (!birkly_mcp_oauth_is_cimd_client_id($url)) {
        return null;
    }

    $parsed = parse_url($url);
    if (!is_array($parsed) || ($parsed['scheme'] ?? '') !== 'https') {
        return null;
    }
    if (empty($parsed['host']) || empty($parsed['path'])) {
        return null;
    }

    $host = $parsed['host'];
    $resolved = gethostbyname($host);
    if ($resolved === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
        return null;
    }
    if (birkly_mcp_oauth_is_private_ip($resolved)) {
        return null;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 5,
            'follow_location' => 0,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    if ($body === false || strlen($body) > 5120) {
        return null;
    }

    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }
    if ($status !== 200) {
        return null;
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        return null;
    }
    if (($data['client_id'] ?? '') !== $url) {
        return null;
    }
    if (empty($data['redirect_uris']) || !is_array($data['redirect_uris'])) {
        return null;
    }

    return $data;
}

function birkly_mcp_oauth_resolve_cimd_client(string $clientId): ?array
{
    $store = birkly_mcp_oauth_load_store();
    $cached = $store['cimd_cache'][$clientId] ?? null;
    if (is_array($cached) && ($cached['expires_at'] ?? 0) > time()) {
        return $cached['client'];
    }

    $metadata = birkly_mcp_oauth_fetch_cimd_metadata($clientId);
    if ($metadata === null) {
        return null;
    }

    $client = [
        'client_id' => $clientId,
        'client_name' => (string) ($metadata['client_name'] ?? 'MCP Client'),
        'redirect_uris' => array_values(array_map('strval', $metadata['redirect_uris'])),
        'grant_types' => $metadata['grant_types'] ?? ['authorization_code'],
        'response_types' => $metadata['response_types'] ?? ['code'],
        'token_endpoint_auth_method' => (string) ($metadata['token_endpoint_auth_method'] ?? 'none'),
        'cimd' => true,
        'fetched_at' => date('c'),
    ];

    if (!isset($store['cimd_cache']) || !is_array($store['cimd_cache'])) {
        $store['cimd_cache'] = [];
    }
    $store['cimd_cache'][$clientId] = [
        'client' => $client,
        'expires_at' => time() + 86400,
    ];
    birkly_mcp_oauth_save_store($store);

    return $client;
}

function birkly_mcp_oauth_redirect_uri_allowed(array $client, string $redirectUri): bool
{
    $uris = $client['redirect_uris'] ?? [];
    if (!is_array($uris)) {
        return false;
    }
    return in_array($redirectUri, $uris, true);
}

function birkly_mcp_oauth_oauth_enabled_endpoints(): array
{
    $endpoints = [];
    foreach (birkly_mcp_load_store()['endpoints'] as $endpoint) {
        if (($endpoint['status'] ?? 'active') !== 'active') {
            continue;
        }
        $mode = $endpoint['auth_mode'] ?? 'api_key';
        if ($mode === 'oauth' || $mode === 'both') {
            $endpoints[] = $endpoint;
        }
    }
    return $endpoints;
}

function birkly_mcp_oauth_resolve_endpoint_for_client(string $clientId): ?array
{
    foreach (birkly_mcp_load_store()['endpoints'] as $endpoint) {
        if (($endpoint['status'] ?? 'active') !== 'active') {
            continue;
        }
        $mode = $endpoint['auth_mode'] ?? 'api_key';
        if ($mode !== 'oauth' && $mode !== 'both') {
            continue;
        }
        $registered = trim((string) ($endpoint['oauth_client_id'] ?? ''));
        if ($registered !== '' && hash_equals($registered, $clientId)) {
            return $endpoint;
        }
    }

    $client = birkly_mcp_oauth_find_client($clientId);
    if ($client && !empty($client['endpoint_id'])) {
        $endpoint = birkly_mcp_find_endpoint((string) $client['endpoint_id']);
        if ($endpoint && ($endpoint['status'] ?? 'active') === 'active') {
            $mode = $endpoint['auth_mode'] ?? 'api_key';
            if ($mode === 'oauth' || $mode === 'both') {
                return $endpoint;
            }
        }
    }

    $enabled = birkly_mcp_oauth_oauth_enabled_endpoints();
    if (count($enabled) === 1) {
        return $enabled[0];
    }

    // CIMD clients (Claude/ChatGPT) use the site-wide /mcp URL — map to the newest OAuth endpoint.
    if (birkly_mcp_oauth_is_cimd_client_id($clientId) && count($enabled) > 0) {
        return $enabled[count($enabled) - 1];
    }

    return null;
}

function birkly_mcp_oauth_verify_pkce(string $verifier, string $challenge, string $method): bool
{
    if ($method !== 'S256' && $method !== 'plain') {
        return false;
    }
    if ($method === 'plain') {
        return hash_equals($challenge, $verifier);
    }
    $computed = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    return hash_equals($challenge, $computed);
}

function birkly_mcp_oauth_purge_expired(): void
{
    $store = birkly_mcp_oauth_load_store();
    $now = time();
    $changed = false;

    foreach ($store['authorization_codes'] as $code => $entry) {
        if (($entry['expires_at'] ?? 0) < $now) {
            unset($store['authorization_codes'][$code]);
            $changed = true;
        }
    }

    foreach ($store['tokens'] as $hash => $entry) {
        $accessExpired = ($entry['expires_at'] ?? 0) < $now;
        $refreshExpired = ($entry['refresh_expires_at'] ?? 0) < $now;
        if ($accessExpired && $refreshExpired) {
            unset($store['tokens'][$hash]);
            $changed = true;
        }
    }

    if ($changed) {
        birkly_mcp_oauth_save_store($store);
    }
}

function birkly_mcp_oauth_handle_authorize(): void
{
    birkly_mcp_oauth_purge_expired();

    $responseType = $_GET['response_type'] ?? '';
    $clientId = trim((string) ($_GET['client_id'] ?? ''));
    $redirectUri = (string) ($_GET['redirect_uri'] ?? '');
    $state = (string) ($_GET['state'] ?? '');
    $codeChallenge = (string) ($_GET['code_challenge'] ?? '');
    $codeChallengeMethod = (string) ($_GET['code_challenge_method'] ?? 'S256');
    $scope = (string) ($_GET['scope'] ?? BIRKLY_MCP_OAUTH_SCOPE);

    if ($responseType !== 'code') {
        birkly_mcp_oauth_error('unsupported_response_type', 'Only response_type=code is supported');
    }
    if ($clientId === '') {
        birkly_mcp_oauth_error('invalid_request', 'client_id is required');
    }
    if ($redirectUri === '') {
        birkly_mcp_oauth_error('invalid_request', 'redirect_uri is required');
    }
    if ($codeChallenge === '') {
        birkly_mcp_oauth_error('invalid_request', 'code_challenge is required (PKCE)');
    }

    $client = birkly_mcp_oauth_find_client($clientId);
    if (!$client && birkly_mcp_oauth_resolve_endpoint_for_client($clientId) === null) {
        birkly_mcp_oauth_error('invalid_client', 'Unknown client_id');
    }
    if ($client && !birkly_mcp_oauth_redirect_uri_allowed($client, $redirectUri)) {
        birkly_mcp_oauth_error('invalid_request', 'redirect_uri not registered for client');
    }

    $endpoint = birkly_mcp_oauth_resolve_endpoint_for_client($clientId);
    if ($endpoint === null) {
        birkly_mcp_oauth_error('access_denied', 'No OAuth-enabled MCP endpoint mapped to this client');
    }

    $code = birkly_mcp_oauth_generate_id('mcp_code_');
    $store = birkly_mcp_oauth_load_store();
    $store['authorization_codes'][$code] = [
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'endpoint_id' => $endpoint['id'],
        'code_challenge' => $codeChallenge,
        'code_challenge_method' => $codeChallengeMethod,
        'scope' => $scope,
        'expires_at' => time() + BIRKLY_MCP_OAUTH_CODE_TTL,
        'created_at' => date('c'),
    ];
    birkly_mcp_oauth_save_store($store);

    $separator = str_contains($redirectUri, '?') ? '&' : '?';
    $location = $redirectUri . $separator . 'code=' . rawurlencode($code);
    if ($state !== '') {
        $location .= '&state=' . rawurlencode($state);
    }

    birkly_mcp_oauth_apply_cors();
    header('Location: ' . $location, true, 302);
    exit;
}

function birkly_mcp_oauth_parse_token_request(): array
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        $body = json_decode((string) file_get_contents('php://input'), true);
        return is_array($body) ? $body : [];
    }
    return $_POST;
}

function birkly_mcp_oauth_issue_tokens(string $clientId, string $endpointId, string $scope): array
{
    $accessToken = birkly_mcp_oauth_generate_id('mcp_at_');
    $refreshToken = birkly_mcp_oauth_generate_id('mcp_rt_');
    $now = time();

    $store = birkly_mcp_oauth_load_store();
    $store['tokens'][hash('sha256', $accessToken)] = [
        'access_token' => $accessToken,
        'refresh_token' => $refreshToken,
        'client_id' => $clientId,
        'endpoint_id' => $endpointId,
        'scope' => $scope,
        'expires_at' => $now + BIRKLY_MCP_OAUTH_ACCESS_TTL,
        'refresh_expires_at' => $now + BIRKLY_MCP_OAUTH_REFRESH_TTL,
        'created_at' => date('c'),
    ];
    birkly_mcp_oauth_save_store($store);

    return [
        'access_token' => $accessToken,
        'token_type' => 'Bearer',
        'expires_in' => BIRKLY_MCP_OAUTH_ACCESS_TTL,
        'refresh_token' => $refreshToken,
        'scope' => $scope,
    ];
}

function birkly_mcp_oauth_handle_token(): void
{
    birkly_mcp_oauth_purge_expired();

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        birkly_mcp_oauth_error('invalid_request', 'POST required', 405);
    }

    $input = birkly_mcp_oauth_parse_token_request();
    $grantType = (string) ($input['grant_type'] ?? '');

    if ($grantType === 'authorization_code') {
        $code = (string) ($input['code'] ?? '');
        $redirectUri = (string) ($input['redirect_uri'] ?? '');
        $clientId = trim((string) ($input['client_id'] ?? ''));
        $codeVerifier = (string) ($input['code_verifier'] ?? '');

        if ($code === '' || $clientId === '' || $codeVerifier === '') {
            birkly_mcp_oauth_error('invalid_request', 'code, client_id, and code_verifier are required');
        }

        $store = birkly_mcp_oauth_load_store();
        $codeEntry = $store['authorization_codes'][$code] ?? null;
        if (!$codeEntry || ($codeEntry['expires_at'] ?? 0) < time()) {
            birkly_mcp_oauth_error('invalid_grant', 'Authorization code invalid or expired');
        }
        if (!hash_equals((string) $codeEntry['client_id'], $clientId)) {
            birkly_mcp_oauth_error('invalid_grant', 'client_id mismatch');
        }
        if ($redirectUri !== '' && !hash_equals((string) $codeEntry['redirect_uri'], $redirectUri)) {
            birkly_mcp_oauth_error('invalid_grant', 'redirect_uri mismatch');
        }
        if (!birkly_mcp_oauth_verify_pkce(
            $codeVerifier,
            (string) $codeEntry['code_challenge'],
            (string) ($codeEntry['code_challenge_method'] ?? 'S256')
        )) {
            birkly_mcp_oauth_error('invalid_grant', 'PKCE verification failed');
        }

        unset($store['authorization_codes'][$code]);
        birkly_mcp_oauth_save_store($store);

        $endpointId = (string) $codeEntry['endpoint_id'];
        $scope = (string) ($codeEntry['scope'] ?? BIRKLY_MCP_OAUTH_SCOPE);
        birkly_mcp_oauth_json_response(birkly_mcp_oauth_issue_tokens($clientId, $endpointId, $scope));
    }

    if ($grantType === 'refresh_token') {
        $refreshToken = (string) ($input['refresh_token'] ?? '');
        $clientId = trim((string) ($input['client_id'] ?? ''));
        if ($refreshToken === '' || $clientId === '') {
            birkly_mcp_oauth_error('invalid_request', 'refresh_token and client_id are required');
        }

        $store = birkly_mcp_oauth_load_store();
        $matched = null;
        $matchedHash = null;
        foreach ($store['tokens'] as $hash => $entry) {
            if (!hash_equals((string) ($entry['refresh_token'] ?? ''), $refreshToken)) {
                continue;
            }
            if (!hash_equals((string) ($entry['client_id'] ?? ''), $clientId)) {
                continue;
            }
            if (($entry['refresh_expires_at'] ?? 0) < time()) {
                birkly_mcp_oauth_error('invalid_grant', 'Refresh token expired');
            }
            $matched = $entry;
            $matchedHash = $hash;
            break;
        }

        if ($matched === null) {
            birkly_mcp_oauth_error('invalid_grant', 'Refresh token invalid');
        }

        unset($store['tokens'][$matchedHash]);
        birkly_mcp_oauth_save_store($store);

        birkly_mcp_oauth_json_response(birkly_mcp_oauth_issue_tokens(
            $clientId,
            (string) $matched['endpoint_id'],
            (string) ($matched['scope'] ?? BIRKLY_MCP_OAUTH_SCOPE)
        ));
    }

    birkly_mcp_oauth_error('unsupported_grant_type', 'Supported: authorization_code, refresh_token');
}

function birkly_mcp_oauth_handle_register(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        birkly_mcp_oauth_error('invalid_request', 'POST required', 405);
    }

    $input = birkly_mcp_oauth_parse_token_request();
    if ($input === [] && ($_SERVER['CONTENT_TYPE'] ?? '') !== '') {
        $raw = json_decode((string) file_get_contents('php://input'), true);
        $input = is_array($raw) ? $raw : [];
    }

    $clientName = trim((string) ($input['client_name'] ?? 'MCP Client'));
    $redirectUris = $input['redirect_uris'] ?? [];
    if (!is_array($redirectUris) || $redirectUris === []) {
        birkly_mcp_oauth_error('invalid_client_metadata', 'redirect_uris is required');
    }

    $grantTypes = $input['grant_types'] ?? ['authorization_code'];
    $responseTypes = $input['response_types'] ?? ['code'];
    $authMethod = (string) ($input['token_endpoint_auth_method'] ?? 'none');
    $endpointId = trim((string) ($input['endpoint_id'] ?? ''));

    $clientId = birkly_mcp_oauth_generate_id('mcp_client_');
    $clientSecret = $authMethod === 'none' ? null : bin2hex(random_bytes(32));

    $client = [
        'client_id' => $clientId,
        'client_name' => $clientName,
        'redirect_uris' => array_values(array_map('strval', $redirectUris)),
        'grant_types' => array_values(array_map('strval', is_array($grantTypes) ? $grantTypes : ['authorization_code'])),
        'response_types' => array_values(array_map('strval', is_array($responseTypes) ? $responseTypes : ['code'])),
        'token_endpoint_auth_method' => $authMethod,
        'created_at' => date('c'),
    ];
    if ($clientSecret !== null) {
        $client['client_secret'] = $clientSecret;
    }
    if ($endpointId !== '') {
        $client['endpoint_id'] = $endpointId;
    }

    $store = birkly_mcp_oauth_load_store();
    $store['clients'][$clientId] = $client;
    birkly_mcp_oauth_save_store($store);

    $response = [
        'client_id' => $clientId,
        'client_name' => $clientName,
        'redirect_uris' => $client['redirect_uris'],
        'grant_types' => $client['grant_types'],
        'response_types' => $client['response_types'],
        'token_endpoint_auth_method' => $authMethod,
    ];
    if ($clientSecret !== null) {
        $response['client_secret'] = $clientSecret;
    }

    birkly_mcp_oauth_json_response($response, 201);
}

function birkly_mcp_oauth_extract_bearer(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    if (preg_match('/^Bearer\s+(\S+)/i', (string) $header, $m)) {
        return $m[1];
    }
    return '';
}

function birkly_mcp_oauth_find_token_entry(string $accessToken): ?array
{
    birkly_mcp_oauth_purge_expired();
    $hash = hash('sha256', $accessToken);
    $entry = birkly_mcp_oauth_load_store()['tokens'][$hash] ?? null;
    if (!is_array($entry)) {
        return null;
    }
    if (($entry['expires_at'] ?? 0) < time()) {
        return null;
    }
    if (!hash_equals((string) ($entry['access_token'] ?? ''), $accessToken)) {
        return null;
    }
    return $entry;
}

function birkly_mcp_oauth_resolve_actor_by_token(string $accessToken): ?array
{
    $entry = birkly_mcp_oauth_find_token_entry($accessToken);
    if ($entry === null) {
        return null;
    }

    $endpoint = birkly_mcp_find_endpoint((string) ($entry['endpoint_id'] ?? ''));
    if ($endpoint === null || ($endpoint['status'] ?? 'active') !== 'active') {
        return null;
    }

    $mode = $endpoint['auth_mode'] ?? 'api_key';
    if ($mode !== 'oauth' && $mode !== 'both') {
        return null;
    }

    return birkly_mcp_endpoint_to_ai_user($endpoint);
}

function birkly_mcp_endpoint_accepts_api_key(array $endpoint): bool
{
    $mode = $endpoint['auth_mode'] ?? 'api_key';
    return $mode === 'api_key' || $mode === 'both';
}

function birkly_mcp_endpoint_accepts_oauth(array $endpoint): bool
{
    $mode = $endpoint['auth_mode'] ?? 'api_key';
    return $mode === 'oauth' || $mode === 'both';
}

function birkly_mcp_resolve_actor_with_auth(string $apiKey, string $bearerToken): ?array
{
    if ($apiKey !== '') {
        $endpoint = birkly_mcp_resolve_endpoint_by_key($apiKey);
        if ($endpoint && !birkly_mcp_endpoint_accepts_api_key($endpoint)) {
            return null;
        }
        $actor = birkly_mcp_resolve_actor_by_key($apiKey);
        if ($actor) {
            return $actor;
        }
    }

    if ($bearerToken !== '') {
        return birkly_mcp_oauth_resolve_actor_by_token($bearerToken);
    }

    return null;
}

function birkly_mcp_oauth_valid_auth_modes(): array
{
    return ['api_key', 'oauth', 'both'];
}
