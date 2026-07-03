<?php
/**
 * entry-editor.html must parse as PHP (direct include route).
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Entry editor template syntax test\n\n";

$path = dirname(__DIR__) . '/admin/templates/entry-editor.html';
test_assert('entry-editor.html exists', is_file($path));

$output = [];
$code = 0;
exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
test_assert('entry-editor.html PHP syntax valid', $code === 0, implode("\n", $output));

exit(test_finish('entry_editor_template_test'));
