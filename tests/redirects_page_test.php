<?php

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Redirects settings integration tests\n\n";

$settingsPath = dirname(__DIR__) . '/admin/templates/settings.html';
$panelPath = dirname(__DIR__) . '/admin/partials/redirects-panel.php';
$redirectsJsPath = dirname(__DIR__) . '/admin/js/redirects.js';

test_assert('redirects panel partial exists', is_file($panelPath));
test_assert('redirects.js exists', is_file($redirectsJsPath));

$settingsContent = (string) file_get_contents($settingsPath);
test_assert('settings includes redirects tab', strpos($settingsContent, 'data-tab="redirects"') !== false);
test_assert('settings includes redirects panel', strpos($settingsContent, 'redirects-panel.php') !== false);
test_assert('settings loads redirects.js', strpos($settingsContent, '/admin/js/redirects.js') !== false);

$panelContent = (string) file_get_contents($panelPath);
test_assert('redirects panel wires redirects api', strpos($panelContent, 'redirect-form') !== false && strpos($panelContent, 'redirects-table-body') !== false);

$redirectsPagePath = dirname(__DIR__) . '/admin/templates/redirects.html';
test_assert('legacy redirects.html redirects to settings', is_file($redirectsPagePath) && strpos((string) file_get_contents($redirectsPagePath), '#redirects') !== false);

exit(test_finish('redirects_page_test'));
