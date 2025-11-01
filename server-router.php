<?php
/**
 * Router script for PHP built-in server
 * 
 * CRITICAL: In PHP's built-in server, a router script MUST return false
 * to let PHP execute files. Any other behavior causes files to be served as plain text.
 * 
 * DO NOT output anything, DO NOT echo, DO NOT call readfile().
 * Just return false for files that should be executed/served normally.
 */

// Get the URI path (ignore query string)
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $uri;

// For ALL existing files (PHP or static), return false to let PHP handle them
if (file_exists($file) && is_file($file)) {
    // Return false = tell PHP built-in server to handle this file normally
    // PHP will execute .php files and serve other files as static content
    return false;
}

// For non-existent files, also return false to let PHP return 404
return false;
