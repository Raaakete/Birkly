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
require_once __DIR__ . '/core/http_routes.php';
require_once __DIR__ . '/core/hosting_routing.php';

$base = birkly_base_path();
if ($base !== '' && str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base));
    if ($uri === '') {
        $uri = '/';
    }
}

if (birkly_hosting_dispatch($uri)) {
    return true;
}

if (birkly_dispatch_http_route($uri)) {
    return true;
}

$file = __DIR__ . $uri;

if (file_exists($file) && is_file($file)) {
    $originalUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if ($base !== '' && is_string($originalUri) && str_starts_with($originalUri, $base)) {
        $_SERVER['SCRIPT_NAME'] = birkly_url($uri);
        require $file;
        return true;
    }
    return false;
}

return false;
