<?php
/**
 * P5.6-M0 — Content groups DnD hardening (ungrouped id + empty drop zones).
 */

$failures = 0;

$files = [
    'nested-drag-drop.js' => __DIR__ . '/../admin/js/nested-drag-drop.js',
    'content-groups.js' => __DIR__ . '/../admin/js/content-groups.js',
    'header.css' => __DIR__ . '/../admin/css/layout/header.css',
    'content.css' => __DIR__ . '/../admin/css/pages/content.css',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        echo "FAIL: $label missing\n";
        $failures++;
    }
}

$nested = file_get_contents($files['nested-drag-drop.js']);
$nestedChecks = [
    'normalizeGroupId' => 'normalizeGroupId helper',
    'findGroupElement' => 'findGroupElement helper',
    'expandGroupIfCollapsed' => 'expand collapsed group on drag-over',
    "return 'ungrouped'" => 'ungrouped canonical id',
];

foreach ($nestedChecks as $needle => $label) {
    if (strpos($nested, $needle) === false) {
        echo "FAIL: $label\n";
        $failures++;
    } else {
        echo "PASS: $label\n";
    }
}

$groups = file_get_contents($files['content-groups.js']);
if (strpos($groups, "dataset.groupId = groupId || 'ungrouped'") === false) {
    echo "FAIL: collection cards use ungrouped id\n";
    $failures++;
} else {
    echo "PASS: collection cards use ungrouped id\n";
}

if (strpos($groups, "grid.dataset.groupId = 'ungrouped'") === false) {
    echo "FAIL: ungrouped grid has data-group-id\n";
    $failures++;
} else {
    echo "PASS: ungrouped grid has data-group-id\n";
}

$header = file_get_contents($files['header.css']);
if (strpos($header, 'background: transparent') === false || strpos($header, 'border-bottom') !== false) {
    echo "FAIL: topbar borderless\n";
    $failures++;
} else {
    echo "PASS: topbar borderless\n";
}

if (strpos($header, '.topbar-user-menu .user-avatar-initials') === false) {
    echo "FAIL: topbar avatar initials styling\n";
    $failures++;
} else {
    echo "PASS: topbar avatar initials styling\n";
}

$contentCss = file_get_contents($files['content.css']);
if (strpos($contentCss, '.grid.empty-drop-zone') === false) {
    echo "FAIL: grid empty drop zone styles\n";
    $failures++;
} else {
    echo "PASS: grid empty drop zone styles\n";
}

if ($failures > 0) {
    echo "\nRESULT: FAIL ($failures)\n";
    exit(1);
}

echo "\nRESULT: PASS\n";
exit(0);
