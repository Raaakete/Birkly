<?php
/**
 * demo.php must include onboarding.php with __HARU_INTERNAL_CALL set,
 * otherwise install_starter responses are overwritten with "Invalid action".
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Demo API onboarding include tests\n\n";

$demoPhp = file_get_contents(dirname(__DIR__) . '/api/demo.php');
test_assert('demo.php defines onboarding include helper', strpos($demoPhp, 'birkly_demo_load_onboarding_helpers') !== false);
test_assert('install_starter uses onboarding include helper', strpos($demoPhp, "case 'install_starter'") !== false
    && preg_match("/case 'install_starter':[\s\S]*?birkly_demo_load_onboarding_helpers\(\)/", $demoPhp) === 1);

$_GET['action'] = 'install_starter';
$_SESSION['user'] = ['email' => 'include-guard@test.local', 'role' => 'admin', 'id' => md5('include-guard@test.local')];
$GLOBALS['__HARU_INTERNAL_CALL'] = true;
ob_start();
require_once dirname(__DIR__) . '/api/onboarding.php';
$output = ob_get_clean();
test_assert('onboarding internal include emits no API output', $output === '');

unset($GLOBALS['__HARU_INTERNAL_CALL'], $_GET['action'], $_SESSION['user']);

exit(test_finish('demo_api_onboarding_include_test'));
