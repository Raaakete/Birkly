<?php
/**
 * P5.5-M0-T1 — Admin dropdown.js must not clobber ContentEditor toolbar menus.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "ContentEditor dropdown isolation tests\n\n";

$path = dirname(__DIR__) . '/admin/js/dropdown.js';
test_assert('dropdown.js exists', is_file($path));

$js = file_get_contents($path);

test_assert('isContentEditorMenu helper defined', strpos($js, 'isContentEditorMenu') !== false);
test_assert('content-editor-toolbar referenced', strpos($js, 'content-editor-toolbar') !== false);
test_assert('closeAdminDropdownMenus skips CE menus', preg_match('/closeAdminDropdownMenus[\s\S]*?isContentEditorMenu\s*\(\s*menu\s*\)/', $js) === 1);
test_assert('outside click ignores CE toolbar', strpos($js, 'isContentEditorToolbarTarget(e.target)') !== false);

exit(test_finish('contenteditor_dropdown_test'));
