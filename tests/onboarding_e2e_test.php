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
$base = "http://127.0.0.1:$port";
$usersDir = $root . '/users';
$settingsDir = $root . '/settings';
$backupUsers = sys_get_temp_dir() . '/birkly-e2e-users-' . getmypid();
$backupSettings = sys_get_temp_dir() . '/birkly-e2e-settings-' . getmypid();
$cookieFile = sys_get_temp_dir() . '/birkly-e2e-cookies-' . getmypid() . '.txt';
$testEmail = 'onboarding-e2e-' . getmypid() . '@birkly.test';
$testPassword = 'SecurePass123!';

$serverPid = birkly_test_http_server_start($root, $port);

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
    birkly_test_http_server_stop($serverPid);
}

exit(test_finish('onboarding_e2e_test'));
