<?php
/**
 * P16-M5 — CSP nonce present on admin shell.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/http_test_helpers.php';

test_reset_failures();
echo "CSP nonce tests\n\n";

if (!function_exists('curl_init')) {
    fwrite(STDERR, "csp_nonce_test: curl extension required\n");
    exit(1);
}

$root = dirname(__DIR__);
$port = 18100 + (getmypid() % 1000);
$base = "http://127.0.0.1:$port";
$usersDir = $root . '/users';
$backupUsers = sys_get_temp_dir() . '/birkly-csp-users-' . getmypid();
$cookieFile = sys_get_temp_dir() . '/birkly-csp-cookies-' . getmypid() . '.txt';

$testEmail = 'csp-nonce-' . getmypid() . '@birkly.test';
$testPassword = 'SecurePass123!';

$serverPid = birkly_test_http_server_start($root, $port);

try {
    birkly_test_backup_directory($usersDir, $backupUsers);
    birkly_test_clear_json_files($usersDir);

    @unlink($cookieFile);
    touch($cookieFile);

    $create = birkly_test_http_request($base . '/api/onboarding.php?action=create_first_user', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'name' => 'CSP Nonce Admin',
            'email' => $testEmail,
            'password' => $testPassword,
        ]),
        'cookie_file' => $cookieFile,
    ]);
    test_assert('seed admin user', ($create['code'] ?? 0) === 200);

    $loginGet = birkly_test_http_request($base . '/admin/login.php', ['cookie_file' => $cookieFile]);
    preg_match('/name="_csrf"\s+value="([^"]+)"/', $loginGet['body'], $csrfMatch);
    $loginCsrf = $csrfMatch[1] ?? '';

    $loginPage = birkly_test_http_request($base . '/admin/login.php');
    $loginHeaders = $loginPage['headers'] ?? '';
    test_assert(
        'login CSP header uses nonce',
        is_string($loginHeaders)
            && preg_match("/Content-Security-Policy:.*'nonce-[^']+'/i", $loginHeaders) === 1
    );
    test_assert(
        'login CSP omits unsafe-inline for scripts when nonce set',
        is_string($loginHeaders)
            && !preg_match("/script-src[^;]*'unsafe-inline'/i", $loginHeaders)
    );
    test_assert(
        'login inline script has nonce attribute',
        preg_match('/<script[^>]*\snonce="[^"]+"/', $loginPage['body'] ?? '') === 1
    );

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
    test_assert('login succeeds', in_array($loginPost['code'] ?? 0, [302, 303], true));

    $adminGet = birkly_test_http_request($base . '/admin/index.php', ['cookie_file' => $cookieFile]);
    $adminHeaders = $adminGet['headers'] ?? '';
    test_assert(
        'admin shell CSP header uses nonce',
        is_string($adminHeaders)
            && preg_match("/Content-Security-Policy:.*'nonce-[^']+'/i", $adminHeaders) === 1
    );
    test_assert(
        'admin layout script tags include nonce attribute',
        preg_match('/<script[^>]+nonce="[^"]+"/', $adminGet['body'] ?? '') === 1
    );

    @unlink($cookieFile);
} finally {
    birkly_test_http_server_stop($serverPid);
    birkly_test_restore_directory($usersDir, $backupUsers);
    @unlink($cookieFile);
}

exit(test_finish('CSP nonce tests'));
