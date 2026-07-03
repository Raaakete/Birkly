<?php
/**
 * P5.5-M2 — Table visual studio: colgroup schema, inspector, row handles, caret insert.
 */

require_once __DIR__ . '/../../../fieldtypes/BaseField.php';
require_once __DIR__ . '/../ContentEditor.php';

use Birkly\FieldTypes\ContentEditor\ContentEditor;

$failures = 0;

$tableLayoutPath = __DIR__ . '/../layout/table-layout.js';
if (!is_file($tableLayoutPath)) {
    echo "FAIL: missing layout/table-layout.js\n";
    exit(1);
}
echo "PASS: layout/table-layout.js exists\n";

$tableJs = file_get_contents($tableLayoutPath);
$tableChecks = [
    'ContentEditorTableLayout' => 'table layout global',
    'ensureColgroup' => 'ensureColgroup export',
    'headerRow' => 'header row toggle',
    'striped' => 'stripe toggle',
    'borderCollapse' => 'border-collapse control',
    'startRowDrag' => 'row min-height drag',
    'verticalAlign' => 'cell vertical align',
];

foreach ($tableChecks as $needle => $label) {
    if (strpos($tableJs, $needle) === false) {
        echo "FAIL: $label\n";
        $failures++;
    } else {
        echo "PASS: $label\n";
    }
}

$tableCommandsPath = __DIR__ . '/../commands/layout/table.js';
$tableCommands = file_get_contents($tableCommandsPath);

if (strpos($tableCommands, "const colgroup = document.createElement('colgroup')") === false) {
    echo "FAIL: createTableElement adds colgroup\n";
    $failures++;
} else {
    echo "PASS: createTableElement adds colgroup\n";
}

if (!preg_match('/createTableElement\s*\([^)]*\)\s*\{[\s\S]*?colgroup[\s\S]*?insertNodesAtCaret/s', $tableCommands)) {
    echo "FAIL: createTableElement colgroup + caret insert\n";
    $failures++;
} else {
    echo "PASS: createTableElement colgroup + caret insert\n";
}

$editor = new ContentEditor([
    'name' => 'content',
    'label' => 'Content',
    'value' => '',
    'required' => false,
]);

$bundled = $editor->getJavaScript();
if (strpos($bundled, 'ContentEditorTableLayout') === false) {
    echo "FAIL: table layout bundled in getJavaScript\n";
    $failures++;
} else {
    echo "PASS: table layout bundled in getJavaScript\n";
}

$tableOrder = strpos($bundled, 'ContentEditorLayout');
$tableAfter = strpos($bundled, 'ContentEditorTableLayout');
if ($tableOrder === false || $tableAfter === false || $tableAfter < $tableOrder) {
    echo "FAIL: table-layout loads after layout-manager\n";
    $failures++;
} else {
    echo "PASS: table-layout loads after layout-manager\n";
}

$tableHtml = '<table class="content-editor-table" data-ce-selected="true" style="width:80%;border-collapse:collapse">'
    . '<colgroup><col style="width:33%"><col style="width:33%"><col style="width:34%"></colgroup>'
    . '<tbody>'
    . '<tr><th scope="col">H</th><th scope="col">H</th><th scope="col">H</th></tr>'
    . '<tr style="background-color:#f9fafb;min-height:48px"><td style="padding:12px;vertical-align:middle">A</td>'
    . '<td>B</td><td>C</td></tr>'
    . '</tbody></table>'
    . '<div class="content-editor-table-row-handles content-editor-layout-handles" data-ce-handles="true"><div></div></div>';

$processed = $editor->processValue($tableHtml);

if (stripos($processed, 'data-ce-') !== false) {
    echo "FAIL: processValue strips table editor metadata\n";
    $failures++;
} else {
    echo "PASS: processValue strips table editor metadata\n";
}

if (stripos($processed, 'content-editor-table-row-handles') !== false) {
    echo "FAIL: processValue strips row resize handles\n";
    $failures++;
} else {
    echo "PASS: processValue strips row resize handles\n";
}

$preserveChecks = [
    'width:80%' => 'table width preserved',
    'border-collapse:collapse' => 'border-collapse preserved',
    'width:33%' => 'colgroup widths preserved',
    '<th' => 'header cells preserved',
    'background-color:#f9fafb' => 'stripe style preserved',
    'vertical-align:middle' => 'cell vertical-align preserved',
];

foreach ($preserveChecks as $needle => $label) {
    if (stripos($processed, $needle) === false) {
        echo "FAIL: $label\n";
        $failures++;
    } else {
        echo "PASS: $label\n";
    }
}

$layoutManager = file_get_contents(__DIR__ . '/../layout/layout-manager.js');
if (strpos($layoutManager, 'ContentEditorTableLayout.bindEditor') === false) {
    echo "FAIL: layout-manager wires table layout\n";
    $failures++;
} else {
    echo "PASS: layout-manager wires table layout\n";
}

if ($failures > 0) {
    echo "\nRESULT: FAIL ($failures)\n";
    exit(1);
}

echo "\nRESULT: PASS\n";
exit(0);
