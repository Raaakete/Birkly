<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
// Secure session initialization for HaruCMS

require_once __DIR__ . '/../core/birkly_paths.php';

function birkly_request_is_secure(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (($_SERVER['SERVER_PORT'] ?? null) == 443) {
        return true;
    }
    $forwarded = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['HTTP_X_FORWARDED_SSL'] ?? '';
    if (is_string($forwarded) && strtolower($forwarded) === 'https') {
        return true;
    }

    return false;
}

function haru_secure_session_start() {
    $secure = birkly_request_is_secure();
    $cookiePath = birkly_session_cookie_path();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Start the site (frontend) session so user_current / user_logged_in see the website user.
 * Used by public API and demo site so template functions see the logged-in demo user (e.g. Alice),
 * not the admin session (e.g. CMS admin).
 */
function birkly_site_session_start() {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    $secure = birkly_request_is_secure();
    $cookiePath = birkly_session_cookie_path();
    session_name('birkly_site_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

/**
 * Start the admin session (CMS panel). Used by admin panel only.
 */
function birkly_admin_session_start() {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    $secure = birkly_request_is_secure();
    $cookiePath = birkly_session_cookie_path();
    session_name('birkly_admin_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

/**
 * Resolve which session scope CSRF tokens should use.
 * Returns one of: admin, cms, site.
 */
function birkly_resolve_csrf_session_scope(array $cookies, string $referer = '', string $requestUri = ''): string {
    $uri = $requestUri !== '' ? $requestUri : ($_SERVER['REQUEST_URI'] ?? '');

    // Admin panel and CMS API always share the main PHP session
    if ($uri !== '' && preg_match('#/(admin|api)(/|$|\?)#', $uri)) {
        return 'cms';
    }

    if (!empty($cookies['birkly_admin_sid'])) {
        return 'admin';
    }

    if (!empty($cookies['PHPSESSID'])) {
        return 'cms';
    }

    if (strpos($referer, '/admin/') !== false) {
        return 'cms';
    }

    if (!empty($cookies['birkly_site_sid'])) {
        return 'site';
    }

    return 'site';
}

/**
 * Start the session used for CSRF token generation/validation.
 * Admin panel and onboarding use the default PHP session (PHPSESSID).
 * Public website client library uses the site session (birkly_site_sid).
 */
function birkly_csrf_session_start(): void {
    $scope = birkly_resolve_csrf_session_scope(
        $_COOKIE,
        $_SERVER['HTTP_REFERER'] ?? '',
        $_SERVER['REQUEST_URI'] ?? ''
    );

    if ($scope === 'admin') {
        birkly_admin_session_start();
        return;
    }

    if ($scope === 'cms') {
        haru_secure_session_start();
        return;
    }

    birkly_site_session_start();
} 