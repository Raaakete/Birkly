<?php
/**
 * P8-M5 — Table cell merge/split with native colspan/rowspan.
 * Verifies merge/split logic, inspector buttons, JS bundling, and that saved
 * HTML preserves colspan/rowspan while stripping editor-only selection metadata.
 */

require_once __DIR__ . '/../../../fieldtypes/BaseField.php';
require_once __DIR__ . '/../ContentEditor.php';

use Birkly\FieldTypes\ContentEditor\ContentEditor;

$failures = 0;

function ce_check($condition, $label) {
    global $failures;
    if ($condition) {
        echo "PASS: $label\n";
    } else {
        echo "FAIL: $label\n";
        $failures++;
    }
}

// --- 1. Merge/split logic lives in table-layout.js ---------------------------
$tableLayoutPath = __DIR__ . '/../layout/table-layout.js';
ce_check(is_file($tableLayoutPath), 'layout/table-layout.js exists');
$tableJs = is_file($tableLayoutPath) ? file_get_contents($tableLayoutPath) : '';

$logicChecks = [
    'buildTableGrid' => 'grid model builder',
    'computeSelectionCells' => 'rectangular selection resolver',
    'mergeSelectedCells' => 'merge command',
    'splitSelectedCell' => 'split command entry',
    'splitCell' => 'split implementation',
    'onCellPointerDown' => 'pointer-drag multi-cell selection',
    'refreshMergeControls' => 'inspector button state sync',
    'setMergeSelection' => 'selection highlight setter',
    'clearMergeSelection' => 'selection highlight clear',
    'CELL_SELECTED_CLASS' => 'selection class constant',
    'target.colSpan' => 'writes native colspan',
    'target.rowSpan' => 'writes native rowspan',
    'canMerge' => 'merge validity check',
    'canSplit' => 'split validity check',
];
foreach ($logicChecks as $needle => $label) {
    ce_check(strpos($tableJs, $needle) !== false, $label);
}

// Selection must be confined to a single table (no nested tables / grid-in-grid).
ce_check(strpos($tableJs, "closest('table.content-editor-table')") !== false,
    'merge scoped to content-editor-table');

// --- 2. Inspector Merge/Split buttons ---------------------------------------
$inspectorPath = __DIR__ . '/../layout/layout-inspector.js';
ce_check(is_file($inspectorPath), 'layout/layout-inspector.js exists');
$inspectorJs = is_file($inspectorPath) ? file_get_contents($inspectorPath) : '';

$inspectorChecks = [
    'data-ce-merge-controls' => 'merge controls container',
    'data-ce-table-action="merge"' => 'merge button',
    'data-ce-table-action="split"' => 'split button',
    'mergeSelectedCells' => 'merge button wired to logic',
    'splitSelectedCell' => 'split button wired to logic',
];
foreach ($inspectorChecks as $needle => $label) {
    ce_check(strpos($inspectorJs, $needle) !== false, $label);
}

// --- 3. Bundled into getJavaScript ------------------------------------------
$editor = new ContentEditor([
    'name' => 'content',
    'label' => 'Content',
    'value' => '',
    'required' => false,
]);
$bundled = $editor->getJavaScript();
ce_check(strpos($bundled, 'mergeSelectedCells') !== false, 'merge logic bundled in getJavaScript');
ce_check(strpos($bundled, 'data-ce-table-action="merge"') !== false, 'merge button bundled in getJavaScript');

// --- 4. Save contract: colspan/rowspan kept, selection metadata stripped -----
$mergedHtml = '<table class="content-editor-table" style="width:100%;border-collapse:collapse">'
    . '<colgroup><col style="width:33%"><col style="width:33%"><col style="width:34%"></colgroup>'
    . '<tbody>'
    . '<tr>'
    . '<td colspan="2" rowspan="2" class="content-editor-table-cell-selected" data-ce-cell-selected="true">Merged</td>'
    . '<td>C</td>'
    . '</tr>'
    . '<tr><td>F</td></tr>'
    . '<tr><td>G</td><td>H</td><td>I</td></tr>'
    . '</tbody></table>';

$processed = $editor->processValue($mergedHtml);

ce_check(stripos($processed, 'colspan="2"') !== false, 'processValue preserves colspan');
ce_check(stripos($processed, 'rowspan="2"') !== false, 'processValue preserves rowspan');
ce_check(stripos($processed, 'data-ce-cell-selected') === false, 'processValue strips data-ce-cell-selected');
ce_check(stripos($processed, 'content-editor-table-cell-selected') === false, 'processValue strips selection class');
ce_check(stripos($processed, 'class=""') === false, 'processValue leaves no empty class attribute');
ce_check(stripos($processed, 'content-editor-table') !== false, 'processValue keeps table class');
ce_check(stripos($processed, 'width:33%') !== false, 'processValue keeps colgroup widths');

// A cell without span keeps its content and does not gain a colspan/rowspan.
ce_check(preg_match('/<td[^>]*>C<\/td>/i', $processed) === 1, 'unmerged cells untouched');

// --- 5. Shared JS strip helper also drops the selection class ----------------
$layoutBlock = file_get_contents(__DIR__ . '/../layout/layout-block.js');
ce_check(strpos($layoutBlock, 'content-editor-table-cell-selected') !== false,
    'layout-block strip removes selection class');

if ($failures > 0) {
    echo "\nRESULT: FAIL ($failures)\n";
    exit(1);
}

echo "\nRESULT: PASS\n";
exit(0);
