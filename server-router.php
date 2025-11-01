<?php
/**
 * Router script for PHP built-in server
 * This ensures PHP files are executed correctly instead of being served as plain text
 */

// Parse the URI to get the path (ignore query string for file checking)
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Get the actual file path
$file = __DIR__ . $uri;

// If it's a PHP file that exists, let PHP execute it (don't interfere)
if (file_exists($file) && is_file($file)) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if ($ext === 'php') {
        // Return false to tell PHP's built-in server to execute this file
        // This is crucial - without this, PHP files would be served as plain text
        return false;
    }
}

// For static files (CSS, JS, images, etc.), also let PHP handle them normally
// For non-existent files, return false to let PHP return 404
return false;

