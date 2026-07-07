<?php
/**
 * Birkly cloud account link (P13-M1) — optional OAuth link; local CMS login remains primary.
 */

const BIRKLY_CLOUD_ACCOUNT_FILE = 'cloud_account.json';
const BIRKLY_CLOUD_OAUTH_DEFAULT_URL = 'https://cloud.birkly.io';

function birkly_cloud_account_settings_dir(): string
{
    if (isset($GLOBALS['__birkly_cloud_account_settings_dir'])) {
        return (string) $GLOBALS['__birkly_cloud_account_settings_dir'];
    }

    return __DIR__ . '/../settings';
}

function birkly_cloud_account_reset(): void
{
    unset($GLOBALS['__birkly_cloud_account_data']);
}

function birkly_cloud_account_mock_enabled(): bool
{
    if (!empty($GLOBALS['birkly_cloud_account_mock_mode'])) {
        return true;
    }

    return getenv('BIRKLY_CLOUD_ACCOUNT_MOCK') === '1';
}

/**
 * @return array<string, mixed>
 */
function birkly_cloud_account_read_raw(): array
{
    if (isset($GLOBALS['__birkly_cloud_account_data']) && is_array($GLOBALS['__birkly_cloud_account_data'])) {
        return $GLOBALS['__birkly_cloud_account_data'];
    }

    require_once __DIR__ . '/../api/storage.php';
    $storage = new Storage(birkly_cloud_account_settings_dir());
    $data = $storage->read(BIRKLY_CLOUD_ACCOUNT_FILE);
    if (!is_array($data)) {
        return [];
    }

    $GLOBALS['__birkly_cloud_account_data'] = $data;
    return $data;
}

/**
 * @param array<string, mixed> $data
 */
function birkly_cloud_account_write_raw(array $data): bool
{
    require_once __DIR__ . '/../api/storage.php';
    $storage = new Storage(birkly_cloud_account_settings_dir());
    $ok = $storage->write(BIRKLY_CLOUD_ACCOUNT_FILE, $data);
    if ($ok) {
        $GLOBALS['__birkly_cloud_account_data'] = $data;
        $path = rtrim(birkly_cloud_account_settings_dir(), '/') . '/' . BIRKLY_CLOUD_ACCOUNT_FILE;
        if (is_file($path)) {
            @chmod($path, 0600);
        }
    }

    return $ok;
}

function birkly_cloud_oauth_base_url(): string
{
    $env = trim((string) (getenv('BIRKLY_CLOUD_OAUTH_URL') ?: ''));
    if ($env !== '') {
        return rtrim($env, '/');
    }

    return BIRKLY_CLOUD_OAUTH_DEFAULT_URL;
}

/**
 * @return array{linked:bool,email:?string,linked_at:?string,expires_at:?string}
 */
function birkly_cloud_account_status(): array
{
    $data = birkly_cloud_account_read_raw();
    $linked = !empty($data['linked']) && trim((string) ($data['email'] ?? '')) !== '';

    return [
        'linked' => $linked,
        'email' => $linked ? (string) $data['email'] : null,
        'linked_at' => $linked ? (string) ($data['linked_at'] ?? '') : null,
        'expires_at' => $linked ? (string) ($data['expires_at'] ?? '') : null,
    ];
}

function birkly_cloud_account_is_linked(): bool
{
    return birkly_cloud_account_status()['linked'];
}

/**
 * @return array{ok:bool,authorization_url?:string,state?:string,error?:string}
 */
function birkly_cloud_account_start_link(string $callbackUrl): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return ['ok' => false, 'error' => 'Session required'];
    }

    $state = bin2hex(random_bytes(16));
    $_SESSION['cloud_account_link'] = [
        'state' => $state,
        'created_at' => time(),
    ];

    $params = http_build_query([
        'client_id' => 'birkly-cms',
        'response_type' => 'code',
        'redirect_uri' => $callbackUrl,
        'state' => $state,
        'scope' => 'account:email',
    ]);

    $authUrl = birkly_cloud_oauth_base_url() . '/oauth/authorize?' . $params;

    if (birkly_cloud_account_mock_enabled()) {
        $sep = str_contains($callbackUrl, '?') ? '&' : '?';
        $authUrl = $callbackUrl . $sep . http_build_query([
            'code' => 'mock_auth_code',
            'state' => $state,
        ]);
    }

    return [
        'ok' => true,
        'authorization_url' => $authUrl,
        'state' => $state,
    ];
}

function birkly_cloud_account_validate_state(string $state): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    $stored = $_SESSION['cloud_account_link']['state'] ?? '';
    $created = (int) ($_SESSION['cloud_account_link']['created_at'] ?? 0);
    unset($_SESSION['cloud_account_link']);

    if ($stored === '' || $created === 0) {
        return false;
    }

    if ((time() - $created) > 600) {
        return false;
    }

    return hash_equals($stored, $state);
}

/**
 * @return array{ok:bool,email?:string,access_token?:string,refresh_token?:string,expires_in?:int,error?:string}
 */
function birkly_cloud_account_exchange_code(string $code): array
{
    $code = trim($code);
    if ($code === '') {
        return ['ok' => false, 'error' => 'Authorization code required'];
    }

    if (isset($GLOBALS['birkly_cloud_account_mock_token_handler']) && is_callable($GLOBALS['birkly_cloud_account_mock_token_handler'])) {
        return ($GLOBALS['birkly_cloud_account_mock_token_handler'])($code);
    }

    if (birkly_cloud_account_mock_enabled() && $code === 'mock_auth_code') {
        $email = trim((string) (getenv('BIRKLY_CLOUD_ACCOUNT_MOCK_EMAIL') ?: 'mock@birkly.cloud'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Invalid mock email'];
        }

        return [
            'ok' => true,
            'email' => $email,
            'access_token' => 'mock_access_' . bin2hex(random_bytes(8)),
            'refresh_token' => 'mock_refresh_' . bin2hex(random_bytes(8)),
            'expires_in' => 3600,
        ];
    }

    if (str_starts_with($code, 'mock_')) {
        $email = substr($code, 5);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Invalid mock code'];
        }

        return [
            'ok' => true,
            'email' => $email,
            'access_token' => 'mock_access_' . bin2hex(random_bytes(8)),
            'refresh_token' => 'mock_refresh_' . bin2hex(random_bytes(8)),
            'expires_in' => 3600,
        ];
    }

    return ['ok' => false, 'error' => 'Cloud OAuth token exchange unavailable (use mock in dev/test)'];
}

/**
 * @param array{email:string,access_token:string,refresh_token?:string,expires_in?:int} $tokens
 * @return array{ok:bool,email?:string,error?:string}
 */
function birkly_cloud_account_store_link(array $tokens): array
{
    $email = trim((string) ($tokens['email'] ?? ''));
    $accessToken = trim((string) ($tokens['access_token'] ?? ''));
    if ($email === '' || $accessToken === '') {
        return ['ok' => false, 'error' => 'Invalid token response'];
    }

    $expiresIn = (int) ($tokens['expires_in'] ?? 3600);
    $expiresAt = gmdate('Y-m-d\TH:i:s\Z', time() + max(60, $expiresIn));

    $data = [
        'linked' => true,
        'email' => $email,
        'access_token' => $accessToken,
        'refresh_token' => trim((string) ($tokens['refresh_token'] ?? '')),
        'expires_at' => $expiresAt,
        'linked_at' => gmdate('Y-m-d\TH:i:s\Z'),
    ];

    if (!birkly_cloud_account_write_raw($data)) {
        return ['ok' => false, 'error' => 'Failed to save cloud account link'];
    }

    require_once __DIR__ . '/marketplace.php';
    birkly_marketplace_sync_owner_email($email);

    return ['ok' => true, 'email' => $email];
}

/**
 * @return array{ok:bool,email?:string,error?:string}
 */
function birkly_cloud_account_handle_callback(string $code, string $state): array
{
    if (!birkly_cloud_account_validate_state($state)) {
        return ['ok' => false, 'error' => 'Invalid or expired link state'];
    }

    $exchange = birkly_cloud_account_exchange_code($code);
    if (empty($exchange['ok'])) {
        return ['ok' => false, 'error' => $exchange['error'] ?? 'Token exchange failed'];
    }

    return birkly_cloud_account_store_link($exchange);
}

/**
 * @return array{ok:bool,access_token?:string,refresh_token?:string,expires_in?:int,error?:string}
 */
function birkly_cloud_account_refresh_tokens(string $refreshToken): array
{
    if (isset($GLOBALS['birkly_cloud_account_mock_refresh_handler']) && is_callable($GLOBALS['birkly_cloud_account_mock_refresh_handler'])) {
        return ($GLOBALS['birkly_cloud_account_mock_refresh_handler'])($refreshToken);
    }

    if (str_starts_with($refreshToken, 'mock_refresh_')) {
        return [
            'ok' => true,
            'access_token' => 'mock_access_' . bin2hex(random_bytes(8)),
            'refresh_token' => 'mock_refresh_' . bin2hex(random_bytes(8)),
            'expires_in' => 3600,
        ];
    }

    return ['ok' => false, 'error' => 'Token refresh unavailable'];
}

function birkly_cloud_account_refresh_tokens_if_needed(): bool
{
    $data = birkly_cloud_account_read_raw();
    if (empty($data['linked'])) {
        return false;
    }

    $expiresAt = strtotime((string) ($data['expires_at'] ?? ''));
    if ($expiresAt === false || ($expiresAt - time()) > 300) {
        return true;
    }

    $refreshToken = trim((string) ($data['refresh_token'] ?? ''));
    if ($refreshToken === '') {
        return false;
    }

    $refreshed = birkly_cloud_account_refresh_tokens($refreshToken);
    if (empty($refreshed['ok'])) {
        return false;
    }

    $data['access_token'] = (string) ($refreshed['access_token'] ?? $data['access_token']);
    if (!empty($refreshed['refresh_token'])) {
        $data['refresh_token'] = (string) $refreshed['refresh_token'];
    }
    $expiresIn = (int) ($refreshed['expires_in'] ?? 3600);
    $data['expires_at'] = gmdate('Y-m-d\TH:i:s\Z', time() + max(60, $expiresIn));

    return birkly_cloud_account_write_raw($data);
}

/**
 * @return array{ok:bool,error?:string}
 */
function birkly_cloud_account_unlink(): array
{
    $empty = [
        'linked' => false,
        'email' => '',
        'access_token' => '',
        'refresh_token' => '',
        'expires_at' => '',
        'linked_at' => '',
    ];

    if (!birkly_cloud_account_write_raw($empty)) {
        return ['ok' => false, 'error' => 'Failed to unlink cloud account'];
    }

    require_once __DIR__ . '/marketplace.php';
    birkly_marketplace_sync_owner_email(null);

    return ['ok' => true];
}
