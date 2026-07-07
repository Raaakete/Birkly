<?php
/**
 * Router script for PHP built-in server.
 *
 * Returns false for existing static files so PHP serves them normally.
 * Routes API and landing pages through the marketplace router.
 */

if (!isset($_SERVER['REQUEST_URI'])) {
    return false;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($uri === false || $uri === null) {
    return false;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Static assets under /public/
if (str_starts_with($uri, '/public/')) {
    $file = __DIR__ . $uri;
    if (is_file($file)) {
        return false;
    }
}

// Let direct PHP file access work for tests and tooling (non-API).
if (preg_match('/\.php$/', $uri) && !str_starts_with($uri, '/api/')) {
    $file = __DIR__ . $uri;
    if (is_file($file)) {
        return false;
    }
}

require_once __DIR__ . '/core/bootstrap.php';

if (marketplace_route($method, $uri)) {
    return true;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Not Found';
return true;
