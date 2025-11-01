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

// Handle cases where REQUEST_URI might not be set
if (!isset($_SERVER['REQUEST_URI'])) {
    return false;
}

// Get the URI path (ignore query string for file checking)
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($uri === false || $uri === null) {
    return false;
}

// Get the actual file path
$file = __DIR__ . $uri;

// For ALL existing files, return false to let PHP handle them
// PHP will execute .php files and serve other files as static content
if (file_exists($file) && is_file($file)) {
    return false;
}

// For non-existent files, also return false to let PHP return 404
return false;
