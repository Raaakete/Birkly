<?php
/**
 * Security policy 2FA grace logic tests (P27-M15).
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Security policy 2FA tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/core/security_policy.php';

$securityFile = $root . '/settings/security.json';
$backup = sys_get_temp_dir() . '/birkly-security-2fa-test-' . getmypid() . '.json';
$hadFile = is_file($securityFile);
if ($hadFile) {
    copy($securityFile, $backup);
}

file_put_contents($securityFile, json_encode([
    'require_2fa' => true,
    'require_2fa_grace_days' => 7,
], JSON_PRETTY_PRINT));
birkly_security_policy_reset_cache();

test_assert('require_2fa enabled', birkly_require_2fa_enabled() === true);
test_assert('grace days default from file', birkly_require_2fa_grace_days() === 7);

$userNo2fa = ['id' => 'test-user', 'email' => 'grace@test.com'];
$userNo2fa = birkly_apply_2fa_policy_to_user($userNo2fa);
test_assert('apply sets grace until', !empty($userNo2fa['2fa_grace_until']));
test_assert('user in grace after apply', birkly_user_in_2fa_grace($userNo2fa) === true);
test_assert('user not blocked in grace', birkly_user_2fa_blocked($userNo2fa) === false);
test_assert('grace days left positive', birkly_user_2fa_grace_days_left($userNo2fa) >= 1);

$userEnabled = ['id' => 'enabled', 'email' => 'ok@test.com', '2fa' => ['enabled' => true]];
test_assert('2fa enabled user not blocked', birkly_user_2fa_blocked($userEnabled) === false);
test_assert('2fa enabled user not in grace', birkly_user_in_2fa_grace($userEnabled) === false);

$userExpired = [
    'id' => 'expired',
    'email' => 'expired@test.com',
    '2fa_grace_until' => date('c', strtotime('-1 day')),
];
test_assert('expired grace not in grace', birkly_user_in_2fa_grace($userExpired) === false);
test_assert('expired grace blocked', birkly_user_2fa_blocked($userExpired) === true);

file_put_contents($securityFile, json_encode(['require_2fa' => false], JSON_PRETTY_PRINT));
birkly_security_policy_reset_cache();
test_assert('policy off not blocked', birkly_user_2fa_blocked($userExpired) === false);

if ($hadFile) {
    copy($backup, $securityFile);
} else {
    @unlink($securityFile);
}
@unlink($backup);
birkly_security_policy_reset_cache();

exit(test_finish('security_policy_2fa_test'));
