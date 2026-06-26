<?php
/**
 * P4-M2-T2 — Admin routing HTTP regression (post-onboarding navigation).
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/http_test_helpers.php';

test_reset_failures();
echo "Admin routing HTTP tests\n\n";

$root = dirname(__DIR__);
$port = (int) (getenv('ADMIN_ROUTING_HTTP_PORT') ?: 18083);
$base = "http://127.0.0.1:$port";
$usersDir = $root . '/users';
$backupUsers = sys_get_temp_dir() . '/birkly-routing-users-' . getmypid();
$cookieFile = sys_get_temp_dir() . '/birkly-routing-cookies-' . getmypid() . '.txt';
$testEmail = 'routing-e2e-' . getmypid() . '@birkly.test';

$pages = [
    'dashboard' => ['marker' => 'page-header', 'forbidden' => 'collections-container-only'],
    'content' => ['marker' => 'content-page'],
    'media' => ['marker' => 'media-layout'],
    'settings' => ['marker' => 'settings-page'],
    'help' => ['marker' => 'help-page'],
];

$serverPid = birkly_test_http_server_start($root, $port);

try {
    birkly_test_backup_directory($usersDir, $backupUsers);
    birkly_test_clear_json_files($usersDir);

    $create = birkly_test_http_request($base . '/api/onboarding.php?action=create_first_user', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'name' => 'Routing E2E',
            'email' => $testEmail,
            'password' => 'SecurePass123!',
        ]),
        'cookie_file' => $cookieFile,
    ]);
    $createData = birkly_test_json_decode($create['body']);
    test_assert('fixture user created', ($createData['status'] ?? '') === 'ok');

    birkly_test_http_request($base . '/api/onboarding.php?action=skip', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => '{}',
        'cookie_file' => $cookieFile,
    ]);

    foreach ($pages as $slug => $expect) {
        $response = birkly_test_http_request($base . '/admin/index.php?page=' . urlencode($slug), [
            'cookie_file' => $cookieFile,
        ]);
        test_assert("$slug page HTTP 200", $response['code'] === 200, 'code ' . $response['code']);
        test_assert(
            "$slug page contains {$expect['marker']}",
            strpos($response['body'], $expect['marker']) !== false
        );
    }

    $contentBody = birkly_test_http_request($base . '/admin/index.php?page=content', [
        'cookie_file' => $cookieFile,
    ])['body'];
    $dashboardBody = birkly_test_http_request($base . '/admin/index.php?page=dashboard', [
        'cookie_file' => $cookieFile,
    ])['body'];

    test_assert(
        'content page is not identical to dashboard body',
        $contentBody !== $dashboardBody
    );
} finally {
    birkly_test_restore_directory($usersDir, $backupUsers);
    birkly_test_remove_directory($backupUsers);
    @unlink($cookieFile);
    birkly_test_http_server_stop($serverPid);
}

exit(test_finish('admin_routing_http_test'));
