<?php
/**
 * Settings page template — settings-page.js must load (tabs depend on it).
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Settings page template tests\n\n";

$template = file_get_contents(dirname(__DIR__) . '/admin/templates/settings.html');

test_assert('no unparsed include_script', strpos($template, '{include_script') === false);
test_assert('no unparsed json users', strpos($template, '{json users}') === false);
test_assert('references settings-page.js', strpos($template, 'settings-page.js') !== false);
test_assert('general tab uses settings-stack', strpos($template, 'settings-stack') !== false);
test_assert('plugins tab has inline grid', strpos($template, 'plugins-grid') !== false && strpos($template, 'manage_all_plugins') === false);
test_assert('activity tab has audit table', strpos($template, 'activity-log-table') !== false && strpos($template, 'log-entries') !== false);
test_assert('webhooks tab has inline table', strpos($template, 'webhooks-list') !== false);
test_assert('users tab has no redundant summary links', strpos($template, 'users_roles_summary_help') === false);
test_assert('ai tab uses unified connection grid', strpos($template, 'ai-connection-kinds-grid') !== false);
test_assert('references webhooks.js', strpos($template, 'webhooks.js') !== false);

exit(test_finish('settings_page_test'));
