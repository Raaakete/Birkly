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
    if (preg_match('/_onboarding\.json$/i', $filename)) {
        return false;
    }
    if (preg_match('/^ai_[a-f0-9]+\.json$/i', $filename)) {
        return false;
    }

    return (bool) preg_match('/^[a-f0-9]{32}\.json$/i', $filename);
}

/**
 * Whether a stored users/*.json file is an outbound BYOAI connection (not a human login).
 */
function birkly_is_ai_connection_filename(string $filename): bool
{
    return (bool) preg_match('/^ai_[a-f0-9]+\.json$/i', $filename);
}

/**
 * Whether a user record represents a human CMS login account.
 */
function birkly_is_human_user_record(array $user): bool
{
    if (($user['type'] ?? '') === 'ai') {
        return false;
    }

    $id = (string) ($user['id'] ?? '');
    if ($id !== '' && str_starts_with($id, 'ai_')) {
        return false;
    }

    $email = strtolower(trim((string) ($user['email'] ?? '')));
    if ($email !== '' && str_ends_with($email, '@ai.birkly')) {
        return false;
    }

    $password = $user['password'] ?? '';
    if (!is_string($password) || $password === '') {
        return false;
    }

    return true;
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
 * Resolve the filesystem path to a user account JSON file by id and/or email.
 */
function birkly_resolve_user_account_path(string $userId, ?string $email = null): ?string
{
    $email = $email !== null ? strtolower(trim($email)) : '';
    if ($email !== '') {
        $byEmail = birkly_user_account_path($email);
        if ($byEmail !== null) {
            return $byEmail;
        }
    }

    $userId = trim($userId);
    if ($userId === '') {
        return null;
    }

    foreach (birkly_users_search_dirs() as $dir) {
        $path = $dir . '/' . $userId . '.json';
        if (is_file($path)) {
            return $path;
        }
    }

    return null;
}

/**
 * Load a user account record by session id/email.
 *
 * @param array<string, mixed> $sessionUser
 * @return array<string, mixed>|null
 */
function birkly_load_user_account_for_session(array $sessionUser): ?array
{
    $email = strtolower(trim((string) ($sessionUser['email'] ?? '')));
    if ($email !== '') {
        $user = birkly_load_user_account($email);
        if ($user !== null) {
            return $user;
        }
    }

    $userId = trim((string) ($sessionUser['id'] ?? ''));
    if ($userId === '' && $email !== '') {
        $userId = birkly_user_id_from_email($email);
    }
    if ($userId === '') {
        return null;
    }

    $path = birkly_resolve_user_account_path($userId, $email !== '' ? $email : null);
    if ($path === null) {
        return null;
    }

    $user = json_decode((string) file_get_contents($path), true);
    return is_array($user) ? $user : null;
}

/**
 * Persist a user account record to the directory where it already lives,
 * or to the canonical write directory for new accounts.
 *
 * @param array<string, mixed> $user
 */
function birkly_save_user_account(array $user): bool
{
    $email = strtolower(trim((string) ($user['email'] ?? '')));
    $userId = trim((string) ($user['id'] ?? ''));
    if ($userId === '' && $email !== '') {
        $userId = birkly_user_id_from_email($email);
        $user['id'] = $userId;
    }
    if ($userId === '') {
        return false;
    }

    $path = birkly_resolve_user_account_path($userId, $email !== '' ? $email : null);
    if ($path === null) {
        $path = birkly_users_write_dir() . '/' . $userId . '.json';
    }

    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $json = json_encode($user, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    $tmpPath = $path . '.tmp';
    if (file_put_contents($tmpPath, $json, LOCK_EX) === false) {
        return false;
    }

    return rename($tmpPath, $path);
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
            if (!birkly_is_human_user_record($user)) {
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

/**
 * Outbound BYOAI provider connections owned by a human user (Agent Guide → Connections → Built-in assistant).
 *
 * @return list<array<string, mixed>>
 */
function birkly_list_byoai_connections_for_user(string $userId): array
{
    $userId = trim($userId);
    if ($userId === '') {
        return [];
    }

    $connections = [];
    foreach (birkly_users_search_dirs() as $dir) {
        foreach (scandir($dir) ?: [] as $file) {
            if ($file === '.' || $file === '..' || !birkly_is_ai_connection_filename($file)) {
                continue;
            }

            $record = json_decode((string) file_get_contents($dir . '/' . $file), true);
            if (!is_array($record) || ($record['type'] ?? '') !== 'ai') {
                continue;
            }
            if ((string) ($record['created_by'] ?? '') !== $userId) {
                continue;
            }

            $connections[] = [
                'id' => (string) ($record['id'] ?? pathinfo($file, PATHINFO_FILENAME)),
                'name' => (string) ($record['name'] ?? 'AI connection'),
                'provider' => (string) ($record['provider'] ?? ''),
                'role' => (string) ($record['role'] ?? ''),
                'status' => (string) ($record['status'] ?? 'active'),
                'created_at' => (string) ($record['created_at'] ?? ''),
                'kind' => 'byoai',
            ];
        }
    }

    usort($connections, static function (array $a, array $b): int {
        return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
    });

    return $connections;
}
