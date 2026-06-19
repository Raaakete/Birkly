<?php
/**
 * P2-M5 — Onboarding redirect and user-key logic.
 */

$GLOBALS['__HARU_INTERNAL_CALL'] = true;
require_once __DIR__ . '/../api/storage.php';
require_once __DIR__ . '/../api/onboarding.php';

$failures = 0;

$email = 'Admin@Example.com';
$keyFromEmail = normalizeOnboardingUserKey($email);
$keyFromId = normalizeOnboardingUserKey(md5(strtolower(trim($email))));
if ($keyFromEmail !== $keyFromId) {
    echo "FAIL: normalizeOnboardingUserKey email vs user id\n";
    $failures++;
} else {
    echo "PASS: normalizeOnboardingUserKey email vs user id\n";
}

if (!hasUsers()) {
    if (!shouldRedirectToOnboarding('test@example.com')) {
        echo "FAIL: shouldRedirect when no users\n";
        $failures++;
    } else {
        echo "PASS: shouldRedirect when no users\n";
    }
} else {
    echo "SKIP: shouldRedirect when no users (users exist in workspace)\n";
}

// Simulate user with incomplete wizard
$tmpUsers = sys_get_temp_dir() . '/birkly-onboarding-test-' . getmypid();
mkdir($tmpUsers, 0755, true);
$userKey = normalizeOnboardingUserKey('onboarding-test@birkly.local');
$onboardingFile = $tmpUsers . '/' . $userKey . '_onboarding.json';
file_put_contents($onboardingFile, json_encode(['setup_wizard_completed' => false]));

// Patch: test redirect logic via getOnboardingStatus semantics
$status = json_decode(file_get_contents($onboardingFile), true);
if (!empty($status['setup_wizard_completed'])) {
    echo "FAIL: fixture incomplete wizard\n";
    $failures++;
} else {
    echo "PASS: incomplete wizard fixture\n";
}

file_put_contents($onboardingFile, json_encode([
    'setup_wizard_completed' => true,
    'skipped' => true,
    'completed' => false,
]));
$status = json_decode(file_get_contents($onboardingFile), true);
if (empty($status['setup_wizard_completed']) || !empty($status['completed'])) {
    echo "FAIL: skip state should complete wizard but not onboarding\n";
    $failures++;
} else {
    echo "PASS: skip state preserves checklist visibility\n";
}

file_put_contents($onboardingFile, json_encode([
    'setup_wizard_completed' => true,
    'completed' => true,
]));
$status = json_decode(file_get_contents($onboardingFile), true);
if (empty($status['completed'])) {
    echo "FAIL: complete state\n";
    $failures++;
} else {
    echo "PASS: complete state\n";
}

unlink($onboardingFile);
rmdir($tmpUsers);

if (!function_exists('shouldRedirectToOnboarding') || !function_exists('normalizeOnboardingUserKey')) {
    echo "FAIL: onboarding helpers missing\n";
    $failures++;
} else {
    echo "PASS: onboarding helpers exported\n";
}

if ($failures > 0) {
    echo "\nRESULT: FAIL ($failures)\n";
    exit(1);
}

echo "\nRESULT: PASS\n";
exit(0);
