<?php
/**
 * Public storefront user resolution (P82-D6).
 *
 * Core resolves the current site user via filter only — never requires plugin class files.
 */

/**
 * Bootstrap active plugins for public user resolution.
 */
function birkly_public_bootstrap_for_current_user(): void
{
    if (!function_exists('get_all_plugins')) {
        require_once __DIR__ . '/public_bootstrap.php';
    }

    if (function_exists('birkly_public_load_active_plugins')) {
        birkly_public_load_active_plugins();
        return;
    }

    if (!isset($GLOBALS['haru_plugins_loaded']) && function_exists('load_plugins')) {
        load_plugins();
        $GLOBALS['haru_plugins_loaded'] = true;
    }
}

/**
 * Resolve the current public/site user from session + plugin filters.
 *
 * @return array<string, mixed>|null
 */
function birkly_public_current_user(): ?array
{
    static $resolved = false;
    static $cached = null;

    if ($resolved) {
        return $cached;
    }
    $resolved = true;

    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $user = null;
    if (!empty($_SESSION['user']['id']) && is_array($_SESSION['user'])) {
        $user = $_SESSION['user'];
    }

    birkly_public_bootstrap_for_current_user();

    if (function_exists('apply_filters')) {
        $filtered = apply_filters('birkly_public_current_user', $user);
        if ($filtered === null) {
            $cached = null;
            return null;
        }
        if (is_array($filtered) && !empty($filtered['id'])) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['user'] = $filtered;
            }
            $cached = $filtered;
            return $filtered;
        }
    }

    if (is_array($user) && !empty($user['id'])) {
        $cached = $user;
        return $user;
    }

    $cached = null;
    return null;
}

/**
 * Current public/site user id, or empty string when guest.
 */
function birkly_public_current_user_id(): string
{
    $user = birkly_public_current_user();
    if (!is_array($user) || empty($user['id'])) {
        return '';
    }

    return (string) $user['id'];
}

/**
 * Filter a list of entries for the current user's view permissions.
 *
 * @param array<int, array<string, mixed>> $entries
 * @return array<int, array<string, mixed>>
 */
function birkly_filter_entries_for_current_user(array $entries, string $collectionSlug = ''): array
{
    $userId = birkly_public_current_user_id();
    if ($userId === '' || !function_exists('apply_filters')) {
        return $entries;
    }

    $filtered = apply_filters('birkly_entries_view_filter', $entries, $userId, $collectionSlug);
    return is_array($filtered) ? $filtered : $entries;
}

/**
 * Whether the current user may view an entry in admin/search contexts.
 *
 * @param array<string, mixed> $entry
 */
function birkly_current_user_can_view_entry(array $entry, string $collectionSlug = ''): bool
{
    $userId = birkly_public_current_user_id();
    if ($userId === '') {
        return true;
    }

    if (!function_exists('apply_filters')) {
        return true;
    }

    $allowed = apply_filters('birkly_entry_view_allowed', true, $entry, $userId, $collectionSlug);
    return $allowed !== false;
}
