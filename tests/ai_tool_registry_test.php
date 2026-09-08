<?php
/**
 * P83-M5 — AI tool registry plugin ownership via MCP shelf.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/ai_tool_registry.php';
require_once $root . '/core/plugins/mcp_shelf.php';

$failures = 0;

function air_assert(bool $cond, string $label): void
{
    global $failures;
    if ($cond) {
        echo "OK  $label\n";
        return;
    }
    echo "FAIL $label\n";
    $failures++;
}

echo "==> Core tools have no plugin owner\n";
air_assert(birkly_ai_tool_plugin_id('read_entry') === '', 'read_entry is core');
air_assert(birkly_ai_tool_plugin_id('cms_admin_action') === '', 'cms_admin_action is core');

echo "==> Registered plugin tools resolve plugin_id from MCP shelf\n";
global $birkly_plugins;
$birkly_plugins['test_plugin'] = ['active' => true];
$birkly_plugins['commerce'] = ['active' => true];

register_plugin_mcp_tool('test_plugin', 'test_shelf_tool', [
    'description' => 'Test shelf ownership',
    'category' => 'read',
    'inputSchema' => ['type' => 'object', 'properties' => []],
    'permissions' => ['read'],
    'handler' => static fn (): array => ['status' => 'ok'],
]);

air_assert(
    birkly_ai_tool_plugin_id('test_shelf_tool') === 'test_plugin',
    'registered tool uses shelf plugin_id'
);

register_plugin_mcp_tool('commerce', 'commerce_shelf_probe', [
    'description' => 'Commerce shelf probe',
    'category' => 'read',
    'inputSchema' => ['type' => 'object', 'properties' => []],
    'permissions' => ['read'],
    'handler' => static fn (): array => ['status' => 'ok'],
]);

air_assert(
    birkly_ai_tool_plugin_id('commerce_shelf_probe') === 'commerce',
    'commerce-prefixed registered tool uses shelf not regex'
);

echo "==> Unregistered prefixed names do not infer plugin ownership\n";
air_assert(
    birkly_ai_tool_plugin_id('commerce_unregistered_probe') === '',
    'unregistered commerce_* tool has no plugin owner'
);
air_assert(
    birkly_ai_tool_plugin_id('automation_not_registered') === '',
    'unregistered automation_* tool has no plugin owner'
);

echo "==> Registry source has no regex fallback in birkly_ai_tool_plugin_id\n";
$src = (string) file_get_contents($root . '/core/ai_tool_registry.php');
air_assert(
    strpos($src, "preg_match('/^(commerce|automation") === false,
    'no regex prefix fallback in ai_tool_registry.php'
);

echo "\n" . ($failures === 0 ? "ai_tool_registry_test: OK\n" : "$failures FAILURES\n");
exit($failures === 0 ? 0 : 1);
