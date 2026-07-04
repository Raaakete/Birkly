<?php
/**
 * P5.7-M2 — Security settings enforcement tests (SEC-02–06).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/api/storage.php';
require_once dirname(__DIR__) . '/api/auth.php';
require_once dirname(__DIR__) . '/api/validate.php';
require_once dirname(__DIR__) . '/api/rate_limit.php';
require_once dirname(__DIR__) . '/core/csrf.php';
require_once dirname(__DIR__) . '/api/session.php';

test_reset_failures();
echo "Settings security tests\n\n";

$root = dirname(__DIR__);
$securityFile = $root . '/settings/security.json';
$backup = sys_get_temp_dir() . '/birkly-security-test-' . getmypid() . '.json';
$hadFile = is_file($securityFile);
if ($hadFile) {
    copy($securityFile, $backup);
}

file_put_contents($securityFile, json_encode([
    'password_min_length' => 8,
    'require_strong_password' => false,
    'max_login_attempts' => 3,
    'lockout_time' => 120,
    'enable_csrf' => false,
], JSON_PRETTY_PRINT));

// Reset cached security settings if any
if (function_exists('get_security_settings')) {
    $ref = new ReflectionFunction('get_security_settings');
    // static cache inside function — reload by including fresh read via file
}

$security = get_security_settings();
test_assert('SEC-02 max_login_attempts loaded', ($security['max_login_attempts'] ?? 0) === 3);
test_assert('SEC-03 lockout_time loaded', ($security['lockout_time'] ?? 0) === 120);

$weakPassword = 'short1';
$result = validate_password_strength($weakPassword);
test_assert('SEC-04 rejects password below min length', $result['valid'] === false);

$okPassword = 'longenough';
$resultOk = validate_password_strength($okPassword);
test_assert('SEC-05 accepts simple password when strong disabled', $resultOk['valid'] === true);

haru_secure_session_start();
$_SESSION['csrf_token'] = 'expected-token';
test_assert('SEC-06 CSRF disabled accepts missing token', validate_csrf_token('') === true);
test_assert('SEC-06 CSRF disabled accepts wrong token', validate_csrf_token('wrong') === true);

file_put_contents($securityFile, json_encode([
    'max_login_attempts' => 2,
    'lockout_time' => 60,
], JSON_PRETTY_PRINT));

$limiter = new RateLimiter('login_test_' . getmypid(), 2, 60);
test_assert('login attempt 1 allowed', $limiter->check() === true);
test_assert('login attempt 2 allowed', $limiter->check() === true);
test_assert('login attempt 3 blocked', $limiter->check() === false);

if ($hadFile) {
    copy($backup, $securityFile);
} else {
    @unlink($securityFile);
}
@unlink($backup);

exit(test_finish('settings_security_test'));
