<?php
/**
 * P5.6-M0 — Content groups DnD hardening (ungrouped id + empty drop zones).
 */

$failures = 0;

$files = [
    'mouse-drag-drop.js' => __DIR__ . '/../admin/js/mouse-drag-drop.js',
    'content-groups.js' => __DIR__ . '/../admin/js/content-groups.js',
    'content.html' => __DIR__ . '/../admin/templates/content.html',
    'header.css' => __DIR__ . '/../admin/css/layout/header.css',
    'content.css' => __DIR__ . '/../admin/css/pages/content.css',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        echo "FAIL: $label missing\n";
        $failures++;
    }
}

$nested = file_get_contents($files['mouse-drag-drop.js']);
$nestedChecks = [
    'MouseDragDropManager' => 'MouseDragDropManager class',
    'onCrossContainerMove' => 'cross-container collection moves',
    ':not(.ungrouped)' => 'ungrouped excluded from group reorder',
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

if (strpos($groups, 'MouseDragDropManager') === false) {
    echo "FAIL: content-groups uses MouseDragDropManager\n";
    $failures++;
} else {
    echo "PASS: content-groups uses MouseDragDropManager\n";
}

if (strpos($nested, 'handleSelector: null') !== false && strpos($nested, 'useCapture: true') !== false) {
    echo "PASS: collection drag uses whole-card capture-phase mouse drag\n";
} else {
    echo "FAIL: collection drag whole-card capture setup\n";
    $failures++;
}

if (strpos($groups, "card.addEventListener('click'") === false) {
    echo "PASS: collection cards do not use whole-card click navigation\n";
} else {
    echo "FAIL: whole-card click handler conflicts with drag\n";
    $failures++;
}

$contentHtml = file_get_contents($files['content.html']);
if (strpos($contentHtml, 'mouse-drag-drop.js') === false) {
    echo "FAIL: content page loads mouse-drag-drop.js\n";
    $failures++;
} else {
    echo "PASS: content page loads mouse-drag-drop.js\n";
}

$cardsCss = file_get_contents(__DIR__ . '/../admin/css/components/cards.css');
if (strpos($cardsCss, '.collection-card .drag-handle') !== false && strpos($cardsCss, 'pointer-events: auto') !== false) {
    echo "PASS: collection drag handle pointer-events for mouse drag\n";
} else {
    echo "FAIL: collection drag handle pointer-events\n";
    $failures++;
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
