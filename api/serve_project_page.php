<?php
/**
 * Serve template-first project HTML with Birkly client bootstrap injected.
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/client_site_html.php';

$path = $_SERVER['BIRKLY_PAGE_PATH'] ?? ($_GET['path'] ?? '');
if (!is_string($path) || $path === '') {
    http_response_code(400);
    exit('Bad request');
}

if (!birkly_serve_project_page_html($path)) {
    http_response_code(404);
    exit('Not found');
}
