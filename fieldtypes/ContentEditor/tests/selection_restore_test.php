<?php
/**
 * Hotfix — editor-relative selection paths and layout inspector default hidden.
 */

$failures = 0;

$selectionPath = __DIR__ . '/../selection.js';
$inspectorPath = __DIR__ . '/../layout/layout-inspector.js';
$cssPath = __DIR__ . '/../editor.css';
$eventManagerPath = __DIR__ . '/../core/event-manager.js';

foreach ([
    $selectionPath => 'selection.js',
    $inspectorPath => 'layout-inspector.js',
    $cssPath => 'editor.css',
    $eventManagerPath => 'event-manager.js',
] as $file => $label) {
    if (!is_file($file)) {
        echo "FAIL: $label missing\n";
        $failures++;
    }
}

$selection = file_get_contents($selectionPath);
$checks = [
    'findEditorRoot' => 'findEditorRoot helper',
    'getNodePath(node, root' => 'editor-relative getNodePath',
    'savedEditorRoot' => 'savedEditorRoot tracking',
    'restoreFromSnapshot' => 'restoreFromSnapshot helper',
    'cloneSavedData' => 'cloneSavedData helper',
];

foreach ($checks as $needle => $label) {
    if (strpos($selection, $needle) === false) {
        echo "FAIL: $label\n";
        $failures++;
    } else {
        echo "PASS: $label\n";
    }
}

$inspector = file_get_contents($inspectorPath);
if (strpos($inspector, 'panel.hidden = true') === false) {
    echo "FAIL: layout inspector hidden by default\n";
    $failures++;
} else {
    echo "PASS: layout inspector hidden by default\n";
}

$css = file_get_contents($cssPath);
if (strpos($css, '.content-editor-preview-badge[hidden]') === false) {
    echo "FAIL: preview badge hidden CSS rule\n";
    $failures++;
} else {
    echo "PASS: preview badge hidden CSS rule\n";
}

$eventManager = file_get_contents($eventManagerPath);
$eventChecks = [
    '_contentEditorToolbarMousedownHandlerAttached' => 'toolbar mousedown capture',
    'saveSelectionToDropdown' => 'saveSelectionToDropdown helper',
    'closeToolbarDropdowns' => 'closeToolbarDropdowns helper',
    'menu.style.removeProperty(\'display\')' => 'clear stale dropdown display',
];

foreach ($eventChecks as $needle => $label) {
    if (strpos($eventManager, $needle) === false) {
        echo "FAIL: $label\n";
        $failures++;
    } else {
        echo "PASS: $label\n";
    }
}

$tableCommands = file_get_contents(__DIR__ . '/../commands/layout/table.js');
if (strpos($tableCommands, 'restoreFromSnapshot') === false && strpos($tableCommands, 'selectionSnapshot') === false) {
    echo "FAIL: table insert selection snapshot\n";
    $failures++;
} else {
    echo "PASS: table insert selection snapshot\n";
}

if ($failures > 0) {
    echo "\nRESULT: FAIL ($failures)\n";
    exit(1);
}

echo "\nRESULT: PASS\n";
exit(0);
