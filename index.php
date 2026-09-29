<?php
// Fallback router for Birkly

require_once __DIR__ . '/core/birkly_paths.php';
require_once __DIR__ . '/core/hosting_routing.php';

// Note: This file should NOT be used as a router for PHP built-in server
// PHP files should execute directly without going through this router

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = birkly_base_path();
if ($base !== '' && str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base));
    if ($uri === '') {
        $uri = '/';
    }
}

if (birkly_deny_git_metadata_request($uri) || birkly_deny_private_tree_request($uri)) {
    exit;
}

// Studio published sites (P22) — nginx/other hosts that route unknown paths to index.php
if (str_starts_with($uri, '/site') && ($uri === '/site' || str_starts_with($uri, '/site/'))) {
    $entry = __DIR__ . '/api/studio_public.php';
    if (is_file($entry)) {
        require $entry;
        exit;
    }
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body><h1>Studio route not configured</h1>'
        . '<p>Missing <code>api/studio_public.php</code> on this server. Deploy the latest Birkly build and ensure '
        . '<code>/site/*</code> rewrites to that file (see <code>.htaccess</code> or nginx config in plugins/studio/README.md).</p></body></html>';
    exit;
}

// If accessing a PHP file directly, don't interfere - let PHP execute it
// This router should only handle non-PHP files and special routes
if (preg_match('/\.php$/', $uri)) {
    // Don't handle PHP files - let them execute normally
    return false;
}

$root = __DIR__;

// P43: settings-driven host roles, clean public URLs, admin canonical host
if (birkly_hosting_dispatch($uri)) {
    exit;
}

$file = realpath($root . $uri);

// Never serve project HTML or /project/ as raw static — requires SSR + client injection.
if ($file && str_starts_with($uri, '/project')) {
    require_once __DIR__ . '/core/hosting_urls.php';
    $hosting = birkly_hosting_load();
    if (($hosting['public_site_mode'] ?? 'none') === 'project') {
        $resolved = birkly_hosting_resolve_public_file($uri);
        if ($resolved !== null && preg_match('/\.html?$/i', $resolved)) {
            birkly_hosting_serve_public_file($resolved);
            exit;
        }
    }
}

// Serve static files directly (CMS host assets at web root)
if ($file && birkly_resolved_path_is_inside_root($file, $root) && is_file($file)) {
    $mime = mime_content_type($file);
    header('Content-Type: ' . $mime);
    // Public runtimes use versioned URLs + long cache in nginx — avoid duplicate headers here.
    $base = basename($file);
    if (preg_match('/^birkly-(client|webmcp|components)\.js$/', $base) !== 1
        && preg_match('/\.(?:js|css|svg|png|jpg|jpeg|webp|gif|ico|woff2?)$/i', $base) === 1) {
        header('Cache-Control: public, max-age=604800');
    }
    readfile($file);
    exit;
}

// Redirect /admin to /admin/index.php (not index.html - we use PHP for admin)
if ($uri === '/admin' || $uri === '/admin/') {
    header('Location: ' . birkly_hosting_admin_url('dashboard'), true, 302);
    exit;
}

// Fallback for /admin - redirect to PHP admin panel
if (preg_match('#^/admin($|/)#', $_SERVER['REQUEST_URI'] ?? '')) {
    header('Location: ' . birkly_hosting_admin_url('dashboard'), true, 302);
    exit;
}

require_once __DIR__ . '/core/http_routes.php';
if (birkly_dispatch_http_route($uri)) {
    exit;
}

// 404 for other requests
http_response_code(404);
echo 'Not found';
