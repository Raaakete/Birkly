<?php
/**
 * Router script for PHP built-in server
 * This ensures PHP files are executed correctly
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Get the actual file path
$file = __DIR__ . $uri;

// If it's a PHP file that exists, let PHP execute it (don't interfere)
if (file_exists($file) && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
    // Return false to tell PHP's built-in server to handle this file normally
    return false;
}

// For all other requests, let PHP's built-in server handle them
// This includes static files (CSS, JS, images, etc.)
return false;

