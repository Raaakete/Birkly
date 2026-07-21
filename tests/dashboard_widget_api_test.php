<?php
/**
 * Dashboard Widget API v2 contract tests (P31-M3).
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Dashboard Widget API v2 tests\n\n";

require_once dirname(__DIR__) . '/core/dashboard_widgets.php';

$widget = birkly_normalize_dashboard_widget([
    'id' => 'plugin-summary',
    'plugin_id' => 'sample_plugin',
    'title' => 'Plugin summary',
    'size' => 'lg',
    'priority' => 42,
    'endpoint' => '/api/index.php?route=sample&action=summary',
    'mount' => 'BirklyDashboardWidgets.sampleSummary',
    'refresh' => 60,
    'roles' => ['admin', 'editor', 'admin', 3],
]);
test_assert('normalizes widget API v2 fields', $widget !== null
    && $widget['size'] === 'lg'
    && $widget['priority'] === 42
    && $widget['refresh'] === 60
    && $widget['source'] === 'plugin');
test_assert('normalizes widget roles', $widget !== null && $widget['roles'] === ['admin', 'editor']);
test_assert('invalid widget id is rejected', birkly_normalize_dashboard_widget(['id' => '../unsafe']) === null);
test_assert('invalid size falls back to md', birkly_normalize_dashboard_widget(['id' => 'safe', 'size' => 'huge'])['size'] === 'md');
test_assert('external endpoint is rejected', birkly_normalize_dashboard_widget([
    'id' => 'external-endpoint',
    'endpoint' => 'https://example.test/widget',
])['endpoint'] === null);
test_assert('non-API endpoint is rejected', birkly_normalize_dashboard_widget([
    'id' => 'other-endpoint',
    'endpoint' => '/admin/index.php',
])['endpoint'] === null);
test_assert('API path traversal is rejected', birkly_normalize_dashboard_widget([
    'id' => 'traversal-endpoint',
    'endpoint' => '/api/%2e%2e/admin/index.php',
])['endpoint'] === null);
test_assert('expression mount is rejected', birkly_normalize_dashboard_widget([
    'id' => 'unsafe-mount',
    'mount' => 'window.alert(1)',
])['mount'] === null);

$core = birkly_get_core_dashboard_widgets();
$coreIds = array_column($core, 'id');
test_assert('core widget registry is populated', count($core) >= 8);
test_assert('core registry includes content overview', in_array('content-snapshot', $coreIds, true));
test_assert('core registry includes pending review widget', in_array('pending-review', $coreIds, true));
test_assert('core registry includes system status', in_array('system-status', $coreIds, true));

$merged = birkly_merge_dashboard_widgets([
    ['id' => 'plugin-summary', 'plugin_id' => 'sample_plugin', 'priority' => 75],
    ['id' => 'content-snapshot', 'plugin_id' => 'sample_plugin', 'priority' => 1],
    ['id' => 'invalid endpoint', 'plugin_id' => 'sample_plugin'],
]);
$mergedIds = array_column($merged, 'id');
test_assert('composition includes a valid plugin widget', in_array('plugin-summary', $mergedIds, true));
test_assert('core widget IDs cannot be overridden', count(array_keys($mergedIds, 'content-snapshot', true)) === 1);
test_assert('composition rejects invalid plugin widgets', !in_array('invalid endpoint', $mergedIds, true));
test_assert('composition is ordered by priority', $merged[0]['id'] === 'content-snapshot');

$_SESSION['user'] = ['role' => 'editor'];
$visible = birkly_filter_dashboard_widgets_for_user($merged);
test_assert('role filter excludes admin-only core widgets', !in_array('system-status', array_column($visible, 'id'), true));
test_assert('role filter retains permitted core widgets', in_array('content-snapshot', array_column($visible, 'id'), true));

$widgetIds = ['content-snapshot', 'pending-review', 'drafts'];
$layout = birkly_normalize_dashboard_layout([
    'widget_order' => ['pending-review', 'drafts', 'content-snapshot', 'invalid-widget'],
    'hidden_widgets' => ['drafts', 'unknown'],
    'widget_sizes' => ['pending-review' => 'full', 'drafts' => 'huge'],
], $widgetIds);
test_assert('layout normalizes widget order', $layout['widget_order'] === ['pending-review', 'drafts', 'content-snapshot']);
test_assert('layout normalizes hidden widgets', $layout['hidden_widgets'] === ['drafts']);
test_assert('layout normalizes widget sizes', $layout['widget_sizes'] === ['pending-review' => 'full']);

$widgets = birkly_merge_dashboard_widgets([]);
$widgets = array_values(array_filter($widgets, static fn ($widget) => in_array($widget['id'], $widgetIds, true)));
$applied = birkly_apply_dashboard_layout($widgets, $layout);
test_assert('layout applies custom order', array_column($applied, 'id') === ['pending-review', 'drafts', 'content-snapshot']);
$hiddenById = [];
foreach ($applied as $widget) {
    $hiddenById[$widget['id']] = !empty($widget['hidden']);
}
test_assert('layout marks hidden widgets', ($hiddenById['drafts'] ?? false) === true && ($hiddenById['pending-review'] ?? false) === false);
test_assert('layout applies size overrides', ($applied[0]['size'] ?? '') === 'md');

exit(test_finish('dashboard_widget_api_test'));
