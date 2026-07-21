<?php
/**
 * Static dashboard lifecycle contracts (P31).
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Dashboard lifecycle contract tests\n\n";

$root = dirname(__DIR__);
$template = file_get_contents($root . '/admin/templates/dashboard.html');
$dashboard = file_get_contents($root . '/admin/js/dashboard.js');
$runtime = file_get_contents($root . '/admin/js/dashboard-widgets.js');
require_once $root . '/core/dashboard_widgets.php';

test_assert('template has one API widget grid', substr_count($template, 'id="dashboard-widget-grid"') === 1);
test_assert('template has no legacy dashboard sections',
    strpos($template, 'stats-grid') === false
    && strpos($template, 'analytics-section') === false
    && strpos($template, 'plugin-widgets-content') === false);
test_assert('dashboard loads the Widget API v2 manifest',
    strpos($dashboard, "/api/dashboard.php?action=widgets") !== false
    && strpos($dashboard, 'Birkly.Dashboard.fetchWidgetData(widget)') !== false
    && strpos($dashboard, 'Birkly.Dashboard.mountWidget(body, widget, data)') !== false);
test_assert('dashboard renders standard widget cards with layout support',
    strpos($dashboard, 'card dash-widget dash-widget--size-') !== false
    && strpos($dashboard, 'dashboardState.widgets') !== false
    && strpos($dashboard, 'setupWidgetDragAndDrop') !== false);
test_assert('dashboard exposes customize controls',
    strpos($template, 'id="dashboard-customize-modal"') !== false
    && strpos($template, 'id="dashboard-customize-btn"') !== false
    && strpos($dashboard, 'save_layout') !== false);
test_assert('dashboard uses one manifest refresh lifecycle',
    substr_count($dashboard, 'setInterval(') === 1
    && strpos($dashboard, 'Number(widget.refresh)') !== false);
test_assert('dashboard removes legacy loaders',
    strpos($dashboard, 'loadPluginWidgets') === false
    && strpos($dashboard, 'loadAnalytics') === false
    && strpos($dashboard, 'loadStats') === false
    && strpos($dashboard, 'loadPerformanceMetrics') === false);

$mounts = array_filter(array_column(birkly_get_core_dashboard_widgets(), 'mount'));
foreach ($mounts as $mount) {
    test_assert('core mount handler registered: ' . $mount,
        strpos($dashboard, "registerMount('" . $mount . "'") !== false);
}

test_assert('dashboard has named plugin mount handlers',
    strpos($dashboard, "registerMount('commerceStats'") !== false
    && strpos($dashboard, "registerMount('eventsUpcoming'") !== false
    && strpos($dashboard, "registerMount('studioPublishing'") !== false
    && strpos($dashboard, "registerMount('automationRuns'") !== false);
test_assert('widget runtime calls named mount handlers',
    strpos($runtime, 'function mountWidget') !== false
    && strpos($runtime, 'handler(element, widget, data)') !== false);
test_assert('widget code never evaluates API-provided JavaScript',
    preg_match('/\beval\s*\(/i', $dashboard . "\n" . $runtime) === 0);
test_assert('plugin fallback is restricted to the plugin registry',
    strpos($runtime, 'Never resolve arbitrary globals') !== false
    && strpos($runtime, 'name.split') === false);

exit(test_finish('dashboard_lifecycle_contract_test'));
