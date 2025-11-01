<?php
/**
 * Router script for PHP built-in server
 * 
 * IMPORTANT: In PHP's built-in server, if a router script returns false,
 * PHP will handle the request normally (execute PHP files, serve static files).
 * 
 * If we don't return false (or output something), PHP serves files as plain text.
 */

// Parse the URI to get the path (ignore query string for file checking)
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Get the actual file path
$file = __DIR__ . $uri;

// Check if the requested file exists
if (file_exists($file) && is_file($file)) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    
    // For PHP files, return false to let PHP execute them
    if ($ext === 'php') {
        // Return false = tell PHP to execute this file normally
        return false;
    }
    
    // For static files (CSS, JS, images), also return false
    // This lets PHP's built-in server serve them normally
    return false;
}

// For non-existent files, return false to let PHP return 404
// CRITICAL: We must return false, not true or anything else
return false;

