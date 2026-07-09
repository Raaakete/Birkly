<?php
/**
 * Org-level security policy helpers (P27-M10–M11) — 2FA enforcement and grace period.
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';
require_once __DIR__ . '/birkly_users.php';
require_once dirname(__DIR__) . '/api/storage.php';

/**
 * @return array<string, mixed>
 */
function birkly_security_settings(): array
{
    if (isset($GLOBALS['__birkly_security_settings_cache']) && is_array($GLOBALS['__birkly_security_settings_cache'])) {
        return $GLOBALS['__birkly_security_settings_cache'];
    }

    $storage = new Storage(birkly_project_root() . '/settings');
    $file = $storage->read('security.json') ?? [];

    $settings = array_merge([
        'session_timeout' => 3600,
        'password_min_length' => 8,
        'require_strong_password' => true,
        'max_login_attempts' => 5,
        'lockout_time' => 900,
        'enable_csrf' => true,
        'require_2fa' => false,
        'require_2fa_grace_days' => 30,
    ], $file);

    $GLOBALS['__birkly_security_settings_cache'] = $settings;
    return $settings;
}

function birkly_security_policy_reset_cache(): void
{
    unset($GLOBALS['__birkly_security_settings_cache']);
}

function birkly_require_2fa_enabled(): bool
{
    $settings = birkly_security_settings();
    return !empty($settings['require_2fa']);
}

function birkly_require_2fa_grace_days(): int
{
    $settings = birkly_security_settings();
    return max(0, (int) ($settings['require_2fa_grace_days'] ?? 30));
}

function birkly_user_2fa_enabled(array $user): bool
{
    return !empty($user['2fa']['enabled']);
}

function birkly_user_2fa_grace_until(array $user): ?string
{
    $until = $user['2fa_grace_until'] ?? null;
    if (!is_string($until) || trim($until) === '') {
        return null;
    }
    return $until;
}

function birkly_user_in_2fa_grace(array $user): bool
{
    if (!birkly_require_2fa_enabled() || birkly_user_2fa_enabled($user)) {
        return false;
    }

    $until = birkly_user_2fa_grace_until($user);
    if ($until === null) {
        return false;
    }

    $ts = strtotime($until);
    if ($ts === false) {
        return false;
    }

    return time() <= $ts;
}

function birkly_user_2fa_blocked(array $user): bool
{
    if (!birkly_require_2fa_enabled() || birkly_user_2fa_enabled($user)) {
        return false;
    }

    return !birkly_user_in_2fa_grace($user);
}

/**
 * Days remaining in the 2FA grace window (0 when expired or not in grace).
 */
function birkly_user_2fa_grace_days_left(array $user): int
{
    if (!birkly_user_in_2fa_grace($user)) {
        return 0;
    }

    $until = birkly_user_2fa_grace_until($user);
    if ($until === null) {
        return 0;
    }

    $ts = strtotime($until);
    if ($ts === false) {
        return 0;
    }

    return max(0, (int) ceil(($ts - time()) / 86400));
}

/**
 * Set per-user grace deadline when org policy requires 2FA and user has none.
 *
 * @param array<string, mixed> $user
 * @param array<string, mixed>|null $security
 * @return array<string, mixed>
 */
function birkly_apply_2fa_policy_to_user(array $user, ?array $security = null): array
{
    $security = $security ?? birkly_security_settings();

    if (empty($security['require_2fa']) || birkly_user_2fa_enabled($user)) {
        return $user;
    }

    if (!empty($user['2fa_grace_until'])) {
        return $user;
    }

    $days = max(0, (int) ($security['require_2fa_grace_days'] ?? 30));
    $user['2fa_grace_until'] = date('c', strtotime('+' . $days . ' days'));

    return $user;
}

/**
 * Apply org 2FA policy grace and persist when a new deadline is set.
 *
 * @param array<string, mixed> $user
 * @return array<string, mixed>
 */
function birkly_save_user_with_2fa_policy(array $user): array
{
    $userId = (string) ($user['id'] ?? '');
    if ($userId === '') {
        return $user;
    }

    $before = $user['2fa_grace_until'] ?? null;
    $user = birkly_apply_2fa_policy_to_user($user);

    if (($user['2fa_grace_until'] ?? null) !== $before) {
        $usersDir = birkly_users_write_dir();
        $storage = new Storage($usersDir);
        $storage->write($userId . '.json', $user);
    }

    return $user;
}

/**
 * Refresh session user from disk (keeps password out of session).
 */
function birkly_refresh_session_user_from_disk(): void
{
    if (!isset($_SESSION['user']['email'])) {
        return;
    }

    $fresh = birkly_load_user_account((string) $_SESSION['user']['email']);
    if (!is_array($fresh)) {
        return;
    }

    unset($fresh['password']);
    $_SESSION['user'] = array_merge($_SESSION['user'], $fresh);
}

/**
 * Persist grace deadline when policy application sets it for the first time.
 *
 * @deprecated Use birkly_save_user_with_2fa_policy()
 * @param array<string, mixed> $user
 * @return array<string, mixed>
 */
function birkly_persist_user_2fa_grace_if_needed(array $user): array
{
    return birkly_save_user_with_2fa_policy($user);
}

/**
 * Redirect blocked users to account 2FA setup (allows account + logout only).
 */
function birkly_enforce_2fa_policy_for_admin_page(): void
{
    if (!isset($_SESSION['user'])) {
        return;
    }

    birkly_refresh_session_user_from_disk();
    $user = birkly_save_user_with_2fa_policy($_SESSION['user']);
    $_SESSION['user'] = array_merge($_SESSION['user'], $user);
    unset($_SESSION['user']['password']);

    if (!birkly_user_2fa_blocked($_SESSION['user'])) {
        return;
    }

    $page = isset($_GET['page']) ? (string) $_GET['page'] : '';
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');

    if ($page === 'account' || $script === 'logout.php' || $script === 'verify-2fa.php') {
        return;
    }

    $_SESSION['flash_2fa_policy'] = 'Two-factor authentication is required for all admin users. Enable 2FA in your account settings to continue.';
    $target = birkly_admin_url('account', ['2fa_required' => '1']) . '#2fa';

    if (!headers_sent()) {
        header('Location: ' . $target);
        exit;
    }
}
