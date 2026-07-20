<?php
/**
 * P29 — entry editor sidebar: tabs, auto_slug checkbox, scoped JS.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Entry editor sidebar tests\n\n";

$template = file_get_contents(dirname(__DIR__) . '/admin/templates/entry-editor.html');

test_assert('has sidebar-tabs container', strpos($template, 'class="sidebar-tabs"') !== false);
test_assert('has auto_slug hidden input', strpos($template, 'name="auto_slug"') !== false && strpos($template, 'id="auto_slug"') !== false);
test_assert('has auto_slug checkbox', strpos($template, 'id="auto_slug_checkbox"') !== false);
test_assert('auto-generate slug label present', strpos($template, 'Auto-generate slug from title') !== false);
test_assert('loads sidebar-tabs.js', strpos($template, 'sidebar-tabs.js') !== false);
test_assert('does not use global tab-pane querySelectorAll', strpos($template, "querySelectorAll('.tab-pane')") === false);
test_assert('does not use global tab-btn querySelectorAll', strpos($template, "querySelectorAll('.tab-btn')") === false);

$entryEditorJs = file_get_contents(dirname(__DIR__) . '/admin/js/entry-editor.js');
test_assert('entry-editor.js uses generic title selector', strpos($entryEditorJs, "querySelector('input[name=\"title\"]')") !== false);
test_assert('entry-editor.js syncs auto_slug hidden field', strpos($entryEditorJs, 'auto_slug') !== false);

$contentCss = file_get_contents(dirname(__DIR__) . '/admin/css/pages/content.css');
test_assert('content.css scopes sidebar tab-content display', strpos($contentCss, '.sidebar-tabs .tab-content') !== false);
test_assert('content.css scopes sidebar tab-pane display', strpos($contentCss, '.sidebar-tabs .tab-pane.active') !== false);

test_assert('loads entry-editor-sidebar.js', strpos($template, 'entry-editor-sidebar.js') !== false);
test_assert('has permalink preview', strpos($template, 'permalink-preview') !== false);
test_assert('has status clarity text', strpos($template, 'status-clarity-text') !== false);
test_assert('has meta SERP preview', strpos($template, 'serp-preview-title') !== false);
test_assert('has structured data field', strpos($template, 'structured_data_json') !== false);
test_assert('has AI summary field', strpos($template, 'ai_summary') !== false);
test_assert('sidebar config JSON present', strpos($template, 'entry-editor-sidebar-config') !== false);
test_assert('inline script has CSP nonce', strpos($template, 'birklyScriptNonceAttr') !== false);
test_assert('unsaved badge in main actions bar', strpos($template, 'editor-unsaved-badge') !== false);
test_assert('no entry settings sidebar header', strpos($template, 'sidebar-tabs-header') === false);

$sidebarJs = file_get_contents(dirname(__DIR__) . '/admin/js/entry-editor-sidebar.js');
test_assert('sidebar js handles permalink preview', strpos($sidebarJs, 'buildPermalink') !== false);
test_assert('sidebar js generates structured data', strpos($sidebarJs, 'generateStructuredData') !== false);

exit(test_finish('entry_editor_sidebar_test'));
