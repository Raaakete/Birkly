<?php
/**
 * Dashboard lead order comes from each plugin's runtime record.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function assert_true(string $label, bool $condition): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $label;
        fwrite(STDERR, "FAIL: $label\n");
        return;
    }
    echo "OK: $label\n";
}

require_once $root . '/core/dashboard_metrics.php';

$source = (string) file_get_contents($root . '/core/dashboard_metrics.php');
assert_true(
    'core does not name official plugins in the lead list',
    !str_contains($source, "return ['commerce', 'user_management', 'events']")
);

$GLOBALS['birkly_plugins'] = [
    'events' => ['active' => true, 'dashboard_metric_lead' => 30],
    'commerce' => ['active' => true, 'dashboard_metric_lead' => 10],
    'studio' => ['active' => true],
    'user_management' => ['active' => true, 'dashboard_metric_lead' => '20'],
];

assert_true(
    'lead order follows the rank each plugin set',
    birkly_dashboard_metric_lead_plugins() === ['commerce', 'user_management', 'events']
);

$slots = birkly_dashboard_metric_slots(
    ['published', 'revenue', 'members', 'rsvps', 'drafts'],
    ['revenue' => 'commerce', 'members' => 'user_management', 'rsvps' => 'events'],
    []
);
assert_true(
    'one lead metric per ranked plugin, then published',
    $slots === ['revenue', 'members', 'rsvps', 'published']
);

$GLOBALS['birkly_plugins'] = [
    'studio' => ['active' => true],
    'automation' => ['active' => true],
];
assert_true('plugins without a rank are not leads', birkly_dashboard_metric_lead_plugins() === []);

$unordered = birkly_dashboard_metric_slots(
    ['published', 'runs', 'projects'],
    ['runs' => 'automation', 'projects' => 'studio'],
    []
);
assert_true(
    'unranked plugin metrics keep discovery order before content defaults',
    $unordered === ['runs', 'projects', 'published']
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " failure(s)\n");
    exit(1);
}

echo "dashboard_metric_leads_test: OK\n";
