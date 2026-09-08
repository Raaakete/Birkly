<?php
/**
 * P83-M6 — Admin routing cleanup regression tests.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function m6_fail(string $label): void
{
    global $failures;
    $failures[] = $label;
    fwrite(STDERR, "FAIL: $label\n");
}

function m6_assert(bool $ok, string $label): void
{
    if ($ok) {
        echo "PASS $label\n";
        return;
    }
    m6_fail($label);
}

$adminIndex = (string) file_get_contents($root . '/admin/index.php');
m6_assert(
    !preg_match("/['\"]automation['\"]/", $adminIndex),
    'admin/index.php has no automation literal'
);
m6_assert(
    !is_file($root . '/admin/js/automation.js'),
    'orphan admin/js/automation.js removed'
);

$studioView = (string) file_get_contents($root . '/api/studio_view.php');
m6_assert(
    str_contains($studioView, "apply_filters('birkly_studio_view_request'"),
    'studio_view.php dispatches via filter'
);
m6_assert(
    !str_contains($studioView, 'plugins/studio/includes'),
    'studio_view.php does not require plugin paths directly'
);

$health = (string) file_get_contents($root . '/core/health.php');
m6_assert(
    str_contains($health, "apply_filters('birkly_health_checks'"),
    'birkly_health_studio delegates to birkly_health_checks filter'
);
m6_assert(
    !str_contains($health, "get_plugin_state('studio')"),
    'core health.php has no studio plugin_state call'
);

$adminEntries = (string) file_get_contents($root . '/api/admin_entries.php');
m6_assert(
    str_contains($adminEntries, "do_action('birkly_entry_saved'"),
    'admin_entries fires birkly_entry_saved action'
);
m6_assert(
    !str_contains($adminEntries, 'enqueue_automation'),
    'admin_entries has no direct enqueue_automation calls'
);

$pluginActivity = (string) file_get_contents($root . '/api/plugin_activity.php');
m6_assert(
    !preg_match("/\\['plugin'\\].*'commerce'/", $pluginActivity),
    'plugin_activity emit_test has no commerce default'
);

if ($failures !== []) {
    exit(1);
}

echo "admin_routing_cleanup_test: OK\n";
