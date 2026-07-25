#!/usr/bin/env php
<?php
/**
 * Reset an admin password (emergency recovery on your own server).
 *
 * Usage:
 *   php scripts/reset-admin-password.php --email=you@example.com --password='NewPassword123!'
 */

declare(strict_types=1);

$options = getopt('', ['email:', 'password:', 'help']);

if (isset($options['help']) || empty($options['email']) || empty($options['password'])) {
    echo "Reset an admin account password\n\n";
    echo "Usage:\n";
    echo "  php scripts/reset-admin-password.php --email=you@example.com --password='NewPassword123!'\n\n";
    echo "Run inside the Dokploy Terminal for the app whose data you want to change.\n";
    exit(isset($options['help']) ? 0 : 1);
}

require_once dirname(__DIR__) . '/core/birkly_paths.php';
require_once dirname(__DIR__) . '/core/birkly_users.php';
require_once dirname(__DIR__) . '/api/users.php';
require_once dirname(__DIR__) . '/api/validate.php';

$email = strtolower(trim((string) $options['email']));
$password = (string) $options['password'];

$validation = validate_password_strength($password);
if (empty($validation['valid'])) {
    fwrite(STDERR, ($validation['message'] ?? 'Password does not meet requirements') . "\n");
    exit(1);
}

$user = birkly_load_user_account($email);
if ($user === null) {
    fwrite(STDERR, "No account found for: $email\n");
    fwrite(STDERR, "List accounts: ls -la /app/data/users/\n");
    exit(1);
}

$user['password'] = hashPassword($password);
$user['updated_at'] = date('c');

$usersDir = birkly_users_write_dir();
$userFile = $usersDir . '/' . md5($email) . '.json';

if (file_put_contents($userFile, json_encode($user, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n") === false) {
    fwrite(STDERR, "Failed to write $userFile\n");
    exit(1);
}

echo "Password updated for $email\n";
echo "You can now log in at /admin on this install.\n";
