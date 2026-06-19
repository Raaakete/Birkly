<?php
/**
 * P2-M3-T4 — ContentEditor HTML storage and mode tab markup.
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

if ($failures > 0) {
    echo "\nRESULT: FAIL ($failures)\n";
    exit(1);
}

echo "\nRESULT: PASS\n";
exit(0);
