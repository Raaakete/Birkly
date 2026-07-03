<?php
/**
 * P5.5-M1 — Layout studio modules, save sanitization, and HTML round-trip.
 */

require_once __DIR__ . '/../../../fieldtypes/BaseField.php';
require_once __DIR__ . '/../ContentEditor.php';

use Birkly\FieldTypes\ContentEditor\ContentEditor;

$failures = 0;

$layoutFiles = [
    'layout-block.js' => __DIR__ . '/../layout/layout-block.js',
    'layout-inspector.js' => __DIR__ . '/../layout/layout-inspector.js',
    'resize-controller.js' => __DIR__ . '/../layout/resize-controller.js',
    'layout-manager.js' => __DIR__ . '/../layout/layout-manager.js',
];

foreach ($layoutFiles as $name => $path) {
    if (!is_file($path)) {
        echo "FAIL: missing layout/$name\n";
        $failures++;
        continue;
    }
    echo "PASS: layout/$name exists\n";
}

$blockJs = file_get_contents($layoutFiles['layout-block.js']);
if (strpos($blockJs, 'ContentEditorLayoutBlock') === false || strpos($blockJs, 'stripEditorMetadata') === false) {
    echo "FAIL: layout-block exports stripEditorMetadata\n";
    $failures++;
} else {
    echo "PASS: layout-block exports stripEditorMetadata\n";
}

$inspectorJs = file_get_contents($layoutFiles['layout-inspector.js']);
if (strpos($inspectorJs, 'ContentEditorLayoutInspector') === false) {
    echo "FAIL: layout-inspector module\n";
    $failures++;
} else {
    echo "PASS: layout-inspector module\n";
}

$resizeJs = file_get_contents($layoutFiles['resize-controller.js']);
if (strpos($resizeJs, 'ContentEditorResizeController') === false) {
    echo "FAIL: resize-controller module\n";
    $failures++;
} else {
    echo "PASS: resize-controller module\n";
}

$editor = new ContentEditor([
    'name' => 'content',
    'label' => 'Content',
    'value' => '',
    'required' => false,
]);

$bundled = $editor->getJavaScript();
$bundleChecks = [
    'ContentEditorLayoutBlock' => 'layout block bundled',
    'ContentEditorLayoutInspector' => 'layout inspector bundled',
    'ContentEditorResizeController' => 'resize controller bundled',
    'ContentEditorLayout' => 'layout manager bundled',
];

foreach ($bundleChecks as $needle => $label) {
    if (strpos($bundled, $needle) === false) {
        echo "FAIL: $label\n";
        $failures++;
    } else {
        echo "PASS: $label\n";
    }
}

$tableHtml = '<table class="content-editor-table" data-ce-selected="true" data-editable-table="true" style="width:100%">'
    . '<colgroup><col style="width:50%"></colgroup><tbody><tr>'
    . '<td data-ce-selected="true" style="padding:12px;background:#f3f4f6">Cell</td></tr></tbody></table>';
$tableProcessed = $editor->processValue($tableHtml);

if (stripos($tableProcessed, 'data-ce-') !== false || stripos($tableProcessed, 'data-editable-table') !== false) {
    echo "FAIL: processValue strips table editor metadata\n";
    $failures++;
} else {
    echo "PASS: processValue strips table editor metadata\n";
}

if (stripos($tableProcessed, 'padding:12px') === false || stripos($tableProcessed, 'Cell') === false) {
    echo "FAIL: processValue preserves table layout styles\n";
    $failures++;
} else {
    echo "PASS: processValue preserves table layout styles\n";
}

$gridHtml = '<div class="content-editor-grid" data-ce-layout-id="x1" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">'
    . '<div class="content-editor-grid-item" style="padding:8px">A</div></div>';
$gridProcessed = $editor->processValue($gridHtml);

if (stripos($gridProcessed, 'data-ce-') !== false) {
    echo "FAIL: processValue strips grid editor metadata\n";
    $failures++;
} else {
    echo "PASS: processValue strips grid editor metadata\n";
}

if (stripos($gridProcessed, 'display:grid') === false || stripos($gridProcessed, 'gap:16px') === false) {
    echo "FAIL: processValue preserves grid inline styles\n";
    $failures++;
} else {
    echo "PASS: processValue preserves grid inline styles\n";
}

$syncJs = file_get_contents(__DIR__ . '/../core/sync-manager.js');
if (strpos($syncJs, 'ContentEditorLayout.stripEditorMetadata') === false
    && strpos($syncJs, 'ContentEditorLayoutBlock.stripEditorMetadata') === false) {
    echo "FAIL: sync-manager strips layout metadata on save\n";
    $failures++;
} else {
    echo "PASS: sync-manager strips layout metadata on save\n";
}

if ($failures > 0) {
    echo "\nRESULT: FAIL ($failures)\n";
    exit(1);
}

echo "\nRESULT: PASS\n";
exit(0);
