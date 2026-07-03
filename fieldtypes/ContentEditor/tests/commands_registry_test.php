<?php
/**
 * P2-M3-T7 — ContentEditor modular command registry.
 */

$failures = 0;

$commandFiles = [
    __DIR__ . '/../commands/base-command.js',
    __DIR__ . '/../commands/inline-format.js',
    __DIR__ . '/../commands/block-format.js',
    __DIR__ . '/../commands/registry.js',
    __DIR__ . '/../commands/bootstrap.js',
    __DIR__ . '/../commands/layout/table.js',
    __DIR__ . '/../commands/layout/grid.js',
];

foreach ($commandFiles as $file) {
    $basename = basename($file);
    if (!is_file($file)) {
        echo "FAIL: missing $basename\n";
        $failures++;
    } else {
        echo "PASS: $basename exists\n";
    }
}

$inline = file_get_contents(__DIR__ . '/../commands/inline-format.js');
if (strpos($inline, 'ContentEditorInlineFormat') === false || strpos($inline, 'wrapWithTag') === false) {
    echo "FAIL: inline-format.js exports wrapWithTag\n";
    $failures++;
} else {
    echo "PASS: inline-format.js exports wrapWithTag\n";
}

$block = file_get_contents(__DIR__ . '/../commands/block-format.js');
if (strpos($block, 'ContentEditorBlockFormat') === false || strpos($block, 'formatBlock') === false) {
    echo "FAIL: block-format.js exports formatBlock\n";
    $failures++;
} else {
    echo "PASS: block-format.js exports formatBlock\n";
}

$bootstrap = file_get_contents(__DIR__ . '/../commands/bootstrap.js');
$expected = ['bold', 'italic', 'underline', 'strikethrough', 'formatBlock', 'insertUnorderedList', 'insertOrderedList'];
foreach ($expected as $cmd) {
    if (strpos($bootstrap, "'$cmd'") === false && strpos($bootstrap, "\"$cmd\"") === false) {
        echo "FAIL: bootstrap registers $cmd\n";
        $failures++;
    }
}
if ($failures === 0) {
    echo "PASS: bootstrap registers Phase 2 commands\n";
}

$registry = file_get_contents(__DIR__ . '/../commands/registry.js');
if (strpos($registry, 'modularCommands') === false || strpos($registry, 'ContentEditorSelection.save') === false) {
    echo "FAIL: registry wraps selection/history\n";
    $failures++;
} else {
    echo "PASS: registry wraps selection/history\n";
}

require_once __DIR__ . '/../../../fieldtypes/BaseField.php';
require_once __DIR__ . '/../ContentEditor.php';

use Birkly\FieldTypes\ContentEditor\ContentEditor;

$editor = new ContentEditor(['name' => 'content', 'label' => 'Content']);
$js = $editor->getJavaScript();

foreach (['ContentEditorInlineFormat', 'ContentEditorBlockFormat', 'ContentEditorBaseCommand', 'ContentEditorCommandRegistry', 'ContentEditorTableCommands', 'ContentEditorGridCommands'] as $symbol) {
    if (strpos($js, $symbol) === false) {
        echo "FAIL: bundled JS includes $symbol\n";
        $failures++;
    } else {
        echo "PASS: bundled JS includes $symbol\n";
    }
}

// inline-format must load before block-format (wrapWithTag may call formatBlock)
$inlinePos = strpos($js, 'ContentEditorInlineFormat');
$blockPos = strpos($js, 'ContentEditorBlockFormat');
if ($inlinePos === false || $blockPos === false || $inlinePos > $blockPos) {
    echo "FAIL: inline-format loads before block-format in bundle\n";
    $failures++;
} else {
    echo "PASS: inline-format loads before block-format in bundle\n";
}

if ($failures > 0) {
    echo "\nRESULT: FAIL ($failures)\n";
    exit(1);
}

echo "\nRESULT: PASS\n";
exit(0);
