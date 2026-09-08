<?php
/**
 * Studio site viewer — query-param alias when /site/ rewrite is unavailable (P23).
 * Core stub: plugin registers handler via birkly_studio_view_request filter.
 */

require_once __DIR__ . '/../core/birkly_paths.php';
require_once __DIR__ . '/../api/session.php';
haru_secure_session_start();
require_once __DIR__ . '/../core/plugins.php';

load_plugins(dirname(__DIR__) . '/plugins');

$served = function_exists('apply_filters')
    ? apply_filters('birkly_studio_view_request', false)
    : false;

if ($served === true) {
    exit;
}

http_response_code(503);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><body><h1>Studio unavailable</h1><p>Activate the Studio plugin to preview projects.</p></body></html>';
