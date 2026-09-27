#!/usr/bin/env php
<?php
/**
 * Generate the icon registry in admin/js/icon.js and admin/assets/icons/*.svg
 * from core/icon_data.php.
 *
 * Usage: php scripts/build-icons.php [--check]
 *   --check  exit 1 if any generated file is out of date (CI).
 */

$root = dirname(__DIR__);
require_once $root . '/core/icons.php';

$check = in_array('--check', $argv, true);
$data = birkly_icon_data();
$stale = [];

$pixel = [];
foreach ($data['pixel'] as $name => $rows) {
    $pixel[$name] = birkly_icon_grid_path($rows);
}
ksort($pixel);
$stroke = $data['stroke'];
ksort($stroke);
$aliases = $data['aliases'];
ksort($aliases);

$json = static fn (array $a): string => json_encode($a, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$indent = static fn (string $s): string => preg_replace('/^/m', '    ', $s);
$block = "    // <icons:generated> — php scripts/build-icons.php; edit core/icon_data.php\n"
    . '    const ICON_PIXEL = ' . ltrim($indent($json($pixel))) . ";\n"
    . '    const ICON_STROKE = ' . ltrim($indent($json($stroke))) . ";\n"
    . '    const ICON_ALIASES = ' . ltrim($indent($json($aliases))) . ";\n"
    . "    // </icons:generated>\n";

$jsPath = $root . '/admin/js/icon.js';
$js = (string) file_get_contents($jsPath);
$next = preg_replace('#    // <icons:generated>.*?// </icons:generated>\n#s', str_replace(['\\', '$'], ['\\\\', '\\$'], $block), $js, 1, $count);
if ($count !== 1) {
    fwrite(STDERR, "icon.js: generated markers not found\n");
    exit(1);
}
if ($next !== $js) {
    $stale[] = 'admin/js/icon.js';
    if (!$check) {
        file_put_contents($jsPath, $next);
    }
}

$dir = $root . '/admin/assets/icons';
$wanted = [];
foreach (birkly_icon_names() as $name) {
    $svg = str_replace(' class="birkly-icon"', '', birkly_icon_svg($name, ['aria_hidden' => false]));
    $svg = str_replace('<svg ', '<svg xmlns="http://www.w3.org/2000/svg" ', $svg) . "\n";
    $file = $dir . '/' . $name . '.svg';
    $wanted[basename($file)] = true;
    if (!is_file($file) || file_get_contents($file) !== $svg) {
        $stale[] = 'admin/assets/icons/' . basename($file);
        if (!$check) {
            file_put_contents($file, $svg);
        }
    }
}
foreach (glob($dir . '/*.svg') ?: [] as $file) {
    if (!isset($wanted[basename($file)])) {
        $stale[] = 'admin/assets/icons/' . basename($file) . ' (remove)';
        if (!$check) {
            unlink($file);
        }
    }
}

if ($check) {
    if ($stale !== []) {
        fwrite(STDERR, "Out of date (run php scripts/build-icons.php):\n  " . implode("\n  ", $stale) . "\n");
        exit(1);
    }
    echo "Icons up to date (" . count($wanted) . " names)\n";
    exit(0);
}

echo ($stale === [] ? 'No changes' : 'Updated ' . count($stale) . ' file(s)') . ' · ' . count($wanted) . " icon names\n";
