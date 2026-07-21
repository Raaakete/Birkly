<?php
/**
 * Dashboard Widget API v2 tests (P31-M4).
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Dashboard widget API v2 tests\n\n";

require_once dirname(__DIR__) . '/core/dashboard_widgets.php';

$normalized = birkly_normalize_dashboard_widget([
    'id' => 'test-widget',
    'title' => 'Test',
    'size' => 'lg',
    'priority' => 42,
    'mount' => 'testMount',
    'endpoint' => '/api/example',
    'refresh' => 30,
]);
test_assert('normalize sets id', ($normalized['id'] ?? '') === 'test-widget');
test_assert('normalize sets size', ($normalized['size'] ?? '') === 'lg');
test_assert('normalize sets priority', (int) ($normalized['priority'] ?? 0) === 42);
test_assert('normalize sets mount', ($normalized['mount'] ?? '') === 'testMount');
test_assert('normalize sets refresh', (int) ($normalized['refresh'] ?? 0) === 30);
test_assert('invalid size falls back to md', birkly_normalize_dashboard_widget(['id' => 'x', 'size' => 'huge'])['size'] === 'md');

$core = birkly_get_core_dashboard_widgets();
test_assert('core registry not empty', count($core) >= 8);
$coreIds = array_column($core, 'id');
test_assert('core includes quick-actions', in_array('quick-actions', $coreIds, true));
test_assert('core includes content-snapshot', in_array('content-snapshot', $coreIds, true));
test_assert('core includes recent-activity', in_array('recent-activity', $coreIds, true));

$merged = birkly_merge_dashboard_widgets([
    ['id' => 'plugin-a', 'title' => 'Plugin', 'priority' => 200, 'plugin_id' => 'sample'],
]);
test_assert('merge includes core + plugin', count($merged) > count($core));
$ids = array_column($merged, 'id');
test_assert('merge includes plugin widget', in_array('plugin-a', $ids, true));

$widgetsJs = file_get_contents(dirname(__DIR__) . '/admin/js/dashboard-widgets.js');
test_assert('dashboard-widgets.js exists', $widgetsJs !== false);
test_assert('mountWidget exported', strpos($widgetsJs, 'mountWidget') !== false);
test_assert('no eval in dashboard-widgets.js', stripos($widgetsJs, 'eval(') === false);

$dashboardJs = file_get_contents(dirname(__DIR__) . '/admin/js/dashboard.js');
test_assert('no eval in dashboard.js', stripos($dashboardJs, 'eval(') === false);

$template = file_get_contents(dirname(__DIR__) . '/admin/templates/dashboard.html');
test_assert('dashboard uses widget grid', strpos($template, 'dashboard-widget-grid') !== false);
test_assert('legacy stats-grid removed', strpos($template, 'stats-grid') === false);

exit(test_finish('dashboard_widget_api_test'));
