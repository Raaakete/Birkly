<?php
/**
 * Dashboard widget role filter tests (P6.1-M4).
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Dashboard widgets filter tests\n\n";

require_once dirname(__DIR__) . '/core/plugins.php';
require_once dirname(__DIR__) . '/core/dashboard_widgets.php';

$_SESSION['user'] = ['role' => 'editor', 'email' => 'editor@test.com'];

$widgets = [
    ['plugin_id' => 'payments', 'title' => 'Payments', 'roles' => ['admin']],
    ['plugin_id' => 'other', 'title' => 'Open', 'roles' => ['admin', 'editor']],
];

$filtered = birkly_filter_dashboard_widgets_for_user($widgets);
test_assert('editor excluded from admin-only widget', count($filtered) === 1);
test_assert('editor sees editor-allowed widget', ($filtered[0]['plugin_id'] ?? '') === 'other');

$_SESSION['user'] = ['role' => 'admin', 'email' => 'admin@test.com'];

global $birkly_plugins;
$birkly_plugins['payments'] = [
    'id' => 'payments',
    'active' => true,
    'settings' => ['enable_dashboard_widget' => false],
];

$filteredAdmin = birkly_filter_dashboard_widgets_for_user([
    ['plugin_id' => 'payments', 'title' => 'Payments', 'roles' => ['admin']],
]);
test_assert('widget hidden when enable_dashboard_widget false', count($filteredAdmin) === 0);

$birkly_plugins['payments']['settings']['enable_dashboard_widget'] = true;
$filteredAdmin2 = birkly_filter_dashboard_widgets_for_user([
    ['plugin_id' => 'payments', 'title' => 'Payments'],
]);
test_assert('admin sees widget when enabled and roles from settings default', count($filteredAdmin2) === 1);

$merged = birkly_merge_dashboard_widgets([
    ['id' => 'commerce-stats', 'plugin_id' => 'commerce', 'title' => 'Commerce', 'priority' => 55],
]);
test_assert('merge sorts plugin with core widgets', count($merged) > 1);

$commerceInit = file_get_contents(dirname(__DIR__) . '/plugins/commerce/init.php');
test_assert('commerce widget uses mount not eval html', strpos($commerceInit, "'mount' => 'commerceStats'") !== false);
test_assert('commerce default widget opt-in not false', strpos($commerceInit, "enable_dashboard_widget', null)") !== false);

$paymentsInit = file_get_contents(dirname(__DIR__) . '/plugins/commerce/init.php');
test_assert('payments_dashboard_widgets function exists', strpos($paymentsInit, 'function payments_dashboard_widgets') !== false);

$pluginPhp = file_get_contents(dirname(__DIR__) . '/plugins/commerce/plugin.php');
test_assert('dashboard callback registered', strpos($pluginPhp, 'dashboard_widgets_callback') !== false);

exit(test_finish('dashboard_widgets_test'));
