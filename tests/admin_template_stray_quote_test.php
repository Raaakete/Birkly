<?php
/**
 * P5.4-M0 — Admin templates must not contain stray ?>\" after PHP short tags.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Admin template stray quote audit\n\n";

$root = dirname(__DIR__);
$templatesDir = $root . '/admin/templates';
$violations = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($templatesDir, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $path = $file->getPathname();
    if (!preg_match('/\.(html|php)$/', $path)) {
        continue;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        continue;
    }
    foreach ($lines as $num => $line) {
        if (preg_match('/\?\>\\\\"/', $line)) {
            $rel = substr($path, strlen($root) + 1);
            $violations[] = $rel . ':' . ($num + 1);
        }
    }
}

test_assert('no stray ?>\" in admin templates', $violations === [], implode(', ', $violations));

$collectionEditor = $templatesDir . '/collection-editor.html';
$ceContent = is_file($collectionEditor) ? file_get_contents($collectionEditor) : '';
test_assert(
    'collection-editor no unescaped quote in sub-entry example',
    strpos($ceContent, 'class=\"sub-entry">\n') === false,
    'found class=\\"sub-entry"> — closing quote must be escaped'
);
test_assert(
    'collection-editor sub-entry example properly escaped',
    strpos($ceContent, 'class=\"sub-entry\">\n') !== false
);

exit(test_finish('admin_template_stray_quote_test'));
