<?php
/**
 * P5.5-M3 — Grid visual studio module, caret insert, and save contract (D2: no @media).
 */

require_once __DIR__ . '/../../../fieldtypes/BaseField.php';
require_once __DIR__ . '/../ContentEditor.php';

use Birkly\FieldTypes\ContentEditor\ContentEditor;

$failures = 0;

$gridLayoutPath = __DIR__ . '/../layout/grid-layout.js';
if (!is_file($gridLayoutPath)) {
    echo "FAIL: missing layout/grid-layout.js\n";
    exit(1);
}
echo "PASS: layout/grid-layout.js exists\n";

$gridJs = file_get_contents($gridLayoutPath);
$moduleChecks = [
    'ContentEditorGridLayout' => 'grid layout global',
    'TRACK_PRESETS' => 'track presets',
    'auto-fit' => 'auto-fit fluid preset',
    'gridAutoFlow' => 'auto-flow control',
    'colSpan' => 'column span UI',
    'rowSpan' => 'row span UI',
    'gapMode' => 'uniform / split gap mode',
];

foreach ($moduleChecks as $needle => $label) {
    if (strpos($gridJs, $needle) === false) {
        echo "FAIL: $label\n";
        $failures++;
    } else {
        echo "PASS: $label\n";
    }
}

if (stripos($gridJs, '@media') !== false) {
    echo "FAIL: grid-layout.js must not emit @media in saved HTML helpers\n";
    $failures++;
} else {
    echo "PASS: grid-layout.js has no @media\n";
}

$gridCommandsPath = __DIR__ . '/../commands/layout/grid.js';
$gridCommands = file_get_contents($gridCommandsPath);
if (!preg_match('/createGridElement\s*\([^)]*\)\s*\{[\s\S]*?insertNodesAtCaret/s', $gridCommands)) {
    echo "FAIL: createGridElement uses insertNodesAtCaret\n";
    $failures++;
} else {
    echo "PASS: createGridElement uses insertNodesAtCaret\n";
}

$editor = new ContentEditor([
    'name' => 'content',
    'label' => 'Content',
    'value' => '',
    'required' => false,
]);

$bundled = $editor->getJavaScript();
if (strpos($bundled, 'ContentEditorGridLayout') === false) {
    echo "FAIL: grid layout bundled in getJavaScript()\n";
    $failures++;
} else {
    echo "PASS: grid layout bundled in getJavaScript()\n";
}

$gridHtml = '<div class="content-editor-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));grid-auto-flow:row dense;gap:20px">'
    . '<div class="content-editor-grid-item" style="grid-column:span 2;background:#f3f4f6;padding:12px">Cell</div>'
    . '<div class="content-editor-grid-item">B</div></div>';

$processed = $editor->processValue($gridHtml);

if (stripos($processed, '@media') !== false) {
    echo "FAIL: processValue grid HTML must not contain @media\n";
    $failures++;
} else {
    echo "PASS: saved grid HTML has no @media\n";
}

$preserveChecks = [
    'repeat(auto-fit,minmax(200px,1fr))' => 'auto-fit track preset',
    'grid-auto-flow:row dense' => 'dense flow mode',
    'gap:20px' => 'gap style',
    'grid-column:span 2' => 'column span',
    'background:#f3f4f6' => 'cell background',
];

foreach ($preserveChecks as $needle => $label) {
    if (stripos(str_replace(' ', '', $processed), str_replace(' ', '', $needle)) === false) {
        echo "FAIL: processValue preserves $label\n";
        $failures++;
    } else {
        echo "PASS: processValue preserves $label\n";
    }
}

$splitGapHtml = '<div class="content-editor-grid" style="display:grid;grid-template-columns:1fr 1fr;row-gap:8px;column-gap:24px">'
    . '<div>A</div><div>B</div></div>';
$splitProcessed = $editor->processValue($splitGapHtml);
if (stripos($splitProcessed, 'row-gap:8px') === false || stripos($splitProcessed, 'column-gap:24px') === false) {
    echo "FAIL: processValue preserves split row/column gap\n";
    $failures++;
} else {
    echo "PASS: processValue preserves split row/column gap\n";
}

if ($failures > 0) {
    echo "\nRESULT: FAIL ($failures)\n";
    exit(1);
}

echo "\nRESULT: PASS\n";
exit(0);
