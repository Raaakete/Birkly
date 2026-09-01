<?php
/**
 * Authenticated proxy for ContentEditor project CSS preview.
 * Serves stylesheets from project/ for the admin visual editor.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../core/project_site_filesystem.php';
require_once __DIR__ . '/../fieldtypes/BaseField.php';
require_once __DIR__ . '/../fieldtypes/ContentEditor/ContentEditor.php';

use Birkly\FieldTypes\ContentEditor\ContentEditor;

requireAuth('read');

$path = trim((string) ($_GET['path'] ?? ''));
if ($path === '' && isset($_GET['url'])) {
    $path = ContentEditor::normalizeProjectCssPath((string) $_GET['url']) ?? '';
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

$fs = new ProjectSiteFileSystem();
$file = ContentEditor::readProjectPreviewCssFile($fs, $path);
if ($file === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Project CSS file not found';
    exit;
}

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: private, max-age=300');
echo $file['content'];
