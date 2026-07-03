<?php
/**
 * P5.5-M0-T1 — ContentEditor caret insert API and table insert wiring.
 */

$failures = 0;

$domPath = __DIR__ . '/../core/dom-utils.js';
$commandsPath = __DIR__ . '/../commands.js';

if (!is_file($domPath)) {
    echo "FAIL: core/dom-utils.js missing\n";
    exit(1);
}

$dom = file_get_contents($domPath);
$checks = [
    'ContentEditorDOMUtils' => 'DOM utils global',
    'resolveInsertAnchor' => 'resolveInsertAnchor export',
    'insertNodesAtCaret' => 'insertNodesAtCaret export',
    'data-ce-insert-marker' => 'insert marker for caret fallback',
];

foreach ($checks as $needle => $label) {
    if (strpos($dom, $needle) === false) {
        echo "FAIL: $label\n";
        $failures++;
    } else {
        echo "PASS: $label\n";
    }
}

if (!is_file($commandsPath)) {
    echo "FAIL: commands.js missing\n";
    exit(1);
}

$commands = file_get_contents($commandsPath);

if (!preg_match('/createTableElement\s*\([^)]*\)\s*\{[\s\S]*?insertNodesAtCaret/s', $commands)) {
    echo "FAIL: createTableElement uses insertNodesAtCaret\n";
    $failures++;
} else {
    echo "PASS: createTableElement uses insertNodesAtCaret\n";
}

if (!preg_match('/createGridElement\s*\([^)]*\)\s*\{[\s\S]*?insertNodesAtCaret/s', $commands)) {
    echo "FAIL: createGridElement uses insertNodesAtCaret\n";
    $failures++;
} else {
    echo "PASS: createGridElement uses insertNodesAtCaret\n";
}

if (strpos($commands, 'ContentEditorSelection.restore') === false) {
    echo "FAIL: table insert restores selection before insert\n";
    $failures++;
} else {
    echo "PASS: table insert restores selection before insert\n";
}

if ($failures > 0) {
    echo "\nRESULT: FAIL ($failures)\n";
    exit(1);
}

echo "\nRESULT: PASS\n";
exit(0);
