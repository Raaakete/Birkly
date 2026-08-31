<?php
/**
 * Lightweight metadata proxy for the ContentEditor link preview feature.
 * Fetches the remote URL and extracts Open Graph / basic meta tags.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../api/auth.php';
require_once __DIR__ . '/../../core/ai_presence.php';

header('Content-Type: application/json; charset=utf-8');

requireAuth('read');

$url = $_GET['url'] ?? '';
$url = trim($url);

if ($url === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'URL is required']);
    exit;
}

if (!preg_match('#^https?://#i', $url)) {
    $url = 'http://' . $url;
}

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid URL']);
    exit;
}

if (!birkly_ai_presence_is_safe_external_url($url)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'URL is not allowed']);
    exit;
}

// Fetch remote HTML
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_USERAGENT => 'Birkly-ContentEditor/1.0 (+https://github.com/Raaakete/Birkly)',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
]);

$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($html === false || $httpCode >= 400) {
    $error = curl_error($ch) ?: "HTTP {$httpCode}";
    curl_close($ch);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch URL: ' . $error]);
    exit;
}

curl_close($ch);

// Limit HTML size to avoid excessive processing
$html = substr($html, 0, 500000);

libxml_use_internal_errors(true);
$dom = new DOMDocument();
$loaded = $dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
libxml_clear_errors();

if (!$loaded) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to parse HTML']);
    exit;
}

$xpath = new DOMXPath($dom);

$metadata = [
    'title' => null,
    'description' => null,
    'image' => null,
    'site_name' => null,
    'url' => $url,
];

// Collect meta tags (case-insensitive)
$metaNodes = $xpath->query('//meta');
if ($metaNodes) {
    foreach ($metaNodes as $node) {
        $nameAttr = strtolower($node->getAttribute('name'));
        $propAttr = strtolower($node->getAttribute('property'));
        $content = trim($node->getAttribute('content'));
        if ($content === '') {
            continue;
        }
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        switch ($propAttr) {
            case 'og:title':
                $metadata['title'] = $metadata['title'] ?: $content;
                break;
            case 'og:description':
                $metadata['description'] = $metadata['description'] ?: $content;
                break;
            case 'og:image':
                $metadata['image'] = $metadata['image'] ?: $content;
                break;
            case 'og:site_name':
                $metadata['site_name'] = $metadata['site_name'] ?: $content;
                break;
        }
        
        switch ($nameAttr) {
            case 'twitter:title':
                $metadata['title'] = $metadata['title'] ?: $content;
                break;
            case 'twitter:description':
                $metadata['description'] = $metadata['description'] ?: $content;
                break;
            case 'twitter:image':
                $metadata['image'] = $metadata['image'] ?: $content;
                break;
            case 'description':
                $metadata['description'] = $metadata['description'] ?: $content;
                break;
        }
    }
}

if (!$metadata['title']) {
    $titleNodes = $xpath->query('//title');
    if ($titleNodes && $titleNodes->length > 0) {
        $metadata['title'] = trim($titleNodes->item(0)->textContent);
    }
}

if (!$metadata['description']) {
    $paragraphs = $xpath->query('//p');
    if ($paragraphs) {
        foreach ($paragraphs as $p) {
            $text = trim(preg_replace('/\s+/', ' ', $p->textContent));
            if (strlen($text) >= 40) {
                $metadata['description'] = $text;
                break;
            }
        }
    }
}

// Provider-specific fallbacks (e.g., YouTube)
function youtube_id_from_url($url) {
    $patterns = [
        '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([^&\n?#]+)/',
        '/youtube\.com\/watch\?.*v=([^&\n?#]+)/'
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
    }
    return null;
}

$lowerUrl = strtolower($url);
if (strpos($lowerUrl, 'youtube.com') !== false || strpos($lowerUrl, 'youtu.be') !== false) {
    $youtubeId = youtube_id_from_url($url);
    if ($youtubeId) {
        if (!$metadata['title']) {
            $metadata['title'] = 'YouTube Video';
        }
        if (!$metadata['description']) {
            $metadata['description'] = 'Watch this video on YouTube.';
        }
        if (!$metadata['image']) {
            $metadata['image'] = 'https://img.youtube.com/vi/' . $youtubeId . '/hqdefault.jpg';
        }
        $metadata['site_name'] = $metadata['site_name'] ?: 'YouTube';
    }
}

// Ensure image URLs are absolute
if ($metadata['image']) {
    $imageUrl = $metadata['image'];
    if (strpos($imageUrl, '//') === 0) {
        $metadata['image'] = 'https:' . $imageUrl;
    } elseif (!preg_match('#^https?://#i', $imageUrl)) {
        $parsed = parse_url($url);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $base = $scheme . '://' . $host . $port;
        if (substr($imageUrl, 0, 1) === '/') {
            $metadata['image'] = $base . $imageUrl;
        } else {
            $path = $parsed['path'] ?? '';
            $dir = rtrim(substr($path, 0, strrpos($path, '/') + 1), '/');
            $metadata['image'] = $base . '/' . ($dir ? $dir . '/' : '') . $imageUrl;
        }
    }
}

if (!$metadata['site_name']) {
    $parsed = parse_url($url);
    if (!empty($parsed['host'])) {
        $metadata['site_name'] = $parsed['host'];
    }
}

echo json_encode([
    'success' => true,
    'data' => $metadata,
]);
