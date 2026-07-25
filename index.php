<?php
// Fallback router for Birkly

require_once __DIR__ . '/core/birkly_paths.php';

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
$file = realpath($root . $uri);

// Serve static files directly
if ($file && strpos($file, $root) === 0 && is_file($file)) {
    $mime = mime_content_type($file);
    header('Content-Type: ' . $mime);
    readfile($file);
    exit;
}

// Redirect /admin to /admin/index.php (not index.html - we use PHP for admin)
if ($uri === '/admin' || $uri === '/admin/') {
    header('Location: ' . birkly_admin_url('dashboard'), true, 302);
    exit;
}

// Fallback for /admin - redirect to PHP admin panel
if (preg_match('#^/admin($|/)#', $_SERVER['REQUEST_URI'] ?? '')) {
    header('Location: ' . birkly_admin_url('dashboard'), true, 302);
    exit;
}

// CMS host root → admin; marketing host root → public project site
if ($uri === '/') {
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if (strpos($host, ':') !== false) {
        $host = explode(':', $host, 2)[0];
    }
    if ($host !== '' && str_starts_with($host, 'cms.')) {
        header('Location: ' . birkly_admin_url('dashboard'), true, 302);
        exit;
    }
    if (is_dir($root . '/project')) {
        $projectIndex = $root . '/project/index.html';
        $target = is_file($projectIndex) ? '/project/index.html' : '/project/';
        header('Location: ' . birkly_url($target), true, 302);
        exit;
    }
}

require_once __DIR__ . '/core/http_routes.php';
if (birkly_dispatch_http_route($uri)) {
    exit;
}

// 404 for other requests
http_response_code(404);
echo 'Not found'; 