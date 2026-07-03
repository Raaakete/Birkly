<?php
/**
 * P2-M3-T4 / P3-M12 — ContentEditor HTML storage, mode tabs, and block round-trip.
 */

require_once __DIR__ . '/../../../fieldtypes/BaseField.php';
require_once __DIR__ . '/../ContentEditor.php';

use Birkly\FieldTypes\ContentEditor\ContentEditor;

$failures = 0;

$editor = new ContentEditor([
    'name' => 'content',
    'label' => 'Content',
    'value' => '<p>Seed</p>',
    'required' => false,
]);

$html = $editor->render();
$checks = [
    'content-editor-mode-tab' => 'mode tabs in render',
    'content-editor-html-source' => 'HTML source panel in render',
    'content-editor-html-panel' => 'HTML source panel wrapper',
    'content-editor-html-gutter' => 'HTML line number gutter',
    'content-editor-area' => 'visual editor area in render',
];

foreach ($checks as $needle => $label) {
    if (strpos($html, $needle) === false) {
        echo "FAIL: $label\n";
        $failures++;
    } else {
        echo "PASS: $label\n";
    }
}

// Visual area must start empty; stored HTML lives in the hidden input only
if (!preg_match('/class="content-editor-area"[^>]*>\s*<\/div>/', $html)) {
    echo "FAIL: content-editor-area should not contain inline HTML markup\n";
    $failures++;
} else {
    echo "PASS: content-editor-area starts empty\n";
}

if (strpos($html, 'value="&lt;p&gt;Seed&lt;/p&gt;"') === false && strpos($html, 'value="<p>Seed</p>"') === false) {
    echo "FAIL: hidden input should carry stored HTML value\n";
    $failures++;
} else {
    echo "PASS: hidden input carries stored HTML value\n";
}

$commandsJs = file_get_contents(__DIR__ . '/../commands.js');
if (@shell_exec('node --check ' . escapeshellarg(__DIR__ . '/../commands.js') . ' 2>&1')) {
    echo "FAIL: commands.js has JavaScript syntax errors\n";
    $failures++;
} else {
    echo "PASS: commands.js parses cleanly\n";
}

$input = '<p>Hello <strong>world</strong></p>';
$processed = $editor->processValue($input);
if (strpos($processed, 'Hello') === false || strpos($processed, 'world') === false) {
    echo "FAIL: processValue preserves HTML content\n";
    $failures++;
} else {
    echo "PASS: processValue preserves HTML content\n";
}

$roundtrip = $editor->processValue($processed);
if (trim($roundtrip) === '') {
    echo "FAIL: HTML round-trip empty\n";
    $failures++;
} else {
    echo "PASS: HTML round-trip non-empty\n";
}

// P3-M12 — block system modules
$blockRegistryPath = __DIR__ . '/../blocks/block-registry.js';
$blockManagerPath = __DIR__ . '/../blocks/block-manager.js';

if (!is_file($blockRegistryPath) || strpos(file_get_contents($blockRegistryPath), 'ContentEditorBlockRegistry') === false) {
    echo "FAIL: blocks/block-registry.js module\n";
    $failures++;
} else {
    echo "PASS: blocks/block-registry.js module\n";
}

if (!is_file($blockManagerPath) || strpos(file_get_contents($blockManagerPath), 'ContentEditorBlockManager') === false) {
    echo "FAIL: blocks/block-manager.js module\n";
    $failures++;
} else {
    echo "PASS: blocks/block-manager.js module\n";
}

$bundledJs = $editor->getJavaScript();
$jsChecks = [
    'ContentEditorBlockRegistry' => 'block registry bundled in getJavaScript',
    'ContentEditorBlockManager' => 'block manager bundled in getJavaScript',
    "type: 'paragraph'" => 'paragraph block registered',
    "type: 'heading'" => 'heading block registered',
    "type: 'divider'" => 'divider block registered',
    "type: 'table'" => 'table block registered',
];

foreach ($jsChecks as $needle => $label) {
    if (strpos($bundledJs, $needle) === false) {
        echo "FAIL: $label\n";
        $failures++;
    } else {
        echo "PASS: $label\n";
    }
}

// Block insert HTML round-trip (divider + table)
$dividerHtml = '<p>Before</p><hr class="content-editor-divider"><p>After</p>';
$dividerProcessed = $editor->processValue($dividerHtml);
if (stripos($dividerProcessed, '<hr') === false || stripos($dividerProcessed, 'Before') === false) {
    echo "FAIL: processValue preserves divider block HTML\n";
    $failures++;
} else {
    echo "PASS: processValue preserves divider block HTML\n";
}

$tableHtml = '<p>Intro</p><p><table class="content-editor-table" data-editable-table="true"><tbody><tr><td>Cell</td></tr></tbody></table></p>';
$tableProcessed = $editor->processValue($tableHtml);
if (stripos($tableProcessed, '<table') === false || stripos($tableProcessed, 'Cell') === false) {
    echo "FAIL: processValue preserves table block HTML\n";
    $failures++;
} else {
    echo "PASS: processValue preserves table block HTML\n";
}

$headingListHtml = '<h2>Title</h2><ul><li>One</li><li>Two</li></ul>';
$headingListProcessed = $editor->processValue($headingListHtml);
if (stripos($headingListProcessed, '<h2') === false || stripos($headingListProcessed, '<ul') === false) {
    echo "FAIL: processValue preserves heading/list block HTML\n";
    $failures++;
} else {
    echo "PASS: processValue preserves heading/list block HTML\n";
}

if ($failures > 0) {
    echo "\nRESULT: FAIL ($failures)\n";
    exit(1);
}

echo "\nRESULT: PASS\n";
exit(0);
