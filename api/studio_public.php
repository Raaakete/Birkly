<?php
/**
 * Public SSR entry for Studio published sites (P22-M9).
 * Apache: .htaccess routes /site/* here. Dev: server-router dispatches via register_public_route.
 */

require_once __DIR__ . '/../core/birkly_paths.php';
require_once __DIR__ . '/../core/plugins.php';

load_plugins(dirname(__DIR__) . '/plugins');

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/site/', PHP_URL_PATH) ?: '/site/';
$base = birkly_base_path();
if ($base !== '' && str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base));
    if ($uri === '') {
        $uri = '/';
    }
}

require_once __DIR__ . '/../plugins/studio/includes/StudioRenderer.php';
$renderer = new StudioRenderer();
if ($renderer->handlePublicRequest($uri)) {
    exit;
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><body><h1>Not found</h1></body></html>';
