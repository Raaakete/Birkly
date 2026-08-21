<?php
/**
 * HTTP routing helpers for configurable hosting (P43).
 */

declare(strict_types=1);

require_once __DIR__ . '/hosting_urls.php';
require_once __DIR__ . '/project_site.php';
require_once __DIR__ . '/hosting_redirects.php';

/**
 * Serve a resolved public file. For project HTML this first attempts SSR
 * (P51-M2) when enabled, then falls back to the static file on any failure.
 */
function birkly_hosting_serve_public_file(string $filePath): void
{
    require_once __DIR__ . '/project_ssr.php';

    if (birkly_project_ssr_should_render($filePath)) {
        $query = isset($_GET) && is_array($_GET) ? $_GET : [];
        if (birkly_project_ssr_serve($filePath, $query)) {
            exit;
        }
        // SSR opted-in but failed → static file + client-side render marker.
        if (!headers_sent()) {
            header('X-Birkly-Render: client-fallback');
            $reason = birkly_project_ssr_last_error();
            if ($reason === null || $reason === '') {
                $status = birkly_project_ssr_runtime_status();
                $reason = $status['reason'] ?? 'unknown';
            }
            // Short machine code only — safe for public responses / ops debugging.
            header('X-Birkly-SSR-Fallback: ' . preg_replace('/[^a-z0-9_]/', '', (string) $reason));
        }
    }

    birkly_hosting_serve_file($filePath);
}

function birkly_hosting_serve_favicon_if_available(): bool
{
    $file = birkly_hosting_resolve_favicon_file();
    if ($file === null) {
        return false;
    }

    birkly_hosting_serve_file($file);
    return true;
}

function birkly_hosting_serve_file(string $filePath): void
{
    $mime = mime_content_type($filePath) ?: 'application/octet-stream';
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if ($ext === 'css') {
        $mime = 'text/css; charset=UTF-8';
    } elseif ($ext === 'js') {
        $mime = 'application/javascript; charset=UTF-8';
    } elseif ($ext === 'json') {
        $mime = 'application/json; charset=UTF-8';
    } elseif ($ext === 'svg') {
        $mime = 'image/svg+xml';
    } elseif ($ext === 'ico') {
        $mime = 'image/x-icon';
    } elseif ($ext === 'png') {
        $mime = 'image/png';
    }
    header('Content-Type: ' . $mime);
    if ($ext === 'html' || $ext === 'htm') {
        $html = (string) file_get_contents($filePath);
        echo birkly_project_prepare_html_for_serve($html);
        exit;
    }
    if ($ext === 'txt' && basename($filePath) === 'robots.txt') {
        require_once __DIR__ . '/ai_presence.php';
        $body = (string) file_get_contents($filePath);
        echo birkly_ai_presence_robots_txt_augment($body);
        exit;
    }
    readfile($filePath);
    exit;
}

/**
 * Handle admin canonical host redirects and public-site clean URLs.
 * Returns true when the request was handled.
 */
function birkly_hosting_dispatch(string $uri): bool
{
    birkly_ensure_public_site_path();
    $config = birkly_hosting_load();

    if (preg_match('#^/demo-website(/|$)#', $uri) === 1) {
        $target = preg_replace('#^/demo-website#', '/project', $uri, 1);
        if ($target === '') {
            $target = '/project/';
        }
        header('Location: ' . birkly_url($target), true, 301);
        exit;
    }

    birkly_hosting_maybe_redirect_alias_to_primary($uri);
    birkly_hosting_apply_redirect($uri);

    if ($uri === '/favicon.ico' || $uri === '/apple-touch-icon.png') {
        if (birkly_hosting_serve_favicon_if_available()) {
            return true;
        }
    }

    if (preg_match('#^/admin($|/)#', $uri)) {
        if (birkly_hosting_should_redirect_admin()) {
            $target = birkly_hosting_admin_url('dashboard');
            header('Location: ' . $target, true, 302);
            exit;
        }
        return false;
    }

    if ($config['public_site_mode'] === 'project') {
        $role = birkly_request_host_role();
        if (($role === 'public' || $role === 'local') && str_starts_with($uri, '/project')) {
            $clean = birkly_hosting_clean_public_url($uri);
            $publicBase = birkly_hosting_public_base_url();
            if ($clean !== $uri && $publicBase !== '') {
                header('Location: ' . rtrim($publicBase, '/') . ($clean === '/' ? '/' : $clean), true, 301);
                exit;
            }
        }

        $publicFile = birkly_hosting_resolve_public_file($uri);
        if ($publicFile !== null && birkly_hosting_role_can_serve_public_project($role, $uri)) {
            birkly_hosting_serve_public_file($publicFile);
        }
    }

    if ($uri === '/') {
        $role = birkly_request_host_role();
        if ($config['public_site_mode'] === 'project' && birkly_hosting_role_can_serve_public_project($role, '/')) {
            $publicFile = birkly_hosting_resolve_public_file('/');
            if ($publicFile !== null) {
                birkly_hosting_serve_public_file($publicFile);
            }
        }

        $redirect = birkly_hosting_root_redirect_url();
        if ($redirect !== null) {
            $current = rtrim(birkly_hosting_build_url_from_request(), '/') . '/';
            $target = rtrim($redirect, '/') . '/';
            if ($target !== $current) {
                header('Location: ' . $redirect, true, 302);
                exit;
            }
        }

        return false;
    }

    return false;
}
