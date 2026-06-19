<?php
/**
 * P2-M3-T6 — ContentEditor component registry and bundled components.
 */

require_once __DIR__ . '/../components/ComponentRegistry.php';

use Birkly\FieldTypes\ContentEditor\ComponentRegistry;

$failures = 0;
$registry = new ComponentRegistry();

$bundledDir = $registry->getBundledDir();
if (!is_dir($bundledDir)) {
    echo "FAIL: bundled components directory missing\n";
    $failures++;
} else {
    echo "PASS: bundled components directory exists\n";
}

$expectedSlugs = ['callout', 'quote', 'cta-button'];
foreach ($expectedSlugs as $slug) {
    $path = $bundledDir . '/' . $slug . '/component.json';
    if (!is_file($path)) {
        echo "FAIL: bundled component missing: $slug\n";
        $failures++;
    } else {
        echo "PASS: bundled component exists: $slug\n";
    }
}

$list = $registry->listAll();
if (count($list) < 3) {
    echo "FAIL: listAll returns at least 3 components\n";
    $failures++;
} else {
    echo "PASS: listAll returns " . count($list) . " components\n";
}

$slugs = array_column($list, 'slug');
foreach ($expectedSlugs as $slug) {
    if (!in_array($slug, $slugs, true)) {
        echo "FAIL: listAll includes $slug\n";
        $failures++;
    }
}
if ($failures === 0) {
    echo "PASS: listAll includes all bundled slugs\n";
}

$callout = $registry->get('callout');
if (!$callout || empty($callout['template']) || empty($callout['style'])) {
    echo "FAIL: get(callout) returns template and style\n";
    $failures++;
} else {
    echo "PASS: get(callout) returns template and style\n";
}

if (!$registry->isBundled('callout')) {
    echo "FAIL: isBundled(callout)\n";
    $failures++;
} else {
    echo "PASS: isBundled(callout)\n";
}

$deleteBundled = $registry->delete('callout');
if (($deleteBundled['success'] ?? false) === true) {
    echo "FAIL: delete rejects bundled component\n";
    $failures++;
} else {
    echo "PASS: delete rejects bundled component\n";
}

$registryJs = __DIR__ . '/../core/component-registry.js';
if (!is_file($registryJs) || strpos(file_get_contents($registryJs), 'ContentEditorComponentRegistry') === false) {
    echo "FAIL: component-registry.js module\n";
    $failures++;
} else {
    echo "PASS: component-registry.js module\n";
}

if ($failures > 0) {
    echo "\nRESULT: FAIL ($failures)\n";
    exit(1);
}

echo "\nRESULT: PASS\n";
exit(0);
