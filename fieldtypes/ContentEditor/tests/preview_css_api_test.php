<?php
/**
 * ContentEditor preview CSS API + core helper integration tests.
 */

require_once __DIR__ . '/../../../core/content_editor_preview_css.php';

$failures = 0;

$tmpdir = sys_get_temp_dir() . '/birkly-preview-css-' . bin2hex(random_bytes(4));
$projectRoot = $tmpdir . '/project';
if (!mkdir($projectRoot, 0755, true) && !is_dir($projectRoot)) {
    echo "FAIL: could not create temp project dir\n";
    exit(1);
}

$cssContent = "/* preview test */\n.post { color: #123456; }\n";
file_put_contents($projectRoot . '/birkly-subpage.css', $cssContent);

$normalized = birkly_content_editor_normalize_preview_css_path('/project/birkly-subpage.css');
if ($normalized !== 'birkly-subpage.css') {
    echo "FAIL: normalize /project/birkly-subpage.css\n";
    $failures++;
} else {
    echo "PASS: normalize /project/birkly-subpage.css\n";
}

$fs = new ProjectSiteFileSystem($projectRoot);
$file = birkly_content_editor_read_preview_css_file($fs, 'birkly-subpage.css');
if ($file === null || !str_contains($file['content'], '#123456')) {
    echo "FAIL: read birkly-subpage.css from project root\n";
    $failures++;
} else {
    echo "PASS: read birkly-subpage.css from project root\n";
}

$apiFile = __DIR__ . '/../../../api/content_editor_preview_css.php';
$apiSource = is_file($apiFile) ? file_get_contents($apiFile) : '';
if ($apiSource === '' || str_contains($apiSource, 'ContentEditor/ContentEditor.php')) {
    echo "FAIL: API endpoint should not require ContentEditor fieldtype class\n";
    $failures++;
} else {
    echo "PASS: API endpoint is self-contained\n";
}

if (!str_contains($apiSource, 'birkly_content_editor_read_preview_css_file')) {
    echo "FAIL: API endpoint uses core preview helper\n";
    $failures++;
} else {
    echo "PASS: API endpoint uses core preview helper\n";
}

require_once __DIR__ . '/../../../fieldtypes/BaseField.php';
require_once __DIR__ . '/../ContentEditor.php';

use Birkly\FieldTypes\ContentEditor\ContentEditor;

$proxy = ContentEditor::resolvePreviewCssFetchUrl('/project/birkly-subpage.css');
if ($proxy !== '/api/content_editor_preview_css.php?path=birkly-subpage.css') {
    echo "FAIL: ContentEditor proxy URL for birkly-subpage.css\n";
    $failures++;
} else {
    echo "PASS: ContentEditor proxy URL for birkly-subpage.css\n";
}

$readBack = ContentEditor::readProjectPreviewCssFile($fs, 'birkly-subpage.css');
if ($readBack === null || !str_contains($readBack['content'], '#123456')) {
    echo "FAIL: ContentEditor::readProjectPreviewCssFile delegation\n";
    $failures++;
} else {
    echo "PASS: ContentEditor::readProjectPreviewCssFile delegation\n";
}

// Cleanup
@unlink($projectRoot . '/birkly-subpage.css');
@rmdir($projectRoot);
@rmdir($tmpdir);

if ($failures > 0) {
    echo "\nRESULT: FAIL ($failures)\n";
    exit(1);
}

echo "\nRESULT: PASS\n";
exit(0);
