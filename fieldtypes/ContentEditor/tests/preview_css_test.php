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
if (!is_file($previewJs) || strpos(file_get_contents($previewJs), 'ContentEditorPreview') === false) {
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
