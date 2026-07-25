#!/usr/bin/env php
<?php
/**
 * Reset a test/staging install to "first visit" state.
 *
 * Removes admin accounts and onboarding, clears starter content.
 * Keeps hosting.json, domains.json, and other settings.
 *
 * Usage:
 *   php scripts/reset-fresh-install.php --yes
 */

declare(strict_types=1);

$options = getopt('', ['yes', 'help']);

if (isset($options['help'])) {
    echo "Reset test install to first-visit state\n\n";
    echo "Usage: php scripts/reset-fresh-install.php --yes\n\n";
    echo "This will:\n";
    echo "  - Delete all admin user accounts\n";
    echo "  - Clear onboarding progress\n";
    echo "  - Clear collections and project site content\n";
    echo "  - Keep hosting.json and other settings\n\n";
    echo "After running, open /admin and create a new admin account.\n";
    exit(0);
}

if (!isset($options['yes'])) {
    fwrite(STDERR, "This deletes all admin logins on THIS install.\n");
    fwrite(STDERR, "Only use on test/staging. Re-run with --yes to confirm.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/core/birkly_paths.php';
require_once dirname(__DIR__) . '/core/birkly_users.php';
require_once dirname(__DIR__) . '/core/system_rebuild.php';

$root = birkly_project_root();
$usersRemoved = 0;

foreach (birkly_users_search_dirs() as $dir) {
    foreach (scandir($dir) ?: [] as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        if (!birkly_is_user_account_filename($file)) {
            continue;
        }
        $path = $dir . '/' . $file;
        $raw = @file_get_contents($path);
        $user = json_decode($raw ?: '', true);
        if (!is_array($user) || !birkly_is_human_user_record($user)) {
            continue;
        }
        if (@unlink($path)) {
            $usersRemoved++;
        }
    }
}

$onboardingFile = $root . '/settings/onboarding.json';
if (is_file($onboardingFile)) {
    @unlink($onboardingFile);
}

$rebuild = birkly_rebuild_system($root);
if (empty($rebuild['success'])) {
    fwrite(STDERR, 'Content reset failed: ' . ($rebuild['message'] ?? 'unknown error') . "\n");
    exit(1);
}

echo "Fresh install reset complete.\n";
echo "  admin accounts removed: $usersRemoved\n";
echo "  entries removed: " . (int) ($rebuild['entries_removed'] ?? 0) . "\n";
echo "  hosting settings: kept\n";
echo "\nNext: open /admin on this site and create a new admin account.\n";
