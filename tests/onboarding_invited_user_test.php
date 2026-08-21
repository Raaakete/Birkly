<?php
/**
 * Invited users skip the install wizard and see the welcome modal on first dashboard visit.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$GLOBALS['__HARU_INTERNAL_CALL'] = true;

$failures = 0;

function assert_true(bool $cond, string $label): void
{
    global $failures;
    if ($cond) {
        echo "OK  $label\n";
        return;
    }
    echo "FAIL $label\n";
    $failures++;
}

require_once $root . '/core/birkly_paths.php';
require_once $root . '/api/storage.php';
require_once $root . '/api/onboarding.php';

$email = 'test-invited-' . bin2hex(random_bytes(4)) . '@example.com';
$userKey = normalizeOnboardingUserKey($email);
$userFile = $root . '/users/' . $userKey . '_onboarding.json';

try {
    assert_true('should redirect before init', shouldRedirectToOnboarding($email));

    birkly_init_invited_user_onboarding($email);

    assert_true('onboarding file created', is_file($userFile));

    $status = getOnboardingStatus($email);
    assert_true('wizard marked complete', !empty($status['setup_wizard_completed']));
    assert_true('invited flag set', !empty($status['invited_user']));
    assert_true('welcome modal pending', !empty($status['show_welcome_modal']));
    assert_true('no redirect after init', !shouldRedirectToOnboarding($email));
} finally {
    if (is_file($userFile)) {
        @unlink($userFile);
    }
}

echo PHP_EOL . 'onboarding_invited_user_test: ' . ($failures === 0 ? 'PASS' : "FAIL ($failures)") . PHP_EOL;
exit($failures === 0 ? 0 : 1);
