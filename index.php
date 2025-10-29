<?php
// Fallback router for HaruCMS
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = __DIR__;
$file = realpath($root . $uri);
// Serve file directly if it exists and is inside the project
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
// Optionally, fallback for public SPA or 404
http_response_code(404);
echo 'Not found'; 