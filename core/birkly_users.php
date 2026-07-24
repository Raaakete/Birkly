<?php
/**
 * User storage helpers — single resolution for users/ vs data/users/.
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';

/**
 * Whether a filename is a CMS admin user account (md5 email), not onboarding metadata.
 */
function birkly_is_user_account_filename(string $filename): bool
{
    return (bool) preg_match('/^[a-f0-9]{32}\.json$/i', $filename);
}

/**
 * Directories to search for user account files, in priority order.
 *
 * @return list<string>
 */
function birkly_users_search_dirs(): array
{
    $root = birkly_project_root();
    $dirs = [];

    $primary = $root . '/users';
    if (is_dir($primary)) {
        $dirs[] = $primary;
    }

    $fallback = $root . '/data/users';
    if (is_dir($fallback) && !in_array($fallback, $dirs, true)) {
        $dirs[] = $fallback;
    }

    return $dirs;
}

/**
 * Directory for creating new user accounts (prefers users/, then data/users/).
 */
function birkly_users_write_dir(): string
{
    $root = birkly_project_root();
    $primary = $root . '/users';

    if (is_dir($primary)) {
        return $primary;
    }

    if (!is_dir($primary)) {
        @mkdir($primary, 0755, true);
    }
    if (is_dir($primary)) {
        return $primary;
    }

    $fallback = $root . '/data/users';
    if (!is_dir($fallback)) {
        @mkdir($fallback, 0755, true);
    }

    return $fallback;
}

/**
 * Whether at least one admin user account file exists.
 */
function birkly_has_users(): bool
{
    foreach (birkly_users_search_dirs() as $dir) {
        foreach (scandir($dir) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (birkly_is_user_account_filename($file)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Normalize email to the md5 user id used for filenames.
 */
function birkly_user_id_from_email(string $email): string
{
    return md5(strtolower(trim($email)));
}

/**
 * Resolve the filesystem path to a user account JSON file, or null if missing.
 */
function birkly_user_account_path(string $email): ?string
{
    $userId = birkly_user_id_from_email($email);
    $filename = $userId . '.json';

    foreach (birkly_users_search_dirs() as $dir) {
        $path = $dir . '/' . $filename;
        if (is_file($path)) {
            return $path;
        }
    }

    return null;
}

/**
 * Load a user account record by email.
 *
 * @return array<string, mixed>|null
 */
function birkly_load_user_account(string $email): ?array
{
    $path = birkly_user_account_path($email);
    if ($path === null) {
        return null;
    }

    $user = json_decode((string) file_get_contents($path), true);
    return is_array($user) ? $user : null;
}

/**
 * Whether a user id refers to a human CMS account (32-char hex), not onboarding metadata.
 */
function birkly_is_human_user_id(string $userId): bool
{
    return birkly_is_user_account_filename($userId . '.json');
}

/**
 * List human CMS user records from users/ and data/users/.
 *
 * @return list<array<string, mixed>>
 */
function birkly_list_human_user_records(bool $stripPassword = true): array
{
    $users = [];
    $seen = [];

    foreach (birkly_users_search_dirs() as $dir) {
        foreach (scandir($dir) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (!birkly_is_user_account_filename($file)) {
                continue;
            }

            $userId = pathinfo($file, PATHINFO_FILENAME);
            if (isset($seen[$userId])) {
                continue;
            }

            $raw = file_get_contents($dir . '/' . $file);
            $user = json_decode((string) $raw, true);
            if (!is_array($user)) {
                continue;
            }
            if (($user['type'] ?? '') === 'ai') {
                continue;
            }

            $seen[$userId] = true;
            if ($stripPassword) {
                unset($user['password']);
            }
            $users[] = $user;
        }
    }

    usort($users, static function (array $a, array $b): int {
        $aLabel = strtolower(trim((string) ($a['email'] ?? $a['name'] ?? $a['id'] ?? '')));
        $bLabel = strtolower(trim((string) ($b['email'] ?? $b['name'] ?? $b['id'] ?? '')));
        return $aLabel <=> $bLabel;
    });

    return $users;
}
