<?php
/**
 * HTTP routing helpers for configurable hosting (P43).
 */

declare(strict_types=1);

require_once __DIR__ . '/hosting_urls.php';
require_once __DIR__ . '/project_site.php';

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
    }
    header('Content-Type: ' . $mime);
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
        if ($publicFile !== null && ($role === 'public' || $role === 'local' || $role === 'cms')) {
            birkly_hosting_serve_file($publicFile);
        }
    }

    if ($uri === '/') {
        $role = birkly_request_host_role();
        if ($config['public_site_mode'] === 'project' && ($role === 'public' || $role === 'local')) {
            $publicFile = birkly_hosting_resolve_public_file('/');
            if ($publicFile !== null) {
                birkly_hosting_serve_file($publicFile);
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
