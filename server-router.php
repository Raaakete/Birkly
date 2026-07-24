<?php
/**
 * Router script for PHP built-in server
 *
 * CRITICAL: This router script MUST return false for existing files
 * to let PHP execute them. Returning false tells PHP's built-in server
 * to handle the file normally (execute PHP, serve static files).
 *
 * DO NOT output anything, DO NOT echo, DO NOT call readfile().
 * Just return false for all files.
 */

if (!isset($_SERVER['REQUEST_URI'])) {
    return false;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($uri === false || $uri === null) {
    return false;
}

require_once __DIR__ . '/core/birkly_paths.php';

$base = birkly_base_path();
if ($base !== '' && str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base));
    if ($uri === '') {
        $uri = '/';
    }
}

$oauthRoutes = [
    '/health' => '/api/health.php',
    '/favicon.ico' => '/admin/assets/birkly-icon.svg',
    '/.well-known/oauth-protected-resource' => '/api/oauth_protected_resource.php',
    '/.well-known/oauth-authorization-server' => '/api/oauth_authorization_server.php',
    '/oauth/authorize' => '/api/oauth_authorize.php',
    '/oauth/token' => '/api/oauth_token.php',
    '/oauth/register' => '/api/oauth_register.php',
    '/mcp' => '/api/mcp.php',
];

foreach ($oauthRoutes as $prefix => $handler) {
    if ($uri === $prefix || str_starts_with($uri, $prefix . '/')) {
        $_SERVER['SCRIPT_NAME'] = birkly_url($handler);
        if ($prefix === '/favicon.ico') {
            header('Content-Type: image/svg+xml');
            header('Cache-Control: public, max-age=86400');
            readfile(__DIR__ . $handler);
            return true;
        }
        require __DIR__ . $handler;
        return true;
    }
}

if (str_starts_with($uri, '/site') && ($uri === '/site' || str_starts_with($uri, '/site/'))) {
    require __DIR__ . '/api/studio_public.php';
    return true;
}

require_once __DIR__ . '/core/plugin_public_routes.php';
if (!function_exists('load_plugins')) {
    require_once __DIR__ . '/core/plugins.php';
}
if (function_exists('load_plugins')) {
    load_plugins(__DIR__ . '/plugins');
}
if (function_exists('birkly_dispatch_public_routes') && birkly_dispatch_public_routes($uri)) {
    return true;
}

$file = __DIR__ . $uri;

if (file_exists($file) && is_file($file)) {
    if (preg_match('#^/project/.+\.html$#i', $uri)) {
        require_once __DIR__ . '/core/client_site_html.php';
        if (birkly_serve_project_page_html($uri)) {
            return true;
        }
    }

    $originalUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if ($base !== '' && is_string($originalUri) && str_starts_with($originalUri, $base)) {
        $_SERVER['SCRIPT_NAME'] = birkly_url($uri);
        require $file;
        return true;
    }
    return false;
}

return false;
