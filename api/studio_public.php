<?php
/**
 * Public route alias entry (Apache / legacy). Dispatches via plugin register_public_route shelf.
 */

require_once __DIR__ . '/../core/birkly_paths.php';
require_once __DIR__ . '/../api/session.php';
haru_secure_session_start();
require_once __DIR__ . '/../core/plugins.php';
require_once __DIR__ . '/../core/plugin_public_routes.php';

load_plugins(dirname(__DIR__) . '/plugins');

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/site/', PHP_URL_PATH) ?: '/site/';
if (function_exists('birkly_dispatch_public_routes') && birkly_dispatch_public_routes($uri)) {
    exit;
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><body><h1>Not found</h1></body></html>';
