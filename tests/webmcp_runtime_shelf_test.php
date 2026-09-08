<?php
/**
 * P83-M4 — WebMCP runtime pack shelf tests.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/webmcp.php';
require_once $root . '/core/plugins.php';

$failures = 0;

function shelf_assert(bool $cond, string $label): void
{
    global $failures;
    if ($cond) {
        echo "OK  $label\n";
        return;
    }
    echo "FAIL $label\n";
    $failures++;
}

$configPath = birkly_webmcp_settings_path();
$backup = is_file($configPath) ? file_get_contents($configPath) : null;
if (is_file($configPath)) {
    @unlink($configPath);
}
birkly_webmcp_reset_cache();

global $birkly_plugins;
$birkly_plugins['test_plugin'] = ['active' => true];

echo "==> Runtime shelf empty without plugin registration\n";
shelf_assert(birkly_webmcp_runtime_packs() === [], 'no runtime packs by default');

echo "==> Plugin runtime pack registration\n";
birkly_webmcp_register_runtime_pack('test_plugin', [
    'pack' => 'test_pack',
    'label' => 'Test pack',
    'note' => 'Test note',
    'global' => 'BirklyTestPack',
    'script' => '/plugins/test_plugin/assets/webmcp-pack.js',
    'tools' => ['birkly_test_plugin_action'],
]);
$packs = birkly_webmcp_runtime_packs();
shelf_assert(isset($packs['test_pack']), 'registered pack appears in shelf');
shelf_assert(($packs['test_pack']['label'] ?? '') === 'Test pack', 'pack keeps label');
shelf_assert(($packs['test_pack']['plugin_id'] ?? '') === 'test_plugin', 'pack keeps plugin_id');

echo "==> Public runtime packs respect enabled tools\n";
birkly_webmcp_register_tools('test_plugin', [
    [
        'id' => 'birkly_test_plugin_action',
        'kind' => 'write',
        'mutating' => true,
        'consent' => 'confirm',
        'default' => false,
    ],
]);
birkly_webmcp_reset_catalog_cache();
birkly_webmcp_save(['enabled' => true, 'tools' => [], 'form_allowlist' => []]);
$publicOff = birkly_webmcp_public_runtime_packs();
shelf_assert($publicOff === [], 'public packs empty when tool disabled');

birkly_webmcp_save(['enabled' => true, 'tools' => ['birkly_test_plugin_action' => true], 'form_allowlist' => []]);
$publicOn = birkly_webmcp_public_runtime_packs();
shelf_assert(count($publicOn) === 1, 'public packs include enabled tool pack');
shelf_assert(($publicOn[0]['pack'] ?? '') === 'test_pack', 'public pack id matches');
shelf_assert(in_array('birkly_test_plugin_action', $publicOn[0]['tools'] ?? [], true), 'public pack lists enabled tool');

echo "==> Script tag renderer only emits plugin pack scripts\n";
$tags = birkly_webmcp_render_pack_script_tags();
shelf_assert(strpos($tags, 'webmcp-pack.js') !== false, 'render emits pack script tag');
shelf_assert(strpos($tags, '/plugins/test_plugin/assets/webmcp-pack.js') !== false, 'render uses plugin script path');

echo "==> Admin API runtime pack metadata\n";
$apiPacks = birkly_webmcp_api_runtime_packs();
shelf_assert(count($apiPacks) >= 1, 'api runtime packs non-empty');
$found = false;
foreach ($apiPacks as $pack) {
    if (($pack['pack'] ?? '') === 'test_pack') {
        $found = true;
        shelf_assert(($pack['label'] ?? '') === 'Test pack', 'api pack label');
        shelf_assert(($pack['plugin_id'] ?? '') === 'test_plugin', 'api pack plugin_id');
    }
}
shelf_assert($found, 'api includes registered pack');

echo "==> Core runtime has no hardcoded plugin pack functions\n";
$js = (string) file_get_contents($root . '/birkly-webmcp.js');
shelf_assert(strpos($js, 'registerCommercePack') === false, 'no registerCommercePack');
shelf_assert(strpos($js, 'registerEventsPack') === false, 'no registerEventsPack');
shelf_assert(strpos($js, 'registerUserPack') === false, 'no registerUserPack');
shelf_assert(strpos($js, 'registerPluginPacks') !== false, 'generic registerPluginPacks loop');

echo "==> Official plugin runtime registrations (source repos)\n";
$commerceSrc = getenv('BIRKLY_PLUGIN_COMMERCE_ROOT') ?: dirname($root) . '/Birkly-Plugin-Commerce';
$eventsSrc = getenv('BIRKLY_PLUGIN_EVENTS_ROOT') ?: dirname($root) . '/Birkly-Plugin-Events';
$userSrc = getenv('BIRKLY_PLUGIN_USER_ROOT') ?: dirname($root) . '/Birkly-Plugin-User';
foreach ([
    'commerce' => $commerceSrc,
    'events' => $eventsSrc,
    'user_management' => $userSrc,
] as $id => $src) {
    $runtimePhp = $src . '/includes/webmcp_runtime.php';
    $runtimeJs = $src . '/assets/webmcp-pack.js';
    shelf_assert(is_file($runtimePhp), "{$id} webmcp_runtime.php exists");
    shelf_assert(is_file($runtimeJs), "{$id} webmcp-pack.js exists");
    $pluginPhp = (string) file_get_contents($src . '/plugin.php');
    shelf_assert(strpos($pluginPhp, 'birkly_webmcp_register_runtime_pack') !== false, "{$id} plugin.php registers runtime pack");
}

if ($backup !== null) {
    file_put_contents($configPath, $backup);
} elseif (is_file($configPath)) {
    @unlink($configPath);
}
birkly_webmcp_reset_cache();
birkly_webmcp_reset_catalog_cache();

echo "\n" . ($failures === 0 ? "webmcp_runtime_shelf_test: OK\n" : "$failures FAILURES\n");
exit($failures === 0 ? 0 : 1);
