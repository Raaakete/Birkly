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
    ['id' => 'payments-widget', 'plugin_id' => 'payments', 'title' => 'Payments', 'roles' => ['admin']],
    ['id' => 'other-widget', 'plugin_id' => 'other', 'title' => 'Open', 'roles' => ['admin', 'editor']],
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
    ['id' => 'payments-widget', 'plugin_id' => 'payments', 'title' => 'Payments', 'roles' => ['admin']],
]);
test_assert('widget hidden when enable_dashboard_widget false', count($filteredAdmin) === 0);

$birkly_plugins['payments']['settings']['enable_dashboard_widget'] = true;
$filteredAdmin2 = birkly_filter_dashboard_widgets_for_user([
    ['id' => 'payments-widget', 'plugin_id' => 'payments', 'title' => 'Payments'],
]);
test_assert('admin sees widget when enabled and roles from settings default', count($filteredAdmin2) === 1);

$paymentsInit = file_get_contents(dirname(__DIR__) . '/plugins/commerce/init.php');
test_assert('payments_dashboard_widgets function exists', strpos($paymentsInit, 'function payments_dashboard_widgets') !== false);
test_assert('commerce widget defaults to enabled', strpos($paymentsInit, "commerce_get_setting('enable_dashboard_widget', true)") !== false);
test_assert('commerce widget uses Widget API v2 endpoint', strpos($paymentsInit, "route=commerce&action=analytics_summary") !== false);
test_assert('commerce widget has a named mount', strpos($paymentsInit, "'mount' => 'commerceStats'") !== false);

$pluginPhp = file_get_contents(dirname(__DIR__) . '/plugins/commerce/plugin.php');
test_assert('dashboard callback registered', strpos($pluginPhp, 'dashboard_widgets_callback') !== false);

$eventsInit = file_get_contents(dirname(__DIR__) . '/plugins/events/init.php');
$eventsApi = file_get_contents(dirname(__DIR__) . '/plugins/events/api/events.php');
test_assert('events dashboard widget function exists', strpos($eventsInit, 'function events_dashboard_widgets') !== false);
test_assert('events widget uses dashboard summary endpoint', strpos($eventsInit, 'action=dashboard_summary') !== false);
test_assert('events dashboard summary is protected', strpos($eventsApi, "case 'dashboard_summary':") !== false
    && strpos($eventsApi, 'events_require_admin();') !== false);

$studioInit = file_get_contents(dirname(__DIR__) . '/plugins/studio/init.php');
$studioApi = file_get_contents(dirname(__DIR__) . '/plugins/studio/api/studio.php');
test_assert('studio dashboard widget function exists', strpos($studioInit, 'function studio_dashboard_widgets') !== false);
test_assert('studio summary requires studio management', strpos($studioApi, "'dashboard_summary'") !== false
    && strpos($studioApi, 'studio_require_manage();') !== false);

$automationCore = file_get_contents(dirname(__DIR__) . '/plugins/automation/automation.php');
$automationApi = file_get_contents(dirname(__DIR__) . '/plugins/automation/api/automation.php');
test_assert('automation dashboard widget function exists', strpos($automationCore, 'function automation_dashboard_widgets') !== false);
test_assert('automation dashboard summary verifies access', strpos($automationApi, 'automation_dashboard_user_can_view()') !== false);

$dashboardJs = file_get_contents(dirname(__DIR__) . '/admin/js/dashboard.js');
test_assert('plugin widget mount handlers are registered', strpos($dashboardJs, "registerMount('eventsUpcoming'") !== false
    && strpos($dashboardJs, "registerMount('studioPublishing'") !== false
    && strpos($dashboardJs, "registerMount('automationRuns'") !== false);

exit(test_finish('dashboard_widgets_test'));
