#!/usr/bin/env php
<?php
/**
 * Reset an admin password (emergency recovery on your own server).
 *
 * Usage:
 *   php scripts/reset-admin-password.php --email=you@example.com
 *   (prompts for password on stdin — not visible in process list)
 *
 * Or:
 *   BIRKLY_RESET_PASSWORD='NewPassword123!' php scripts/reset-admin-password.php --email=you@example.com
 *
 * --password is rejected. The value would show up in the process list.
 */

declare(strict_types=1);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--password' || str_starts_with($arg, '--password=')) {
        fwrite(STDERR, "Do not pass --password on the command line (visible in process list).\n");
        fwrite(STDERR, "Use one of:\n");
        fwrite(STDERR, "  php scripts/reset-admin-password.php --email=you@example.com\n");
        fwrite(STDERR, "  BIRKLY_RESET_PASSWORD='…' php scripts/reset-admin-password.php --email=you@example.com\n");
        exit(1);
    }
}

$options = getopt('', ['email:', 'help']);

if (isset($options['help'])) {
    echo "Reset an admin account password\n\n";
    echo "Usage:\n";
    echo "  php scripts/reset-admin-password.php --email=you@example.com\n";
    echo "  BIRKLY_RESET_PASSWORD='…' php scripts/reset-admin-password.php --email=you@example.com\n\n";
    echo "Run inside the Dokploy Terminal for the app whose data you want to change.\n";
    echo "--password is not accepted.\n";
    exit(0);
}

if (empty($options['email'])) {
    fwrite(STDERR, "Missing --email\n");
    exit(1);
}

$password = trim((string) (getenv('BIRKLY_RESET_PASSWORD') ?: ''));

if ($password === '') {
    if (function_exists('posix_isatty') && posix_isatty(STDIN)) {
        fwrite(STDOUT, 'New password: ');
        system('stty -echo 2>/dev/null');
        $password = trim((string) fgets(STDIN));
        system('stty echo 2>/dev/null');
        fwrite(STDOUT, "\n");
    } else {
        fwrite(STDERR, "Set BIRKLY_RESET_PASSWORD or run interactively with a TTY.\n");
        exit(1);
    }
}

if ($password === '') {
    fwrite(STDERR, "Password cannot be empty.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/core/birkly_paths.php';
require_once dirname(__DIR__) . '/core/birkly_users.php';
require_once dirname(__DIR__) . '/api/users.php';
require_once dirname(__DIR__) . '/api/validate.php';

$email = strtolower(trim((string) $options['email']));

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
    fwrite(STDERR, "Failed to write user record\n");
    exit(1);
}

echo "Password updated for $email\n";
echo "You can now log in at /admin on this install.\n";
