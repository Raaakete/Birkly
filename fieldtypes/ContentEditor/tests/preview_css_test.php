<?php
/**
 * P2-M3-T5 — ContentEditor project CSS preview assets and markup.
 */

require_once __DIR__ . '/../../../fieldtypes/BaseField.php';
require_once __DIR__ . '/../ContentEditor.php';

use Birkly\FieldTypes\ContentEditor\ContentEditor;

$failures = 0;

$demoCss = __DIR__ . '/../preview/demo-preview.css';
if (!is_file($demoCss)) {
    echo "FAIL: bundled demo-preview.css missing\n";
    $failures++;
} else {
    echo "PASS: bundled demo-preview.css exists\n";
}

$editor = new ContentEditor([
    'name' => 'content',
    'label' => 'Content',
    'value' => '<p>Hi</p>',
    'css_url' => '/project/styles.css',
]);

$html = $editor->render();
if (strpos($html, 'data-css-url="/project/styles.css"') === false) {
    echo "FAIL: render exposes data-css-url\n";
    $failures++;
} else {
    echo "PASS: render exposes data-css-url\n";
}

if (ContentEditor::normalizeProjectCssPath('/project/css/style.css') !== 'css/style.css') {
    echo "FAIL: normalizeProjectCssPath strips /project/\n";
    $failures++;
} else {
    echo "PASS: normalizeProjectCssPath strips /project/\n";
}

if (ContentEditor::normalizeProjectCssPath('css/style.css') !== 'css/style.css') {
    echo "FAIL: normalizeProjectCssPath accepts relative path\n";
    $failures++;
} else {
    echo "PASS: normalizeProjectCssPath accepts relative path\n";
}

if (ContentEditor::normalizeProjectCssPath('https://example.com/site.css') !== null) {
    echo "FAIL: normalizeProjectCssPath rejects external URL\n";
    $failures++;
} else {
    echo "PASS: normalizeProjectCssPath rejects external URL\n";
}

$proxyUrl = ContentEditor::resolvePreviewCssFetchUrl('/project/css/style.css');
if ($proxyUrl === '' || !str_contains($proxyUrl, 'content_editor_preview_css.php?path=css%2Fstyle.css')) {
    echo "FAIL: resolvePreviewCssFetchUrl uses proxy for project path\n";
    $failures++;
} else {
    echo "PASS: resolvePreviewCssFetchUrl uses proxy for project path\n";
}

$bundledUrl = ContentEditor::resolvePreviewCssFetchUrl(ContentEditor::DEFAULT_PREVIEW_CSS);
if ($bundledUrl === '' || !str_contains($bundledUrl, '/fieldtypes/ContentEditor/preview/demo-preview.css')) {
    echo "FAIL: resolvePreviewCssFetchUrl resolves bundled preview CSS path\n";
    $failures++;
} else {
    echo "PASS: resolvePreviewCssFetchUrl resolves bundled preview CSS path\n";
}

$externalUrl = ContentEditor::resolvePreviewCssFetchUrl('https://cdn.example.com/theme.css');
if ($externalUrl !== 'https://cdn.example.com/theme.css') {
    echo "FAIL: resolvePreviewCssFetchUrl keeps external URL direct\n";
    $failures++;
} else {
    echo "PASS: resolvePreviewCssFetchUrl keeps external URL direct\n";
}

$proxyPhp = __DIR__ . '/../../../api/content_editor_preview_css.php';
if (!is_file($proxyPhp)) {
    echo "FAIL: content_editor_preview_css.php API endpoint missing\n";
    $failures++;
} else {
    echo "PASS: content_editor_preview_css.php API endpoint exists\n";
}

$legacyProxyPhp = __DIR__ . '/../preview-css.php';
if (!is_file($legacyProxyPhp) || strpos(file_get_contents($legacyProxyPhp), 'content_editor_preview_css.php') === false) {
    echo "FAIL: legacy preview-css.php shim\n";
    $failures++;
} else {
    echo "PASS: legacy preview-css.php shim\n";
}

if (strpos($html, 'content_editor_preview_css.php?path=styles.css') === false) {
    echo "FAIL: render exposes project CSS proxy fetch URL\n";
    $failures++;
} else {
    echo "PASS: render exposes project CSS proxy fetch URL\n";
}

if (strpos($html, 'content-editor-preview-badge') === false) {
    echo "FAIL: preview badge in toolbar\n";
    $failures++;
} else {
    echo "PASS: preview badge in toolbar\n";
}

$config = $editor->getAdminConfig();
if (($config['css_url'] ?? '') !== '/project/styles.css') {
    echo "FAIL: getAdminConfig includes css_url\n";
    $failures++;
} else {
    echo "PASS: getAdminConfig includes css_url\n";
}

if (ContentEditor::DEFAULT_PREVIEW_CSS !== '/fieldtypes/ContentEditor/preview/demo-preview.css') {
    echo "FAIL: DEFAULT_PREVIEW_CSS constant\n";
    $failures++;
} else {
    echo "PASS: DEFAULT_PREVIEW_CSS constant\n";
}

$previewJs = __DIR__ . '/../core/preview-css.js';
$previewJsContent = is_file($previewJs) ? file_get_contents($previewJs) : '';
if ($previewJsContent === '' || strpos($previewJsContent, 'resolveFetchUrl') === false) {
    echo "FAIL: preview-css.js module\n";
    $failures++;
} else {
    echo "PASS: preview-css.js module\n";
}

if ($failures > 0) {
    echo "\nRESULT: FAIL ($failures)\n";
    exit(1);
}

echo "\nRESULT: PASS\n";
exit(0);
