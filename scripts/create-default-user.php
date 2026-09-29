#!/usr/bin/env php
<?php
/**
 * Create the first admin account when the install has none.
 *
 *   BIRKLY_ADMIN_PASSWORD='YourPassword123!' php scripts/create-default-user.php
 *   php scripts/create-default-user.php --email=you@example.com
 *
 * If any admin already exists, the script leaves it unchanged.
 * The password is not accepted on the command line.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/cli_only.php';
birkly_require_cli();

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--password' || str_starts_with($arg, '--password=')) {
        fwrite(STDERR, "Do not pass --password on the command line (visible in process list).\n");
        fwrite(STDERR, "Set BIRKLY_ADMIN_PASSWORD or type the password at the prompt.\n");
        exit(1);
    }
}

$options = getopt('', ['email:', 'help']);

if (isset($options['help'])) {
    echo "Create the first admin account\n\n";
    echo "  BIRKLY_ADMIN_PASSWORD='…' php scripts/create-default-user.php\n";
    echo "  php scripts/create-default-user.php --email=you@example.com\n\n";
    echo "Does nothing when an admin account already exists.\n";
    exit(0);
}

require_once dirname(__DIR__) . '/core/initial_admin.php';

if (birkly_has_users()) {
    fwrite(STDOUT, "An admin account already exists. No account was created.\n");
    exit(0);
}

$email = strtolower(trim((string) ($options['email'] ?? getenv('BIRKLY_ADMIN_EMAIL') ?: 'admin@localhost')));
$password = trim((string) (getenv('BIRKLY_ADMIN_PASSWORD') ?: ''));

if ($password === '') {
    if (function_exists('posix_isatty') && posix_isatty(STDIN)) {
        fwrite(STDOUT, 'New admin password: ');
        system('stty -echo 2>/dev/null');
        $password = trim((string) fgets(STDIN));
        system('stty echo 2>/dev/null');
        fwrite(STDOUT, "\n");
    } else {
        fwrite(STDERR, "Set BIRKLY_ADMIN_PASSWORD or run interactively with a TTY.\n");
        exit(1);
    }
}

$result = birkly_create_initial_admin($email, $password);
fwrite($result['ok'] ? STDOUT : STDERR, $result['message'] . "\n");
exit($result['ok'] ? 0 : 1);
