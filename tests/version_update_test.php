<?php
/**
 * Update package validation tests (GitHub archive layout).
 */

require_once __DIR__ . '/../core/version.php';
require_once __DIR__ . '/http_test_helpers.php';

echo "Update package tests\n\n";

function test_assert(string $label, bool $ok, string $detail = ''): void
{
    echo $label . ': ' . ($ok ? 'PASS' : 'FAIL');
    if ($detail !== '') {
        echo " ($detail)";
    }
    echo "\n";
    if (!$ok) {
        exit(1);
    }
}

$zipPath = sys_get_temp_dir() . '/birkly-update-test-' . uniqid() . '.zip';
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Could not create test zip\n");
    exit(1);
}

// GitHub tag archive layout
$zip->addFromString('Birkly-1.0.3/index.php', '<?php');
$zip->addFromString('Birkly-1.0.3/admin/index.php', '<?php');
$zip->addFromString('Birkly-1.0.3/core/version.php', '<?php');
// Pad so size > 50KB minimum
$zip->addFromString('Birkly-1.0.3/padding.bin', random_bytes(12 * 1024));
$zip->close();

test_assert(
    'github archive zip validates',
    BirklyVersion::validateUpdatePackageForTest($zipPath, '1.0.3')
);

@unlink($zipPath);

// Flat zip at root should also validate
$zipPath2 = sys_get_temp_dir() . '/birkly-update-test-flat-' . uniqid() . '.zip';
$zip2 = new ZipArchive();
$zip2->open($zipPath2, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip2->addFromString('index.php', '<?php');
$zip2->addFromString('admin/index.php', '<?php');
$zip2->addFromString('core/version.php', '<?php');
$zip2->addFromString('padding.bin', random_bytes(12 * 1024));
$zip2->close();

test_assert(
    'flat zip validates',
    BirklyVersion::validateUpdatePackageForTest($zipPath2, '1.0.0')
);

@unlink($zipPath2);

// P5.2-M4: updates API uses manage_settings (not role === admin only)
if (function_exists('curl_init')) {
    echo "\nUpdates API auth tests\n\n";

    $root = dirname(__DIR__);
    $port = (int) (getenv('VERSION_UPDATE_HTTP_PORT') ?: 18084);
    $base = "http://127.0.0.1:$port";
    $usersDir = $root . '/users';
    $backupUsers = sys_get_temp_dir() . '/birkly-updates-users-' . getmypid();
    $cookieFile = sys_get_temp_dir() . '/birkly-updates-cookies-' . getmypid() . '.txt';
    $testEmail = 'updates-auth-' . getmypid() . '@birkly.test';

    $serverPid = birkly_test_http_server_start($root, $port);

    try {
        birkly_test_backup_directory($usersDir, $backupUsers);
        birkly_test_clear_json_files($usersDir);

        $unauth = birkly_test_http_request($base . '/api/updates.php', [
            'method' => 'POST',
            'headers' => ['Content-Type: application/json'],
            'body' => json_encode(['action' => 'get_update_status']),
        ]);
        test_assert('unauthenticated updates API returns 401', $unauth['code'] === 401, 'code ' . $unauth['code']);

        $create = birkly_test_http_request($base . '/api/onboarding.php?action=create_first_user', [
            'method' => 'POST',
            'headers' => ['Content-Type: application/json'],
            'body' => json_encode([
                'name' => 'Updates Auth',
                'email' => $testEmail,
                'password' => 'SecurePass123!',
            ]),
            'cookie_file' => $cookieFile,
        ]);
        $createData = birkly_test_json_decode($create['body']);
        test_assert('fixture admin user created', ($createData['status'] ?? '') === 'ok');

        $csrfMeta = birkly_test_http_request($base . '/admin/index.php?page=settings', [
            'cookie_file' => $cookieFile,
        ]);
        preg_match('/name="csrf-token"\s+content="([^"]+)"/', $csrfMeta['body'], $csrfMatch);
        $csrf = $csrfMatch[1] ?? '';

        $adminUpdates = birkly_test_http_request($base . '/api/updates.php', [
            'method' => 'POST',
            'headers' => ['Content-Type: application/json'],
            'body' => json_encode([
                'action' => 'get_update_status',
                '_csrf' => $csrf,
            ]),
            'cookie_file' => $cookieFile,
        ]);
        test_assert(
            'admin user can call updates API',
            $adminUpdates['code'] === 200,
            'code ' . $adminUpdates['code'] . ' body ' . substr($adminUpdates['body'], 0, 120)
        );
    } finally {
        birkly_test_restore_directory($usersDir, $backupUsers);
        birkly_test_remove_directory($backupUsers);
        @unlink($cookieFile);
        birkly_test_http_server_stop($serverPid);
    }
}

echo "\nversion_update_test: PASS\n";
exit(0);
