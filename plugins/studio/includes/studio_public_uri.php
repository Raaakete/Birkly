<?php
/**
 * Resolve the /site/… path for Studio SSR (LiteSpeed-safe).
 */

/**
 * Build synthetic /site/{slug}/ URI from query params or server vars.
 */
function studio_resolve_public_uri(): string
{
    $slug = trim((string) ($_GET['slug'] ?? ''));
    if ($slug !== '') {
        $page = trim((string) ($_GET['page'] ?? ''));
        if ($page === 'styles.css') {
            return '/site/' . rawurlencode($slug) . '/styles.css';
        }
        if ($page !== '') {
            return '/site/' . rawurlencode($slug) . '/' . rawurlencode($page) . '/';
        }

        return '/site/' . rawurlencode($slug) . '/';
    }

    $candidates = [
        $_SERVER['HTTP_X_ORIGINAL_URL'] ?? '',
        $_SERVER['REDIRECT_URL'] ?? '',
        $_SERVER['REDIRECT_URI'] ?? '',
        $_SERVER['REQUEST_URI'] ?? '/site/',
    ];

    $base = function_exists('birkly_base_path') ? birkly_base_path() : '';
    foreach ($candidates as $raw) {
        if ($raw === '') {
            continue;
        }
        $path = parse_url($raw, PHP_URL_PATH) ?: '';
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
            if ($path === '') {
                $path = '/';
            }
        }
        if (preg_match('#^/site(?:/|$)#', $path)) {
            return $path === '/site' ? '/site/' : $path;
        }
    }

    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/site/', PHP_URL_PATH) ?: '/site/';
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
        if ($uri === '') {
            $uri = '/';
        }
    }

    return $uri;
}
