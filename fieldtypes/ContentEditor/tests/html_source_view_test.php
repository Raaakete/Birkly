<?php
/**
 * HTML source view — height sync and pretty-print formatting.
 */

$failures = 0;

$syncPath = __DIR__ . '/../core/sync-manager.js';
$cssPath = __DIR__ . '/../editor.css';

if (!is_file($syncPath)) {
    echo "FAIL: sync-manager.js missing\n";
    exit(1);
}

$sync = file_get_contents($syncPath);
$checks = [
    'formatHtml' => 'formatHtml helper',
    'matchPanelHeight' => 'matchPanelHeight helper',
    "options.format" => 'format option on syncHtmlSourceFromHidden',
    'white-space: pre' => null,
];

foreach ([
    'formatHtml' => 'formatHtml helper',
    'matchPanelHeight' => 'matchPanelHeight helper',
    'options.format' => 'format option on syncHtmlSourceFromHidden',
] as $needle => $label) {
    if (strpos($sync, $needle) === false) {
        echo "FAIL: $label\n";
        $failures++;
    } else {
        echo "PASS: $label\n";
    }
}

$css = file_get_contents($cssPath);
if (strpos($css, 'white-space: pre') === false) {
    echo "FAIL: HTML source uses preformatted whitespace\n";
    $failures++;
} else {
    echo "PASS: HTML source uses preformatted whitespace\n";
}

if (strpos($css, '.content-editor-body') === false || strpos($css, 'min-height: 200px') === false) {
    echo "FAIL: content-editor-body min-height\n";
    $failures++;
} else {
    echo "PASS: content-editor-body min-height\n";
}

if ($failures > 0) {
    echo "\nRESULT: FAIL ($failures)\n";
    exit(1);
}

echo "\nRESULT: PASS\n";
exit(0);
