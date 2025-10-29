<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Simple metadata fetcher - like metatags.io
function fetchWebsiteMetadata($url) {
    try {
        // Fetch the website HTML
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Mozilla/5.0 (compatible; HaruCMS/1.0)'
            ]
        ]);
        
        $html = file_get_contents($url, false, $context);
        
        if ($html === false) {
            throw new Exception('Failed to fetch website');
        }
        
        // Parse HTML to extract metadata
        $metadata = [
            'title' => '',
            'description' => '',
            'image' => '',
            'favicon' => ''
        ];
        
        // Extract title
        if (preg_match('/<title[^>]*>(.*?)<\/title>/i', $html, $matches)) {
            $metadata['title'] = trim($matches[1]);
        }
        
        // Extract meta description
        if (preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $matches)) {
            $metadata['description'] = trim($matches[1]);
        }
        
        // Extract Open Graph description
        if (empty($metadata['description']) && preg_match('/<meta[^>]*property=["\']og:description["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $matches)) {
            $metadata['description'] = trim($matches[1]);
        }
        
        // Extract Open Graph image
        if (preg_match('/<meta[^>]*property=["\']og:image["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $matches)) {
            $metadata['image'] = trim($matches[1]);
        }
        
        // Extract favicon
        if (preg_match('/<link[^>]*rel=["\'](?:shortcut )?icon["\'][^>]*href=["\']([^"\']*)["\']/i', $html, $matches)) {
            $metadata['favicon'] = trim($matches[1]);
        }
        
        // Make relative URLs absolute
        if (!empty($metadata['image']) && !preg_match('/^https?:\/\//', $metadata['image'])) {
            $metadata['image'] = rtrim($url, '/') . '/' . ltrim($metadata['image'], '/');
        }
        
        if (!empty($metadata['favicon']) && !preg_match('/^https?:\/\//', $metadata['favicon'])) {
            $metadata['favicon'] = rtrim($url, '/') . '/' . ltrim($metadata['favicon'], '/');
        }
        
        return [
            'success' => true,
            'data' => $metadata
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $url = $input['url'] ?? '';
} else {
    $url = $_GET['url'] ?? '';
}

if (empty($url)) {
    echo json_encode(['success' => false, 'error' => 'URL parameter required']);
    exit;
}

// Validate URL
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid URL']);
    exit;
}

// Fetch and return metadata
$result = fetchWebsiteMetadata($url);
echo json_encode($result);
?>
