<?php
/**
 * User directory resolution and hasUsers() accuracy.
 */

require_once __DIR__ . '/../core/birkly_users.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/http_test_helpers.php';

test_reset_failures();
echo "Birkly users storage tests\n\n";

$root = dirname(__DIR__);
$usersDir = $root . '/users';
$dataUsersDir = $root . '/data/users';
$backupUsers = sys_get_temp_dir() . '/birkly-users-test-' . getmypid();
$backupDataUsers = sys_get_temp_dir() . '/birkly-data-users-test-' . getmypid();

function users_test_clear_dir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $path = $dir . '/' . $file;
        if (is_file($path)) {
            unlink($path);
        }
    }
}

try {
    birkly_test_backup_directory($usersDir, $backupUsers);
    birkly_test_backup_directory($dataUsersDir, $backupDataUsers);
    users_test_clear_dir($usersDir);
    users_test_clear_dir($dataUsersDir);

    test_assert('no users when directories missing', birkly_has_users() === false);

    if (!is_dir($usersDir)) {
        mkdir($usersDir, 0755, true);
    }
    file_put_contents($usersDir . '/abc123_onboarding.json', '{}');
    test_assert(
        'onboarding json alone is not a user account',
        birkly_has_users() === false
    );

    $email = 'storage-test@birkly.test';
    $userId = birkly_user_id_from_email($email);
    $userFile = $usersDir . '/' . $userId . '.json';
  file_put_contents($userFile, json_encode([
        'id' => $userId,
        'email' => $email,
        'password' => password_hash('SecurePass123!', PASSWORD_DEFAULT),
        'role' => 'admin',
    ], JSON_PRETTY_PRINT));

    test_assert('user file in users/ is detected', birkly_has_users() === true);
    test_assert(
        'birkly_user_account_path finds users/ file',
        birkly_user_account_path($email) === $userFile
    );

    unlink($userFile);
    if (!is_dir($dataUsersDir)) {
        mkdir($dataUsersDir, 0755, true);
    }
    $fallbackFile = $dataUsersDir . '/' . $userId . '.json';
    file_put_contents($fallbackFile, json_encode([
        'id' => $userId,
        'email' => $email,
        'password' => password_hash('SecurePass123!', PASSWORD_DEFAULT),
        'role' => 'admin',
    ], JSON_PRETTY_PRINT));

    test_assert('user file in data/users/ is detected', birkly_has_users() === true);
    test_assert(
        'birkly_user_account_path finds data/users file',
        birkly_user_account_path($email) === $fallbackFile
    );

    $loaded = birkly_load_user_account($email);
    test_assert('birkly_load_user_account returns record', is_array($loaded));
    test_assert('loaded user has admin role', ($loaded['role'] ?? '') === 'admin');
} finally {
    birkly_test_restore_directory($usersDir, $backupUsers);
    birkly_test_restore_directory($dataUsersDir, $backupDataUsers);
    birkly_test_remove_directory($backupUsers);
    birkly_test_remove_directory($backupDataUsers);
}

exit(test_finish('birkly_users_test'));
