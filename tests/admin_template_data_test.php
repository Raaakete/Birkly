<?php
/**
 * Plugin admin page data comes from a registered provider.
 * Core does not know an official plugin's payload keys.
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
assert_true('core has no getAutomationData', !str_contains($source, 'function getAutomationData'));
assert_true('core has no automation page case', !str_contains($source, "case 'automation'"));
assert_true('core does not filter collection automations', !str_contains($source, 'collection_automations'));

$called = false;
register_admin_page_data_provider('automation', static function () use (&$called): array {
    $called = true;
    throw new RuntimeException('inactive plugin must not call the provider');
}, 'automation');

$inactive = getPageData('automation');
assert_true('inactive payload is an array', is_array($inactive));
assert_true('inactive path does not call the provider', $called === false);
assert_true('inactive path is not a provider payload', !isset($inactive['automations']));
assert_true('inactive path reports the page is not served by core', ($inactive['error'] ?? '') === 'Page not found');

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

$active = getPageData('automation');
assert_true('active payload is passed through unchanged', ($active['automations'][0]['id'] ?? '') === 'welcome');
assert_true('active automation name is passed through', ($active['automations'][0]['name'] ?? '') === 'Welcome');
assert_true('snapshot ignores timestamp equality', array_key_exists('updated_at', $active['automations'][0] ?? []));
assert_true('active blocks keep registered keys', isset($active['blocks']['triggers']['manual']['name']));
assert_true('core does not add an inactive flag', !array_key_exists('plugin_inactive', $active));

register_admin_page_data_provider('automation', static function (): array {
    throw new RuntimeException('provider failed');
}, 'automation');
$failed = getPageData('automation');
assert_true('provider failure is an array', is_array($failed));
assert_true('provider failure reports the error', ($failed['error'] ?? '') === 'provider failed');
assert_true('provider failure does not invent automation keys', !isset($failed['automations']) && !isset($failed['blocks']));

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " failure(s)\n");
    exit(1);
}

echo "admin_template_data_test: OK\n";
