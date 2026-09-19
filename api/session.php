<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
// Secure session initialization for Birkly

require_once __DIR__ . '/../core/birkly_paths.php';
require_once __DIR__ . '/../core/update_staging.php';
birkly_apply_staged_update_files();

/** CMS admin/API session cookie name (avoids PHPSESSID collisions with host sites). */
define('BIRKLY_CMS_SESSION_NAME', 'birkly_cms_sid');

function birkly_trust_forwarded_proto(): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $cached = false;
    $settingsFile = dirname(__DIR__) . '/settings/security.json';
    if (!is_readable($settingsFile)) {
        return $cached;
    }

    $data = json_decode((string) file_get_contents($settingsFile), true);
    if (is_array($data) && !empty($data['trust_forwarded_proto'])) {
        $cached = true;
    }

    return $cached;
}

function birkly_request_is_secure(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (($_SERVER['SERVER_PORT'] ?? null) == 443) {
        return true;
    }

    if (birkly_trust_forwarded_proto()) {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['HTTP_X_FORWARDED_SSL'] ?? '';
        if (is_string($forwarded) && strtolower($forwarded) === 'https') {
            return true;
        }
    }

    return false;
}

/**
 * Normalize a request host (strip port, lowercase).
 */
function birkly_normalize_request_host(string $host): string
{
    $host = strtolower(trim($host));
    if ($host === '') {
        return '';
    }
    $colon = strpos($host, ':');
    if ($colon !== false) {
        $host = substr($host, 0, $colon);
    }
    return $host;
}

/**
 * Host from Origin or Referer when the storefront runs on a registered domain
 * separate from the CMS host (e.g. birkly.io → cms.birkly.cloud).
 */
function birkly_client_site_host(string $origin = '', string $referer = ''): string
{
    $origin = $origin !== '' ? $origin : ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '') {
        $host = parse_url($origin, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return birkly_normalize_request_host($host);
        }
    }

    $referer = $referer !== '' ? $referer : ($_SERVER['HTTP_REFERER'] ?? '');
    if ($referer !== '') {
        $host = parse_url($referer, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return birkly_normalize_request_host($host);
        }
    }

    return '';
}

/**
 * True when the browser page is on a registered site domain that is not the CMS host.
 * Cross-origin credentialed fetch requires SameSite=None site session cookies.
 */
function birkly_is_cross_origin_registered_site_request(string $origin = '', string $referer = ''): bool
{
    $clientHost = birkly_client_site_host($origin, $referer);
    if ($clientHost === '') {
        return false;
    }

    $cmsHost = birkly_normalize_request_host($_SERVER['HTTP_HOST'] ?? '');
    if ($cmsHost === '' || $clientHost === $cmsHost) {
        return false;
    }

    if (!function_exists('birkly_cors_origin_allowed')) {
        require_once __DIR__ . '/../core/cors.php';
    }

    $originUrl = $origin !== '' ? $origin : ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($originUrl === '' && $referer !== '') {
        $scheme = parse_url($referer, PHP_URL_SCHEME);
        if (!is_string($scheme) || $scheme === '') {
            $scheme = 'https';
        }
        $originUrl = $scheme . '://' . $clientHost;
    }
    if ($originUrl === '' && !empty($_SERVER['HTTP_REFERER'])) {
        $scheme = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_SCHEME);
        if (!is_string($scheme) || $scheme === '') {
            $scheme = 'https';
        }
        $originUrl = $scheme . '://' . $clientHost;
    }
    if ($originUrl === '') {
        return false;
    }

    return birkly_cors_origin_allowed($originUrl, get_registered_domains());
}

/**
 * SameSite policy for storefront (birkly_site_sid) cookies.
 */
function birkly_site_session_samesite(string $origin = '', string $referer = ''): string
{
    return birkly_is_cross_origin_registered_site_request($origin, $referer) ? 'None' : 'Lax';
}

/**
 * Cross-origin storefront fallback when browsers block third-party cookies.
 * Only honored for registered external origins with a valid session id format.
 */
function birkly_site_session_id_from_request_header(string $origin = '', string $referer = ''): ?string
{
    if (!birkly_is_cross_origin_registered_site_request($origin, $referer)) {
        return null;
    }

    $id = $_SERVER['HTTP_X_BIRKLY_SITE_SESSION'] ?? '';
    if (!is_string($id) || $id === '' || !preg_match('/^[a-zA-Z0-9,-]{16,128}$/', $id)) {
        return null;
    }

    return $id;
}

function haru_secure_session_start() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(BIRKLY_CMS_SESSION_NAME);
    }
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
    $sameSite = birkly_site_session_samesite();
    $secure = birkly_request_is_secure() || $sameSite === 'None';
    $cookiePath = birkly_session_cookie_path();
    session_name('birkly_site_sid');
    // Cross-origin club/storefront sends X-Birkly-Site-Session when third-party
    // cookies are blocked or stale. Always prefer the header over the cookie so
    // public_submit and other site-session APIs see the logged-in user.
    $headerSession = birkly_site_session_id_from_request_header();
    if ($headerSession !== null) {
        session_id($headerSession);
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => $sameSite
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
function birkly_resolve_csrf_session_scope(array $cookies, string $referer = '', string $requestUri = '', string $origin = ''): string {
    $uri = $requestUri !== '' ? $requestUri : ($_SERVER['REQUEST_URI'] ?? '');
    $fromAdmin = strpos($referer, '/admin/') !== false;
    $origin = $origin !== '' ? $origin : ($_SERVER['HTTP_ORIGIN'] ?? '');

    if (!empty($cookies['birkly_admin_sid']) || $fromAdmin) {
        return !empty($cookies['birkly_admin_sid']) ? 'admin' : 'cms';
    }

    // Storefront site users (club, account pages) share CSRF with birkly_site_sid.
    // Must run before the generic /api/ rule so /api/csrf.php matches the site session.
    if (!empty($cookies['birkly_site_sid'])) {
        return 'site';
    }

    // Club / storefront on a registered external domain (cross-origin from CMS host).
    if (birkly_is_cross_origin_registered_site_request($origin, $referer)) {
        return 'site';
    }

    // Admin panel and CMS API share the main PHP session when no site-user cookie is present.
    if ($uri !== '' && preg_match('#/(admin|api)(/|$|\?)#', $uri)) {
        return 'cms';
    }

    if (!empty($cookies['birkly_cms_sid'])) {
        return 'cms';
    }

    if (!empty($cookies['PHPSESSID'])) {
        return 'cms';
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