<?php
/**
 * P2-M3-T8 — ContentEditor internal README exists and documents key modules.
 */

$readme = __DIR__ . '/../README.md';
$failures = 0;

if (!is_file($readme)) {
    echo "FAIL: README.md missing\n";
    exit(1);
}

$content = file_get_contents($readme);
$required = [
    'ContentEditor.php',
    'registry.js',
    'ComponentRegistry.php',
    'preview-css.js',
    'sync-manager.js',
    'html_roundtrip_test.php',
    'pure HTML',
];

foreach ($required as $needle) {
    if (stripos($content, $needle) === false) {
        echo "FAIL: README missing section for $needle\n";
        $failures++;
    } else {
        echo "PASS: README documents $needle\n";
    }
}

if ($failures > 0) {
    echo "\nRESULT: FAIL ($failures)\n";
    exit(1);
}

echo "\nRESULT: PASS\n";
exit(0);
