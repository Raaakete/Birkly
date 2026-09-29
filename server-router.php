<?php
/**
 * Router script for PHP built-in server
 *
 * CRITICAL: This router script MUST return false for existing files
 * to let PHP execute them. Returning false tells PHP's built-in server
 * to handle the file normally (execute PHP, serve static files).
 *
 * DO NOT output anything for files the built-in server should serve.
 * Return false for those files. A private-tree request is refused here.
 */

if (!isset($_SERVER['REQUEST_URI'])) {
    return false;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($uri === false || $uri === null) {
    return false;
}

require_once __DIR__ . '/core/birkly_paths.php';
require_once __DIR__ . '/core/project_site.php';

$routedUri = $uri;
$base = birkly_base_path();
if ($base !== '' && str_starts_with($routedUri, $base)) {
    $routedUri = substr($routedUri, strlen($base));
    if ($routedUri === '') {
        $routedUri = '/';
    }
}

if (birkly_deny_git_metadata_request($routedUri) || birkly_deny_private_tree_request($routedUri)) {
    return true;
}

// Fast path for static assets on PHP's built-in server: do not boot routes/plugins.
// Admin CSS uses many @import files; without this, every .css/.js hit pays full bootstrap
// and serializes behind the single worker — making local admin feel unusable.
// Note: do NOT include .html here — project HTML may need SSR via hosting_dispatch.
$staticExt = '#\.(css|js|mjs|map|png|jpe?g|gif|svg|webp|ico|woff2?|ttf|eot)$#i';
if (preg_match($staticExt, $routedUri) === 1) {
    $staticCandidate = __DIR__ . $routedUri;
    if (is_file($staticCandidate)) {
        return false;
    }
}

require_once __DIR__ . '/core/http_routes.php';
require_once __DIR__ . '/core/hosting_routing.php';

$uri = $routedUri;

// Retry static fast-path after base-path rewrite (subdirectory installs).
if (preg_match($staticExt, $uri) === 1) {
    $staticCandidate = __DIR__ . $uri;
    if (is_file($staticCandidate)) {
        return false;
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
