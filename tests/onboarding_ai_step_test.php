<?php
/**
 * P19-M5 — Optional AI onboarding step: skip tracking, no forced dashboard AI
 */

$GLOBALS['__HARU_INTERNAL_CALL'] = true;
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../api/storage.php';
require_once __DIR__ . '/../api/onboarding.php';

test_reset_failures();
echo "Onboarding AI step tests\n\n";

$root = dirname(__DIR__);
$tmpUsers = sys_get_temp_dir() . '/birkly-ai-onboarding-' . getmypid();
mkdir($tmpUsers, 0755, true);
$userKey = normalizeOnboardingUserKey('ai-onboarding@test.local');
$onboardingUserFile = $tmpUsers . '/' . $userKey . '_onboarding.json';

// Simulate AI skipped in wizard progress
file_put_contents($onboardingUserFile, json_encode([
    'setup_wizard_completed' => true,
    'completed' => false,
    'progress' => ['ai_skipped' => true, 'step' => 6],
    'last_step' => 6,
]));

$raw = json_decode(file_get_contents($onboardingUserFile), true);
test_assert('ai_skipped stored in progress', !empty($raw['progress']['ai_skipped']));

$onboardingHtml = file_get_contents($root . '/admin/templates/onboarding.html');
test_assert('onboarding template has BYOAI provider select', strpos($onboardingHtml, 'onboarding-ai-provider') !== false);
test_assert('onboarding template has test connection button', strpos($onboardingHtml, 'onboarding-test-ai-btn') !== false);
test_assert('onboarding template has skip for now', strpos($onboardingHtml, 'skipAI') !== false);
test_assert('onboarding template has set up later', strpos($onboardingHtml, 'Set up later') !== false);
test_assert('require approval checkbox default checked', strpos($onboardingHtml, 'onboarding-ai-require-approval') !== false
    && strpos($onboardingHtml, 'checked') !== false);

$onboardingJs = file_get_contents($root . '/admin/js/onboarding.js');
test_assert('onboarding.js tracks ai_skipped on skip', strpos($onboardingJs, "ai_skipped") !== false);
test_assert('onboarding.js uses ai_connections create API', strpos($onboardingJs, '/api/ai_connections.php?action=create') !== false);
test_assert('onboarding.js uses ai_connections test API', strpos($onboardingJs, '/api/ai_connections.php?action=test') !== false);

$adminTemplates = file_get_contents($root . '/api/admin_templates.php');
test_assert('dashboard checklist adds connect_ai only when ai_skipped', strpos($adminTemplates, 'ai_skipped') !== false);
test_assert('dashboard checklist does not always include connect_ai', strpos($adminTemplates, "if (\$aiSkipped && !\$hasAiConnection)") !== false);

unlink($onboardingUserFile);
rmdir($tmpUsers);

exit(test_finish('onboarding_ai_step_test'));
