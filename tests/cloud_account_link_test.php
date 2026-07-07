<?php
/**
 * P13-M1 — Cloud account optional link tests (mock OAuth, unlink, local login).
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/http_test_helpers.php';
require_once dirname(__DIR__) . '/core/cloud_account.php';
require_once dirname(__DIR__) . '/core/marketplace.php';

test_reset_failures();
echo "Cloud account link tests\n\n";

if (!function_exists('curl_init')) {
    fwrite(STDERR, "cloud_account_link_test: curl extension required\n");
    exit(1);
}

$root = dirname(__DIR__);
$port = 18000 + (getmypid() % 1000);
$base = "http://127.0.0.1:$port";
$settingsDir = sys_get_temp_dir() . '/birkly-cloud-account-' . getmypid();
$usersDir = $root . '/users';
$backupUsers = sys_get_temp_dir() . '/birkly-cloud-users-' . getmypid();
$cookieFile = sys_get_temp_dir() . '/birkly-cloud-cookies-' . getmypid() . '.txt';

$testEmail = 'cloud-link-' . getmypid() . '@birkly.test';
$testPassword = 'SecurePass123!';
$cloudEmail = 'cloud-owner-' . getmypid() . '@birkly.cloud';

@mkdir($settingsDir, 0755, true);
$GLOBALS['__birkly_cloud_account_settings_dir'] = $settingsDir;
$GLOBALS['__birkly_marketplace_settings_dir'] = $settingsDir;

$realSettingsDir = $root . '/settings';
$realCloudFile = $realSettingsDir . '/cloud_account.json';
$realMpFile = $realSettingsDir . '/marketplace.json';
$backupCloud = sys_get_temp_dir() . '/birkly-cloud-backup-' . getmypid() . '.json';
$backupMp = sys_get_temp_dir() . '/birkly-mp-backup-' . getmypid() . '.json';

putenv('BIRKLY_CLOUD_ACCOUNT_MOCK=1');
putenv('BIRKLY_CLOUD_ACCOUNT_MOCK_EMAIL=' . $cloudEmail);

$serverPid = birkly_test_http_server_start($root, $port);

try {
    birkly_test_backup_directory($usersDir, $backupUsers);
    birkly_test_clear_json_files($usersDir);

    // Unit: store link, marketplace sync, unlink
    birkly_cloud_account_reset();
    test_assert('initially not linked', birkly_cloud_account_is_linked() === false);

    $stored = birkly_cloud_account_store_link([
        'email' => $cloudEmail,
        'access_token' => 'mock_access_unit',
        'refresh_token' => 'mock_refresh_unit',
        'expires_in' => 3600,
    ]);
    test_assert('link stores account', !empty($stored['ok']) && ($stored['email'] ?? '') === $cloudEmail);
    test_assert('status shows linked email', birkly_cloud_account_status()['email'] === $cloudEmail);

    birkly_marketplace_settings_reset();
    test_assert(
        'marketplace owner email synced on link',
        birkly_marketplace_installation_owner_email() === $cloudEmail
    );

    $raw = birkly_cloud_account_read_raw();
    test_assert('tokens stored server-side', ($raw['access_token'] ?? '') === 'mock_access_unit');
    test_assert('status omits tokens', !array_key_exists('access_token', birkly_cloud_account_status()));

    $unlinked = birkly_cloud_account_unlink();
    test_assert('unlink succeeds', !empty($unlinked['ok']));
    test_assert('not linked after unlink', birkly_cloud_account_is_linked() === false);
    birkly_marketplace_settings_reset();
    test_assert(
        'marketplace owner email cleared on unlink',
        birkly_marketplace_installation_owner_email() === null
    );

    // HTTP tests use live settings (separate PHP server process).
    unset($GLOBALS['__birkly_cloud_account_settings_dir'], $GLOBALS['__birkly_marketplace_settings_dir']);
    birkly_cloud_account_reset();
    birkly_marketplace_settings_reset();

    if (is_file($realCloudFile)) {
        copy($realCloudFile, $backupCloud);
    }
    if (is_file($realMpFile)) {
        copy($realMpFile, $backupMp);
    }
    @unlink($realCloudFile);
    if (is_file($realMpFile)) {
        $mpExisting = json_decode((string) file_get_contents($realMpFile), true);
        if (is_array($mpExisting)) {
            unset($mpExisting['installation_owner_email']);
            file_put_contents($realMpFile, json_encode($mpExisting, JSON_PRETTY_PRINT));
        }
    }

    @unlink($cookieFile);
    touch($cookieFile);

    $create = birkly_test_http_request($base . '/api/onboarding.php?action=create_first_user', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'name' => 'Cloud Link Admin',
            'email' => $testEmail,
            'password' => $testPassword,
        ]),
        'cookie_file' => $cookieFile,
    ]);
    test_assert('seed admin user', ($create['code'] ?? 0) === 200);

    $statusUnauth = birkly_test_http_request($base . '/api/cloud_account.php?action=status');
    test_assert('status requires auth', ($statusUnauth['code'] ?? 0) === 401);

    $loginGet = birkly_test_http_request($base . '/admin/login.php', ['cookie_file' => $cookieFile]);
    preg_match('/name="_csrf"\s+value="([^"]+)"/', $loginGet['body'], $csrfMatch);
    if (($csrfMatch[1] ?? '') === '') {
        preg_match('/name=\'_csrf\'\s+value=\'([^\']+)\'/', $loginGet['body'], $csrfMatch);
    }
    $loginCsrf = $csrfMatch[1] ?? '';

    $loginPost = birkly_test_http_request($base . '/admin/login.php', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
        'body' => http_build_query(array_filter([
            'email' => $testEmail,
            'password' => $testPassword,
            '_csrf' => $loginCsrf !== '' ? $loginCsrf : null,
        ])),
        'cookie_file' => $cookieFile,
        'follow' => false,
    ]);
    test_assert('local login works without cloud link', in_array($loginPost['code'] ?? 0, [302, 303], true));

    $adminGet = birkly_test_http_request($base . '/admin/index.php', ['cookie_file' => $cookieFile]);
    preg_match('/meta name="csrf-token" content="([^"]+)"/', $adminGet['body'], $adminCsrfMatch);
    $apiCsrf = $adminCsrfMatch[1] ?? '';
    test_assert('admin session active', ($adminGet['code'] ?? 0) === 200 && $apiCsrf !== '');

    $startLink = birkly_test_http_request($base . '/api/cloud_account.php?action=start_link', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode(['_csrf' => $apiCsrf]),
        'cookie_file' => $cookieFile,
    ]);
    $startData = json_decode($startLink['body'] ?? '', true);
    test_assert('start_link returns authorization_url', ($startLink['code'] ?? 0) === 200 && !empty($startData['data']['authorization_url']));

    $authUrl = (string) ($startData['data']['authorization_url'] ?? '');
    if (str_starts_with($authUrl, '/')) {
        $authUrl = $base . $authUrl;
    }
    parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $authQuery);
    test_assert(
        'mock auth url includes code and state',
        ($authQuery['code'] ?? '') !== '' && ($authQuery['state'] ?? '') !== ''
    );

    $callback = birkly_test_http_request($authUrl, [
        'cookie_file' => $cookieFile,
        'follow' => false,
    ]);
    test_assert(
        'callback redirects to account page',
        in_array($callback['code'] ?? 0, [302, 303], true)
            && (str_contains($callback['headers'] ?? '', 'page=account') || str_contains($callback['headers'] ?? '', 'cloud_linked=1'))
    );

    $statusLinked = birkly_test_http_request($base . '/api/cloud_account.php?action=status', [
        'cookie_file' => $cookieFile,
    ]);
    $statusData = json_decode($statusLinked['body'] ?? '', true);
    $linkedOk = ($statusLinked['code'] ?? 0) === 200
        && !empty($statusData['data']['linked'])
        && ($statusData['data']['email'] ?? '') === $cloudEmail;
    test_assert(
        'status shows linked after callback',
        $linkedOk
    );

    $mpConfig = birkly_test_http_request($base . '/api/marketplace.php?action=config', [
        'cookie_file' => $cookieFile,
    ]);
    $mpData = json_decode($mpConfig['body'] ?? '', true);
    test_assert(
        'marketplace config exposes linked owner email',
        ($mpData['data']['installation_owner_email'] ?? '') === $cloudEmail
    );

    $unlink = birkly_test_http_request($base . '/api/cloud_account.php?action=unlink', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode(['_csrf' => $apiCsrf]),
        'cookie_file' => $cookieFile,
    ]);
    $unlinkData = json_decode($unlink['body'] ?? '', true);
    test_assert('unlink via API', ($unlink['code'] ?? 0) === 200 && empty($unlinkData['data']['linked']));

    @unlink($cookieFile);
    touch($cookieFile);
    $loginGet2 = birkly_test_http_request($base . '/admin/login.php', ['cookie_file' => $cookieFile]);
    preg_match('/name="_csrf"\s+value="([^"]+)"/', $loginGet2['body'], $csrfMatch2);
    $loginPost2 = birkly_test_http_request($base . '/admin/login.php', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
        'body' => http_build_query(array_filter([
            'email' => $testEmail,
            'password' => $testPassword,
            '_csrf' => ($csrfMatch2[1] ?? '') !== '' ? $csrfMatch2[1] : null,
        ])),
        'cookie_file' => $cookieFile,
        'follow' => false,
    ]);
    test_assert('local login works after unlink', in_array($loginPost2['code'] ?? 0, [302, 303], true));
} finally {
    birkly_test_http_server_stop($serverPid);
    birkly_test_restore_directory($usersDir, $backupUsers);

    if (is_file($backupCloud)) {
        copy($backupCloud, $realCloudFile);
        @unlink($backupCloud);
    } else {
        @unlink($realCloudFile);
    }
    if (is_file($backupMp)) {
        copy($backupMp, $realMpFile);
        @unlink($backupMp);
    }

    @unlink($cookieFile);
    putenv('BIRKLY_CLOUD_ACCOUNT_MOCK');
    putenv('BIRKLY_CLOUD_ACCOUNT_MOCK_EMAIL');
    birkly_cloud_account_reset();
    birkly_marketplace_settings_reset();
    array_map('unlink', glob($settingsDir . '/*') ?: []);
    @rmdir($settingsDir);
}

exit(test_finish('Cloud account link tests'));
