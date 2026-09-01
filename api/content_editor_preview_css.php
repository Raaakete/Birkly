<?php
/**
 * Authenticated proxy for ContentEditor project CSS preview.
 * Serves stylesheets from project/ for the admin visual editor.
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/cors.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../core/content_editor_preview_css.php';

set_cors_headers('admin');
haru_secure_session_start();
requireAuth('read');

$path = trim((string) ($_GET['path'] ?? ''));
if ($path === '' && isset($_GET['url'])) {
    $path = birkly_content_editor_normalize_preview_css_path((string) $_GET['url']) ?? '';
}

if ($path === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Missing or invalid project CSS path';
    exit;
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
if ($ext !== 'css') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Only .css files are allowed';
    exit;
}

try {
    $fs = new ProjectSiteFileSystem();
    $file = birkly_content_editor_read_preview_css_file($fs, $path);
} catch (Throwable $e) {
    error_log('content_editor_preview_css: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Could not read project stylesheet';
    exit;
}

if ($file === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Project CSS file not found';
    exit;
}

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: private, max-age=300');
echo $file['content'];
