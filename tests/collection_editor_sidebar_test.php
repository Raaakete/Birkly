<?php
/**
 * P29 — collection editor sidebar: tabs, auto_slug checkbox, scoped JS.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Collection editor sidebar tests\n\n";

$template = file_get_contents(dirname(__DIR__) . '/admin/templates/collection-editor.html');

test_assert('has sidebar-tabs container', strpos($template, 'class="sidebar-tabs"') !== false);
test_assert('has auto_slug hidden input', strpos($template, 'name="auto_slug"') !== false);
test_assert('has auto_slug checkbox', strpos($template, 'id="auto_slug_checkbox"') !== false);
test_assert('auto-generate slug label present', strpos($template, 'Auto-generate slug from title') !== false);
test_assert('loads sidebar-tabs.js', strpos($template, 'sidebar-tabs.js') !== false);
test_assert('slug input visible (no change-slug link)', strpos($template, 'id="change-slug-link"') === false);
test_assert('does not use global tab-pane querySelectorAll', strpos($template, "querySelectorAll('.tab-pane')") === false);
test_assert('does not use global tab-btn querySelectorAll', strpos($template, "querySelectorAll('.tab-btn')") === false);
test_assert('sub-collection auto_slug support', strpos($template, 'sub_auto_slug') !== false);
test_assert('does not require entry_slug.php from template', strpos($template, 'entry_slug.php') === false);
test_assert('inline script has CSP nonce attribute', strpos($template, 'birklyScriptNonceAttr') !== false);
test_assert('add sub-collection field button wired', strpos($template, 'add-sub-collection-field-btn') !== false);
test_assert('automation tab lists collection automations', strpos($template, 'collection-automation-list') !== false);
test_assert('automation tab not placeholder only', strpos($template, 'Automation settings coming soon') === false);

exit(test_finish('collection_editor_sidebar_test'));
