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

    $collectionSlug = 'routing-entries-' . getmypid();
    $collectionDir = $root . '/content/collections/' . $collectionSlug;
    $backupCollection = sys_get_temp_dir() . '/birkly-routing-collection-' . getmypid();
    if (is_dir($collectionDir)) {
        birkly_test_backup_directory($collectionDir, $backupCollection);
    }
    mkdir($collectionDir, 0755, true);
    mkdir($collectionDir . '/entries', 0755, true);
    file_put_contents($collectionDir . '/collection.json', json_encode([
        'name' => 'Routing Test',
        'slug' => $collectionSlug,
    ], JSON_PRETTY_PRINT));
    file_put_contents($collectionDir . '/schema.json', json_encode(['fields' => []], JSON_PRETTY_PRINT));

    $entriesResponse = birkly_test_http_request(
        $base . '/admin/index.php?page=entries&collection=' . urlencode($collectionSlug),
        ['cookie_file' => $cookieFile]
    );
    test_assert('entries page HTTP 200', $entriesResponse['code'] === 200, 'code ' . $entriesResponse['code']);
    test_assert(
        'entries page contains entries-page marker',
        strpos($entriesResponse['body'], 'entries-page') !== false
    );

    $entryId = 'routing-entry-' . getmypid();
    file_put_contents($collectionDir . '/entries/' . $entryId . '.json', json_encode([
        'id' => $entryId,
        'title' => 'Routing Test Entry',
        'status' => 'published',
    ], JSON_PRETTY_PRINT));

    $entryDetailResponse = birkly_test_http_request(
        $base . '/admin/index.php?page=entry-detail&collection=' . urlencode($collectionSlug) . '&entry=' . urlencode($entryId),
        ['cookie_file' => $cookieFile]
    );
    test_assert('entry-detail page HTTP 200', $entryDetailResponse['code'] === 200, 'code ' . $entryDetailResponse['code']);
    test_assert(
        'entry-detail page contains entry-detail-page marker',
        strpos($entryDetailResponse['body'], 'entry-detail-page') !== false
    );

    // Subdirectory install smoke (P5.4-M0) — router + login reachable; render covered by admin_subdir_content_test
    $subdirAdminJson = $root . '/settings/admin.json';
    $subdirAdminBackup = sys_get_temp_dir() . '/birkly-routing-admin-' . getmypid() . '.json';
    $hadAdminJson = is_file($subdirAdminJson);
    if ($hadAdminJson) {
        copy($subdirAdminJson, $subdirAdminBackup);
    }
    if (!is_dir(dirname($subdirAdminJson))) {
        mkdir(dirname($subdirAdminJson), 0755, true);
    }
    try {
        file_put_contents($subdirAdminJson, json_encode(['base_path' => '/Birkly-0.1'], JSON_PRETTY_PRINT));
        unset($GLOBALS['__birkly_base_path']);

        $subdirBase = $base . '/Birkly-0.1';
        $subdirLogin = birkly_test_http_request($subdirBase . '/admin/login.php');
        test_assert('subdir login page HTTP 200', $subdirLogin['code'] === 200, 'code ' . $subdirLogin['code']);
        test_assert(
            'subdir login page has no syntax error',
            stripos($subdirLogin['body'], 'syntax error') === false
                && stripos($subdirLogin['body'], 'unexpected token') === false
        );
    } finally {
        if ($hadAdminJson) {
            copy($subdirAdminBackup, $subdirAdminJson);
            @unlink($subdirAdminBackup);
        } else {
            @unlink($subdirAdminJson);
        }
        unset($GLOBALS['__birkly_base_path']);
    }

    if (is_dir($backupCollection)) {
        birkly_test_remove_directory($collectionDir);
        birkly_test_restore_directory($collectionDir, $backupCollection);
        birkly_test_remove_directory($backupCollection);
    } else {
        birkly_test_remove_directory($collectionDir);
    }
} finally {
    birkly_test_restore_directory($usersDir, $backupUsers);
    birkly_test_remove_directory($backupUsers);
    @unlink($cookieFile);
    birkly_test_http_server_stop($serverPid);
}

exit(test_finish('admin_routing_http_test'));
