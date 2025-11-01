<?php
// Fallback router for HaruCMS
// Note: This file should NOT be used as a router for PHP built-in server
// PHP files should execute directly without going through this router

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

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

// Serve admin index.html for /admin or /
if ($uri === '/admin' || $uri === '/admin/' || $uri === '/') {
    $index = $root . '/admin/index.html';
    if (file_exists($index)) {
        header('Content-Type: text/html');
        readfile($index);
        exit;
    }
}

// Fallback for /admin SPA
if (preg_match('#^/admin($|/)#', $_SERVER['REQUEST_URI'])) {
    $index = $root . '/admin/index.html';
    if (file_exists($index)) {
        header('Content-Type: text/html');
        readfile($index);
        exit;
    }
}

// 404 for other requests
http_response_code(404);
echo 'Not found'; 