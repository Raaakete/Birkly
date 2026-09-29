<?php
/**
 * P100-M2 — Automation admin page data comes from a registered provider.
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

$GLOBALS['__HARU_INTERNAL_CALL'] = true;
$GLOBALS['haru_plugins_loaded'] = true;
$GLOBALS['birkly_plugins'] = [
    'automation' => ['id' => 'automation', 'active' => false],
];

require_once $root . '/core/plugins/admin_page_data.php';
require_once $root . '/api/admin_templates.php';

$source = (string) file_get_contents($root . '/api/admin_templates.php');
assert_true('core does not call get_automations', !str_contains($source, 'get_automations'));
assert_true('core does not call get_all_automation_blocks', !str_contains($source, 'get_all_automation_blocks'));

$called = false;
register_admin_page_data_provider('automation', static function () use (&$called): array {
    $called = true;
    throw new RuntimeException('inactive plugin must not call the provider');
}, 'automation');

$inactive = getAutomationData();
assert_true('inactive payload is an array', is_array($inactive));
assert_true('inactive automations are empty', ($inactive['automations'] ?? null) === []);
assert_true('inactive blocks are empty', ($inactive['blocks'] ?? null) === []);
assert_true('inactive flag is set', ($inactive['plugin_inactive'] ?? false) === true);
assert_true('inactive path does not call the provider', $called === false);
assert_true('inactive path has no error key', !array_key_exists('error', $inactive));

$GLOBALS['birkly_plugins']['automation']['active'] = true;
register_admin_page_data_provider('automation', static function (): array {
    return [
        'automations' => [[
            'id' => 'welcome',
            'name' => 'Welcome',
            'updated_at' => '2020-01-01T00:00:00Z',
        ]],
        'blocks' => [
            'triggers' => ['manual' => ['name' => 'Manual']],
        ],
    ];
}, 'automation');

$active = getAutomationData();
assert_true(
    'active payload keys are the contract',
    array_keys($active) === ['automations', 'blocks']
);
assert_true('active automation id is passed through', ($active['automations'][0]['id'] ?? '') === 'welcome');
assert_true('active automation name is passed through', ($active['automations'][0]['name'] ?? '') === 'Welcome');
assert_true('snapshot ignores timestamp equality', array_key_exists('updated_at', $active['automations'][0]));
assert_true('active blocks keep registered keys', isset($active['blocks']['triggers']['manual']['name']));
assert_true('active payload has no inactive flag', !array_key_exists('plugin_inactive', $active));

register_admin_page_data_provider('automation', static function (): array {
    throw new RuntimeException('provider failed');
}, 'automation');
$failed = getAutomationData();
assert_true('provider failure is an array', is_array($failed));
assert_true('provider failure keeps empty automations', ($failed['automations'] ?? null) === []);
assert_true('provider failure keeps empty blocks', ($failed['blocks'] ?? null) === []);
assert_true('provider failure reports the error', ($failed['error'] ?? '') === 'provider failed');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " failure(s)\n");
    exit(1);
}

echo "admin_template_data_test: OK\n";
