<?php
/**
 * Login CSRF must survive LiteSpeed/Apache internal rewrites.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/http_test_helpers.php';

test_reset_failures();
echo "Login CSRF rewrite tests\n\n";

if (!function_exists('curl_init')) {
    fwrite(STDERR, "login_csrf_rewrite_test: curl extension required\n");
    exit(1);
}

$root = dirname(__DIR__);
$fixtures = __DIR__ . '/fixtures';
$port = (int) (getenv('LOGIN_CSRF_PORT') ?: 18087);
$base = "http://127.0.0.1:$port";
$usersDir = $root . '/users';
$backupUsers = sys_get_temp_dir() . '/birkly-login-csrf-users-' . getmypid();
$cookieFile = sys_get_temp_dir() . '/birkly-login-csrf-cookies-' . getmypid() . '.txt';
$testEmail = 'login-csrf-' . getmypid() . '@birkly.test';
$testPassword = 'SecurePass123!';

$cmd = sprintf(
    'cd %s && php -S 127.0.0.1:%d -t docroot rewrite_router.php',
    escapeshellarg($fixtures),
    $port
);
$spec = [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']];
$proc = proc_open($cmd, $spec, $pipes);
if (!is_resource($proc)) {
    fwrite(STDERR, "login_csrf_rewrite_test: failed to start rewrite server\n");
    exit(1);
}
$serverPid = (int) (proc_get_status($proc)['pid'] ?? 0);
usleep(300000);

try {
    birkly_test_backup_directory($usersDir, $backupUsers);
    birkly_test_clear_json_files($usersDir);

    $create = birkly_test_http_request($base . '/Birkly-main/api/onboarding.php?action=create_first_user', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'name' => 'Login CSRF',
            'email' => $testEmail,
            'password' => $testPassword,
        ]),
        'cookie_file' => $cookieFile,
    ]);
    test_assert('seed user for login test', ($create['code'] ?? 0) === 200);

    @unlink($cookieFile);
    touch($cookieFile);

    $loginGet = birkly_test_http_request($base . '/Birkly-main/admin/login.php', [
        'cookie_file' => $cookieFile,
    ]);
    test_assert('login page loads under rewrite sim', ($loginGet['code'] ?? 0) === 200, 'code ' . ($loginGet['code'] ?? 0));

    preg_match('/name="_csrf"\s+value="([^"]+)"/', $loginGet['body'], $csrfMatch);
    $loginCsrf = $csrfMatch[1] ?? '';
    test_assert('csrf token present under rewrite sim', $loginCsrf !== '');

    test_assert(
        'cms session cookie scoped to install path',
        strpos($loginGet['headers'], 'birkly_cms_sid=') !== false
            && strpos($loginGet['headers'], 'path=/Birkly-main/') !== false,
        substr($loginGet['headers'], 0, 500)
    );

    $loginPost = birkly_test_http_request($base . '/Birkly-main/admin/login.php', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
        'body' => http_build_query([
            'email' => $testEmail,
            'password' => $testPassword,
            '_csrf' => $loginCsrf,
        ]),
        'cookie_file' => $cookieFile,
    ]);

    test_assert(
        'login POST accepts csrf under rewrite sim',
        stripos($loginPost['body'], 'Invalid security token') === false,
        substr($loginPost['body'], 0, 300)
    );
    test_assert(
        'login POST redirects to dashboard',
        ($loginPost['code'] ?? 0) === 302
            && stripos($loginPost['headers'], 'page=dashboard') !== false,
        'code ' . ($loginPost['code'] ?? 0) . ' headers ' . substr($loginPost['headers'], 0, 200)
    );
} finally {
    birkly_test_restore_directory($usersDir, $backupUsers);
    birkly_test_remove_directory($backupUsers);
    @unlink($cookieFile);
    if ($serverPid > 0 && function_exists('posix_kill')) {
        posix_kill($serverPid, SIGTERM);
        usleep(200000);
    }
}

exit(test_finish('login_csrf_rewrite_test'));
