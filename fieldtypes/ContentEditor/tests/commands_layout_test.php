<?php
/**
 * P5.5-M4 — ContentEditor layout command modules (table/grid extraction).
 */

$failures = 0;

$layoutFiles = [
    'table.js' => __DIR__ . '/../commands/layout/table.js',
    'grid.js' => __DIR__ . '/../commands/layout/grid.js',
];

foreach ($layoutFiles as $basename => $file) {
    if (!is_file($file)) {
        echo "FAIL: missing commands/layout/$basename\n";
        $failures++;
        continue;
    }
    echo "PASS: commands/layout/$basename exists\n";
}

$tableJs = file_get_contents($layoutFiles['table.js']);
$tableExports = [
    'ContentEditorTableCommands' => 'table commands global',
    'insertTable' => 'insertTable export',
    'createTableElement' => 'createTableElement export',
    'createTableDialog' => 'createTableDialog export',
    'initTableControls' => 'initTableControls export',
    'insertNodesAtCaret' => 'caret insert in createTableElement',
    "const colgroup = document.createElement('colgroup')" => 'colgroup schema',
];

foreach ($tableExports as $needle => $label) {
    if (strpos($tableJs, $needle) === false) {
        echo "FAIL: table.js $label\n";
        $failures++;
    } else {
        echo "PASS: table.js $label\n";
    }
}

$gridJs = file_get_contents($layoutFiles['grid.js']);
$gridExports = [
    'ContentEditorGridCommands' => 'grid commands global',
    'insertGrid' => 'insertGrid export',
    'createGridElement' => 'createGridElement export',
    'extractGridData' => 'extractGridData export',
    'enhanceExistingGrids' => 'enhanceExistingGrids export',
    'insertNodesAtCaret' => 'caret insert in createGridElement',
];

foreach ($gridExports as $needle => $label) {
    if (strpos($gridJs, $needle) === false) {
        echo "FAIL: grid.js $label\n";
        $failures++;
    } else {
        echo "PASS: grid.js $label\n";
    }
}

$commandsPath = __DIR__ . '/../commands.js';
$commands = file_get_contents($commandsPath);

$wrapperChecks = [
    'ContentEditorTableCommands.insertTable' => 'commands.js delegates insertTable',
    'ContentEditorTableCommands.createTableElement' => 'commands.js delegates createTableElement',
    'ContentEditorGridCommands.insertGrid' => 'commands.js delegates insertGrid',
    'ContentEditorGridCommands.createGridElement' => 'commands.js delegates createGridElement',
    'ContentEditorGridCommands.enhanceExistingGrids' => 'commands.js delegates enhanceExistingGrids',
];

foreach ($wrapperChecks as $needle => $label) {
    if (strpos($commands, $needle) === false) {
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
$js = $editor->getJavaScript();

$bundleChecks = [
    'ContentEditorTableCommands' => 'table module bundled',
    'ContentEditorGridCommands' => 'grid module bundled',
];

foreach ($bundleChecks as $symbol => $label) {
    if (strpos($js, $symbol) === false) {
        echo "FAIL: bundled JS includes $symbol\n";
        $failures++;
    } else {
        echo "PASS: bundled JS includes $symbol\n";
    }
}

$tablePos = strpos($js, 'ContentEditorTableCommands');
$gridPos = strpos($js, 'ContentEditorGridCommands');
$legacyPos = strpos($js, 'window.ContentEditorCommands =');

if ($tablePos === false || $gridPos === false || $legacyPos === false) {
    echo "FAIL: layout modules or legacy commands missing from bundle\n";
    $failures++;
} elseif ($tablePos > $legacyPos || $gridPos > $legacyPos) {
    echo "FAIL: layout modules must load before commands.js\n";
    $failures++;
} elseif ($gridPos < $tablePos) {
    echo "FAIL: table.js must load before grid.js\n";
    $failures++;
} else {
    echo "PASS: layout modules load before commands.js (table then grid)\n";
}

if ($failures > 0) {
    echo "\nRESULT: FAIL ($failures)\n";
    exit(1);
}

echo "\nRESULT: PASS\n";
exit(0);
