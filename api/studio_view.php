<?php
/**
 * Studio site viewer — works on all hosts without /site/ rewrite (P23).
 * Admin "Open project" uses this URL; pretty /site/{slug}/ links use studio_public.php.
 */

require_once __DIR__ . '/../core/birkly_paths.php';
require_once __DIR__ . '/../api/session.php';
haru_secure_session_start();
require_once __DIR__ . '/../core/plugins.php';

load_plugins(dirname(__DIR__) . '/plugins');

require_once __DIR__ . '/../plugins/studio/includes/studio_public_uri.php';
require_once __DIR__ . '/../plugins/studio/includes/StudioRenderer.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') {
    http_response_code(400);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body><h1>Missing project</h1><p>No slug provided.</p></body></html>';
    exit;
}

$page = trim((string) ($_GET['page'] ?? ''));
$uri = '/site/' . rawurlencode($slug) . '/';
if ($page === 'styles.css') {
    $uri = '/site/' . rawurlencode($slug) . '/styles.css';
} elseif ($page !== '') {
    $uri = '/site/' . rawurlencode($slug) . '/' . rawurlencode($page) . '/';
}

$renderer = new StudioRenderer();
if ($renderer->handlePublicRequest($uri)) {
    exit;
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><body><h1>Not found</h1></body></html>';
