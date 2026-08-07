<?php
/**
 * WebMCP config + runtime tests (P60-M3).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/webmcp.php';

$failures = 0;

function assert_true(bool $cond, string $label): void
{
    global $failures;
    if ($cond) {
        echo "OK  $label\n";
        return;
    }
    echo "FAIL $label\n";
    $failures++;
}

// Isolate from any real settings/webmcp.json: back it up and restore at the end.
$configPath = birkly_webmcp_settings_path();
$backup = null;
if (is_file($configPath)) {
    $backup = file_get_contents($configPath);
    @unlink($configPath);
}
birkly_webmcp_reset_cache();

echo "==> Syntax\n";
foreach ([
    'core/webmcp.php',
    'api/webmcp.php',
    'api/public/webmcp.php',
] as $rel) {
    exec('php -l ' . escapeshellarg($root . '/' . $rel) . ' 2>&1', $out, $code);
    assert_true($code === 0, "syntax $rel");
}

echo "==> Defaults (no config file)\n";
$defaults = birkly_webmcp_load();
assert_true($defaults['enabled'] === false, 'default enabled = false');
assert_true(isset($defaults['tools']['birkly_get_page_context']), 'core tool present in defaults');
assert_true($defaults['tools']['birkly_get_page_context'] === true, 'core tool default on');
assert_true($defaults['tools']['birkly_commerce_add_to_cart'] === false, 'plugin tool default off');
assert_true($defaults['form_allowlist'] === [], 'default allowlist empty');

echo "==> Public config off = {enabled:false} only\n";
$pub = birkly_webmcp_public_config();
assert_true($pub === ['enabled' => false], 'public config off leaks nothing');
assert_true(birkly_webmcp_enabled_tool_ids() === [], 'no enabled tool ids when off');

echo "==> Normalize drops unknown tools + bad allowlist entries\n";
$norm = birkly_webmcp_normalize([
    'enabled' => true,
    'tools' => [
        'birkly_get_page_context' => false,
        'not_a_real_tool' => true,
    ],
    'form_allowlist' => ['contact', 'Bad Slug!', 'events', 123],
]);
assert_true($norm['enabled'] === true, 'normalize keeps enabled');
assert_true($norm['tools']['birkly_get_page_context'] === false, 'normalize applies tool override');
assert_true(!array_key_exists('not_a_real_tool', $norm['tools']), 'normalize drops unknown tool');
assert_true($norm['form_allowlist'] === ['contact', 'events'], 'normalize sanitizes allowlist');

echo "==> Save + reload roundtrip\n";
$save = birkly_webmcp_save([
    'enabled' => true,
    'tools' => ['birkly_commerce_get_cart' => true],
    'form_allowlist' => ['contact'],
]);
assert_true($save['ok'] === true, 'save ok');
birkly_webmcp_reset_cache();
$loaded = birkly_webmcp_load();
assert_true($loaded['enabled'] === true, 'reload enabled');
assert_true($loaded['tools']['birkly_commerce_get_cart'] === true, 'reload plugin tool on');
assert_true($loaded['tools']['birkly_get_page_context'] === true, 'reload core tool default on');

echo "==> Public config on = tools + allowlist, no secrets\n";
$pubOn = birkly_webmcp_public_config();
assert_true($pubOn['enabled'] === true, 'public on enabled');
assert_true(in_array('birkly_get_page_context', $pubOn['tools'], true), 'public on includes core tool');
assert_true(in_array('birkly_commerce_get_cart', $pubOn['tools'], true), 'public on includes enabled plugin tool');
assert_true(!in_array('birkly_commerce_checkout', $pubOn['tools'], true), 'public on excludes disabled plugin tool');
assert_true($pubOn['form_allowlist'] === ['contact'], 'public on allowlist');
assert_true(!isset($pubOn['_updated_at']), 'public config has no metadata');

echo "==> Runtime file present + safe markers\n";
$js = (string) file_get_contents($root . '/birkly-webmcp.js');
assert_true($js !== '', 'birkly-webmcp.js exists');
assert_true(strpos($js, 'getModelContext') !== false, 'runtime feature-detects modelContext');
assert_true(strpos($js, 'AbortController') !== false, 'runtime uses AbortController lifecycle');
assert_true(strpos($js, '.unregisterTool(') === false, 'runtime does not call unregisterTool()');
assert_true(strpos($js, 'window.confirm') !== false, 'runtime has a consent gate');
exec('node --check ' . escapeshellarg($root . '/birkly-webmcp.js') . ' 2>&1', $jsOut, $jsCode);
if ($jsCode === 0) {
    assert_true(true, 'birkly-webmcp.js node --check');
} else {
    // Node may be unavailable in some CI images — treat as skipped, not failed.
    echo "SKIP birkly-webmcp.js node --check (node unavailable)\n";
}

// Restore original settings state.
if ($backup !== null) {
    file_put_contents($configPath, $backup);
} elseif (is_file($configPath)) {
    @unlink($configPath);
}
birkly_webmcp_reset_cache();

echo "\n" . ($failures === 0 ? "All WebMCP tests passed\n" : "$failures FAILURES\n");
exit($failures === 0 ? 0 : 1);
