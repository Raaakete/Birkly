<?php
/**
 * Local dev host detection for onboarding AI step skip.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../core/birkly_paths.php';

test_reset_failures();
echo "Local dev request detection tests\n\n";

$prevHost = $_SERVER['HTTP_HOST'] ?? null;

$_SERVER['HTTP_HOST'] = 'localhost:8080';
test_assert('localhost is local dev', birkly_is_local_dev_request() === true);

$_SERVER['HTTP_HOST'] = '127.0.0.1:8001';
test_assert('127.0.0.1 is local dev', birkly_is_local_dev_request() === true);

$_SERVER['HTTP_HOST'] = 'birkly.test';
test_assert('.test TLD is local dev', birkly_is_local_dev_request() === true);

$_SERVER['HTTP_HOST'] = 'example.com';
test_assert('public domain is not local dev', birkly_is_local_dev_request() === false);

$_SERVER['HTTP_HOST'] = 'cms.mycompany.com';
test_assert('production host is not local dev', birkly_is_local_dev_request() === false);

if ($prevHost === null) {
    unset($_SERVER['HTTP_HOST']);
} else {
    $_SERVER['HTTP_HOST'] = $prevHost;
}

$onboardingHtml = file_get_contents(dirname(__DIR__) . '/admin/templates/onboarding.html');
test_assert('onboarding template exposes ai-step data attribute', strpos($onboardingHtml, 'data-ai-step') !== false);
test_assert('onboarding template conditionally renders connect-ai step', strpos($onboardingHtml, '$showAiOnboardingStep') !== false);

$onboardingJs = file_get_contents(dirname(__DIR__) . '/admin/js/onboarding.js');
test_assert('onboarding.js skips AI step on localhost', strpos($onboardingJs, 'finishWithoutAiStep') !== false);

exit(test_finish('local_dev_request_test'));
