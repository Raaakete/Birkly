<?php
/**
 * Router for demo websites
 * Handles rendering of demo website templates with data
 */

// Get the request URI and determine which demo website to render
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Check if this is a demo website request (e.g., /demo-blog/index.html)
// Match /demo-blog or /demo-blog/...
if (!preg_match('#^/demo-blog(/.*)?$#', $uri, $matches)) {
    // Not a demo website request, return false to let server handle it
    return false;
}

$path = $matches[1] ?? '/index.html';
$path = ltrim($path, '/'); // Remove leading slash

// If path is empty or just '/', default to index.html
if (empty($path) || $path === '/') {
    $path = 'index.html';
}

// Check if the demo website directory exists
$websiteDir = __DIR__ . '/demo-blog';
if (!is_dir($websiteDir)) {
    http_response_code(404);
    echo "Demo website not found";
    exit;
}

// Get the requested file
$requestedFile = "$websiteDir/$path";

// If it's a static file (CSS, JS, images), serve it directly
$extension = pathinfo($requestedFile, PATHINFO_EXTENSION);
if (in_array($extension, ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot'])) {
    if (file_exists($requestedFile)) {
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject'
        ];
        $mime = $mimeTypes[$extension] ?? mime_content_type($requestedFile);
        header("Content-Type: $mime");
        readfile($requestedFile);
        exit;
    }
}

// For HTML files, render with template engine
if ($extension === 'html' && file_exists($requestedFile)) {
    // Include the template parser
    require_once __DIR__ . '/admin/template_parser.php';
    require_once __DIR__ . '/api/public.php';
    
    // Get settings for site title
    $settingsPath = __DIR__ . '/settings/site.json';
    $siteTitle = 'HaruCMS';
    if (file_exists($settingsPath)) {
        $settings = json_decode(file_get_contents($settingsPath), true);
        $siteTitle = $settings['name'] ?? 'HaruCMS';
    }
    
    // Load the template
    $template = file_get_contents($requestedFile);
    
    // Get blog entries if needed (for blog demo)
    $blogEntries = [];
    $blogCollectionPath = __DIR__ . '/content/collections/blog/entries';
    if (is_dir($blogCollectionPath)) {
        $entryFiles = glob($blogCollectionPath . '/*.json');
        foreach ($entryFiles as $entryFile) {
            $entryData = json_decode(file_get_contents($entryFile), true);
            if ($entryData && ($entryData['status'] ?? 'published') === 'published') {
                $blogEntries[] = $entryData;
            }
        }
        // Sort by created_at descending
        usort($blogEntries, function($a, $b) {
            $timeA = strtotime($a['created_at'] ?? '');
            $timeB = strtotime($b['created_at'] ?? '');
            return $timeB - $timeA;
        });
    }
    
    // Prepare template data
    // The template uses {for each entry in 'blog'} which means it expects a variable named 'blog'
    // We also need to handle the collection name 'blog' dynamically
    $data = [
        'site_title' => $siteTitle,
        'current_year' => date('Y'),
        'blog' => $blogEntries, // Collection name as array of entries
        'entries' => $blogEntries, // Alternative name
        // Add individual entries to data for direct access
    ];
    
    // Add entries as individual variables if template needs them
    // For templates like {for each entry in 'blog'}, the parser will look for $blog array
    
    // Create template parser instance
    $parser = new TemplateParser($data);
    
    // Render the template
    try {
        $output = $parser->render($template);
        header('Content-Type: text/html; charset=utf-8');
        echo $output;
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo "Error rendering template: " . htmlspecialchars($e->getMessage());
        exit;
    }
}

// File not found
http_response_code(404);
echo "File not found: $path";
exit;

