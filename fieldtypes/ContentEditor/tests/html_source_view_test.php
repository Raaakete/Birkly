<?php
/**
 * HTML source view — syntax highlighting, line numbers, and panel layout.
 */

$failures = 0;

$files = [
    'sync-manager.js' => __DIR__ . '/../core/sync-manager.js',
    'html-source-editor.js' => __DIR__ . '/../core/html-source-editor.js',
    'editor.css' => __DIR__ . '/../editor.css',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        echo "FAIL: $label missing\n";
        $failures++;
    }
}

$htmlSourceJs = file_get_contents($files['html-source-editor.js']);
$checks = [
    'ContentEditorHtmlSource' => 'HtmlSource module',
    'highlightHtml' => 'highlightHtml helper',
    'updateGutter' => 'line number gutter',
    'ce-html-tag' => 'tag token class',
    'content-editor-html-line-num' => 'line number element',
];

foreach ($checks as $needle => $label) {
    if (strpos($htmlSourceJs, $needle) === false) {
        echo "FAIL: $label\n";
        $failures++;
    } else {
        echo "PASS: $label\n";
    }
}

$css = file_get_contents($files['editor.css']);
$cssChecks = [
    '.content-editor-html-panel' => 'html panel layout',
    '.content-editor-html-gutter' => 'line number gutter styles',
    '.ce-html-tag' => 'tag syntax color',
    '.ce-html-attr' => 'attribute syntax color',
    '.ce-html-value' => 'value syntax color',
    'color: transparent !important' => 'textarea transparent text for overlay',
    '.content-editor-html-highlight .ce-html-tag' => 'scoped syntax token colors',
];

foreach ($cssChecks as $needle => $label) {
    if (strpos($css, $needle) === false) {
        echo "FAIL: $label\n";
        $failures++;
    } else {
        echo "PASS: $label\n";
    }
}

require_once __DIR__ . '/../../../fieldtypes/BaseField.php';
require_once __DIR__ . '/../ContentEditor.php';

use Birkly\FieldTypes\ContentEditor\ContentEditor;

$editor = new ContentEditor(['name' => 'content', 'label' => 'Content']);
$html = $editor->render();

if (strpos($html, 'content-editor-html-panel') === false) {
    echo "FAIL: html panel in render output\n";
    $failures++;
} else {
    echo "PASS: html panel in render output\n";
}

if (strpos($html, 'content-editor-html-gutter') === false) {
    echo "FAIL: html gutter in render output\n";
    $failures++;
} else {
    echo "PASS: html gutter in render output\n";
}

$bundled = $editor->getJavaScript();
if (strpos($bundled, 'ContentEditorHtmlSource') === false) {
    echo "FAIL: HtmlSource bundled in getJavaScript\n";
    $failures++;
} else {
    echo "PASS: HtmlSource bundled in getJavaScript\n";
}

if ($failures > 0) {
    echo "\nRESULT: FAIL ($failures)\n";
    exit(1);
}

echo "\nRESULT: PASS\n";
exit(0);
