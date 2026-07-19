<?php
/**
 * P4-M2-T1 — Onboarding E2E: first user → CSRF → project save → skip → admin pages.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/http_test_helpers.php';

test_reset_failures();
echo "Onboarding E2E tests\n\n";

if (!function_exists('curl_init')) {
    fwrite(STDERR, "onboarding_e2e_test: curl extension required\n");
    exit(1);
}

$root = dirname(__DIR__);
$port = (int) (getenv('ONBOARDING_E2E_PORT') ?: 18082);
$usersDir = $root . '/users';
$settingsDir = $root . '/settings';
$backupUsers = sys_get_temp_dir() . '/birkly-e2e-users-' . getmypid();
$backupSettings = sys_get_temp_dir() . '/birkly-e2e-settings-' . getmypid();
$cookieFile = sys_get_temp_dir() . '/birkly-e2e-cookies-' . getmypid() . '.txt';
$testEmail = 'onboarding-e2e-' . getmypid() . '@birkly.test';
$testPassword = 'SecurePass123!';

$serverPid = birkly_test_http_server_start($root, $port);
$port = birkly_test_http_server_port();
$base = "http://127.0.0.1:$port";

try {
    birkly_test_backup_directory($usersDir, $backupUsers);
    birkly_test_backup_directory($settingsDir, $backupSettings);
    birkly_test_clear_json_files($usersDir);

  $adminSettingsPath = $settingsDir . '/admin.json';
    $adminBackup = is_file($adminSettingsPath) ? file_get_contents($adminSettingsPath) : null;
    if (is_file($adminSettingsPath)) {
        unlink($adminSettingsPath);
    }

    $create = birkly_test_http_request($base . '/api/onboarding.php?action=create_first_user', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'name' => 'Onboarding E2E',
            'email' => $testEmail,
            'password' => $testPassword,
        ]),
        'cookie_file' => $cookieFile,
    ]);
    $createData = birkly_test_json_decode($create['body']);
    test_assert('create_first_user HTTP 200', $create['code'] === 200, 'code ' . $create['code']);
    test_assert('create_first_user status ok', ($createData['status'] ?? '') === 'ok', $create['body']);

    $dashboardProbe = birkly_test_http_request($base . '/admin/index.php?page=dashboard', [
        'cookie_file' => $cookieFile,
    ]);
    test_assert('dashboard reachable after create_first_user', $dashboardProbe['code'] === 200);
    test_assert('session kept after create (not login page)', stripos($dashboardProbe['body'], 'Welcome Back') === false);

    $loginCookieFile = sys_get_temp_dir() . '/birkly-e2e-login-cookies-' . getmypid() . '.txt';
    @unlink($loginCookieFile);
    touch($loginCookieFile);

    $loginGet = birkly_test_http_request($base . '/admin/login.php', ['cookie_file' => $loginCookieFile]);
    test_assert('login page HTTP 200', $loginGet['code'] === 200, 'code ' . $loginGet['code']);
    preg_match('/name="_csrf"\s+value="([^"]+)"/', $loginGet['body'], $csrfMatch);
    $loginCsrf = $csrfMatch[1] ?? '';
    test_assert('login page csrf present', $loginCsrf !== '');

    $loginPost = birkly_test_http_request($base . '/admin/login.php', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
        'body' => http_build_query([
            'email' => $testEmail,
            'password' => $testPassword,
            '_csrf' => $loginCsrf,
        ]),
        'cookie_file' => $loginCookieFile,
    ]);
    test_assert(
        'login POST accepts csrf token',
        stripos($loginPost['body'], 'Invalid security token') === false,
        substr($loginPost['body'], 0, 300)
    );

    $csrfResponse = birkly_test_http_request($base . '/api/csrf.php', [
        'cookie_file' => $cookieFile,
        'headers' => ['Referer: ' . $base . '/admin/index.php?page=onboarding'],
    ]);
    $csrfData = birkly_test_json_decode($csrfResponse['body']);
    $csrfToken = $csrfData['data']['csrf_token'] ?? '';
    test_assert('csrf token issued for admin session', $csrfToken !== '', $csrfResponse['body']);

    $save = birkly_test_http_request($base . '/api/settings.php?action=update', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'action' => 'update',
            'category' => 'admin',
            'settings' => [
                'project_name' => 'E2E Test Project',
                'project_description' => 'Automated onboarding regression',
            ],
            '_csrf' => $csrfToken,
        ]),
        'cookie_file' => $cookieFile,
    ]);
    $saveData = birkly_test_json_decode($save['body']);
    test_assert('project info save HTTP 200', $save['code'] === 200, 'code ' . $save['code']);
    test_assert('project info save status ok', ($saveData['status'] ?? '') === 'ok', $save['body']);

    $savedAdmin = json_decode(@file_get_contents($adminSettingsPath) ?: '{}', true);
    test_assert(
        'project_name persisted to admin.json',
        ($savedAdmin['project_name'] ?? '') === 'E2E Test Project'
    );

    $skip = birkly_test_http_request($base . '/api/onboarding.php?action=skip', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => '{}',
        'cookie_file' => $cookieFile,
    ]);
    $skipData = birkly_test_json_decode($skip['body']);
    test_assert('onboarding skip status ok', ($skipData['status'] ?? '') === 'ok', $skip['body']);

    $onboardingPage = birkly_test_http_request($base . '/admin/index.php?page=onboarding', [
        'cookie_file' => $cookieFile,
    ]);
    test_assert('onboarding template loads', $onboardingPage['code'] === 200);
    test_assert('onboarding.css linked', strpos($onboardingPage['body'], '/admin/css/onboarding.css') !== false);
    test_assert('onboarding page wrapper present', strpos($onboardingPage['body'], 'onboarding-page') !== false);

    // P19 wizard v3 steps
    test_assert('onboarding has welcome step (merged install info)', strpos($onboardingPage['body'], 'step-welcome') !== false);
    test_assert('onboarding has starter-templates step', strpos($onboardingPage['body'], 'step-starter-templates') !== false);
    test_assert('onboarding has no marketplace step', strpos($onboardingPage['body'], 'step-marketplace') === false);
    test_assert('onboarding has blog starter option', strpos($onboardingPage['body'], 'data-starter="blog"') !== false);
    test_assert('onboarding marks shop as coming soon', strpos($onboardingPage['body'], 'starter-coming-soon') !== false);

    // P19-M3: install path query param
    $onboardingLocal = birkly_test_http_request($base . '/admin/index.php?page=onboarding&install=local', [
        'cookie_file' => $cookieFile,
    ]);
    test_assert('onboarding accepts install=local param', $onboardingLocal['code'] === 200);
    test_assert('onboarding page has install path data attr or handler', strpos($onboardingLocal['body'], 'install') !== false);

    $contentPage = birkly_test_http_request($base . '/admin/index.php?page=content', [
        'cookie_file' => $cookieFile,
    ]);
    test_assert('content page HTTP 200', $contentPage['code'] === 200);
    test_assert('content page renders content-page', strpos($contentPage['body'], 'content-page') !== false);
    test_assert('content page has collections container', strpos($contentPage['body'], 'collections-container') !== false);

    $mediaPage = birkly_test_http_request($base . '/admin/index.php?page=media', [
        'cookie_file' => $cookieFile,
    ]);
    test_assert('media page HTTP 200', $mediaPage['code'] === 200);
    test_assert('media page renders media-layout', strpos($mediaPage['body'], 'media-layout') !== false);

    $css = birkly_test_http_request($base . '/admin/css/onboarding.css');
    test_assert('onboarding.css served', $css['code'] === 200);
    test_assert('onboarding.css has container rules', strpos($css['body'], '.onboarding-container') !== false);

    // P19-M5: optional AI wizard step — shown on web servers, hidden on localhost
    test_assert('onboarding exposes ai-step data attribute', strpos($onboardingPage['body'], 'data-ai-step') !== false);
    $aiStepOnPage = strpos($onboardingPage['body'], 'data-ai-step="1"') !== false;
    if ($aiStepOnPage) {
        test_assert('onboarding has connect-ai step element', strpos($onboardingPage['body'], 'step-connect-ai') !== false);
        test_assert('onboarding has BYOAI provider field', strpos($onboardingPage['body'], 'onboarding-ai-provider') !== false);
        test_assert(
            'connect-ai step is optional (skippable)',
            strpos($onboardingPage['body'], 'data-onboarding-action="skip-ai"') !== false
                || strpos($onboardingPage['body'], 'skip_for_now') !== false
        );
    } else {
        test_assert('connect-ai step omitted on localhost', strpos($onboardingPage['body'], 'step-connect-ai') === false);
        test_assert('onboarding.js can finish without AI step', strpos(file_get_contents($root . '/admin/js/onboarding.js'), 'finishWithoutAiStep') !== false);
    }

    // P19-M6: Dashboard checklist essential only; AI only if skipped
    $dashboardPageChecklist = birkly_test_http_request($base . '/admin/index.php?page=dashboard', [
        'cookie_file' => $cookieFile,
    ]);
    test_assert('dashboard checklist page HTTP 200', $dashboardPageChecklist['code'] === 200);

    test_assert(
        'dashboard checklist does not contain upload_media',
        strpos($dashboardPageChecklist['body'], 'upload_media') === false,
        'upload_media found in dashboard — must be removed per D3'
    );
    test_assert(
        'dashboard checklist does not contain create_template',
        strpos($dashboardPageChecklist['body'], 'create_template') === false,
        'create_template found in dashboard — must be removed per D3'
    );
    test_assert(
        'dashboard checklist does not contain configure_settings',
        strpos($dashboardPageChecklist['body'], 'configure_settings') === false
    );
    test_assert(
        'dashboard checklist does not contain AI item after skip wizard (no ai_skipped yet)',
        strpos($dashboardPageChecklist['body'], 'connect_ai') === false
            && strpos($dashboardPageChecklist['body'], 'connect-ai') === false,
        'AI checklist item must not appear unless user skipped wizard AI step'
    );
    test_assert(
        'dashboard template has hide checklist control',
        strpos(file_get_contents($root . '/admin/templates/dashboard.html'), 'hide-checklist-btn') !== false
    );

    // M2-T3: Birkly.url() used in onboarding JS (no bare /api/ fetch paths)
    $onboardingJs = birkly_test_http_request($base . '/admin/js/onboarding.js');
    test_assert('onboarding.js served', $onboardingJs['code'] === 200);
    test_assert(
        'onboarding.js uses Birkly.url() for API calls',
        strpos($onboardingJs['body'], "Birkly.url('/api/") !== false,
        'Birkly.url() not found in onboarding.js'
    );
    // Ensure no bare fetch('/api/...') remain (all must go through Birkly.url)
    preg_match_all("/fetch\s*\(\s*'\/api\//", $onboardingJs['body'], $bareMatches);
    test_assert(
        'onboarding.js has no bare /api/ fetch paths',
        count($bareMatches[0]) === 0,
        'Found bare /api/ fetch: ' . implode(', ', $bareMatches[0])
    );

    // M2-T2: visual redesign — progress track and step dots present
    test_assert(
        'onboarding.css has progress-track (redesigned progress bar)',
        strpos($css['body'], '.progress-track') !== false
    );
    test_assert(
        'onboarding.css has step-icon-badge (redesigned step header)',
        strpos($css['body'], '.step-icon-badge') !== false
    );
} finally {
    birkly_test_restore_directory($usersDir, $backupUsers);
    birkly_test_restore_directory($settingsDir, $backupSettings);

    if (isset($adminBackup)) {
        if ($adminBackup === null && is_file($settingsDir . '/admin.json')) {
            unlink($settingsDir . '/admin.json');
        } elseif ($adminBackup !== null) {
            file_put_contents($settingsDir . '/admin.json', $adminBackup);
        }
    }

    birkly_test_remove_directory($backupUsers);
    birkly_test_remove_directory($backupSettings);
    @unlink($cookieFile);
    if (isset($loginCookieFile)) {
        @unlink($loginCookieFile);
    }
    birkly_test_http_server_stop($serverPid);
}

exit(test_finish('onboarding_e2e_test'));
