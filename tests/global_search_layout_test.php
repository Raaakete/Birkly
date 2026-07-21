<?php
/**
 * Global admin search UI wiring smoke test.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Global search layout tests\n\n";

$root = dirname(__DIR__);
$topbar = (string) file_get_contents($root . '/admin/partials/admin-topbar.php');
$footer = (string) file_get_contents($root . '/admin/partials/layout_footer.php');
$modal = (string) file_get_contents($root . '/admin/partials/global-search-modal.php');
$scriptPath = $root . '/admin/js/global-search.js';

test_assert('topbar includes search button', strpos($topbar, 'id="topbar-search-btn"') !== false);
test_assert('layout footer includes global search modal', strpos($footer, "global-search-modal.php") !== false);
test_assert('layout footer includes global search script', strpos($footer, 'global-search.js') !== false);
test_assert('modal partial renders overlay shell', strpos($modal, 'id="search-overlay"') !== false && strpos($modal, 'id="search-results"') !== false);
test_assert('global search script exists', is_file($scriptPath));

exit(test_finish('global_search_layout_test'));
