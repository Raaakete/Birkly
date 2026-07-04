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

exit(test_finish('settings_page_test'));
