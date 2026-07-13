<?php
/**
 * J-INSTALL — Fresh CMS owner (release zip extract → onboarding → dashboard).
 */

require_once __DIR__ . '/_helpers.php';

return [
    'id' => 'J-INSTALL',
    'name' => 'Fresh CMS owner',
    'server' => 'isolated',
    'run' => function (array $ctx): void {
        $extractInfo = journey_build_release_extract();
        $extractDir = $extractInfo['dir'];
        $port = journey_allocate_port(80);
        $base = "http://127.0.0.1:$port";
        $pid = 0;

        try {
            $startHere = is_file($extractDir . '/START_HERE.html')
                ? $extractDir . '/START_HERE.html'
                : $extractDir . '/0_START-HERE.html';
            journey_assert('START_HERE exists in release extract', is_file($startHere), $startHere);

            $pid = journey_start_cms_server($extractDir, $port);
            $cookieFile = journey_temp_cookie_file('install');

            $login = journey_http($base, '/admin/login.php');
            journey_assert('admin login page reachable', in_array($login['code'], [200, 302], true), 'code ' . $login['code']);
            if ($login['code'] === 200) {
                journey_assert('login form present', stripos($login['body'], 'login') !== false);
            }

            $email = 'journey-install-' . getmypid() . '@birkly.test';
            $password = 'SecurePass123!';
            journey_create_first_admin($base, $cookieFile, $email, $password, 'Journey Install');

            journey_onboarding_skip($base, $cookieFile);

            $status = journey_http($base, '/api/onboarding.php?action=status', ['cookie_file' => $cookieFile]);
            $statusData = birkly_test_json_decode($status['body']);
            journey_assert(
                'setup_wizard_completed after skip',
                !empty($statusData['data']['setup_wizard_completed']) || !empty($statusData['setup_wizard_completed'])
            );

            $dashboard = journey_http($base, '/admin/index.php?page=dashboard', ['cookie_file' => $cookieFile]);
            journey_assert('dashboard HTTP 200', $dashboard['code'] === 200);

            $onboardingStatus = journey_http($base, '/api/onboarding.php?action=status', ['cookie_file' => $cookieFile]);
            $statusData = birkly_test_json_decode($onboardingStatus['body']);
            journey_assert('onboarding status ok', ($statusData['status'] ?? '') === 'ok', $onboardingStatus['body']);
            journey_assert(
                'setup_wizard_completed in status',
                !empty($statusData['data']['setup_wizard_completed']) || !empty($statusData['setup_wizard_completed'])
            );
        } finally {
            if ($pid > 0) {
                journey_stop_cms_server($pid);
            }
            if (isset($extractInfo['zip']) && is_file($extractInfo['zip'])) {
                @unlink($extractInfo['zip']);
            }
            if (isset($extractDir)) {
                journey_remove_tree($extractDir);
            }
            if (isset($cookieFile)) {
                @unlink($cookieFile);
            }
        }
    },
];
