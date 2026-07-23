<?php
/**
 * Official plugins marketplace readiness — manifest v2 and zip build.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/plugins.php';

test_reset_failures();
echo "Official plugins manifest tests\n\n";

$manifestPath = dirname(__DIR__) . '/scripts/official-plugins-manifest.json';
$export = json_decode((string) file_get_contents($manifestPath), true);
test_assert('export manifest exists', is_array($export) && ($export['manifest_version'] ?? 0) === 2);

$officialIds = ['commerce', 'events', 'automation', 'user_management', 'studio'];
foreach ($officialIds as $id) {
    $pluginJsonPath = dirname(__DIR__) . "/plugins/{$id}/plugin.json";
    test_assert("{$id} plugin.json exists", is_file($pluginJsonPath));
    $meta = json_decode((string) file_get_contents($pluginJsonPath), true);
    test_assert("{$id} has marketplace_slug", is_string($meta['marketplace_slug'] ?? null) && $meta['marketplace_slug'] !== '');
    test_assert("{$id} has data_access", is_array($meta['data_access'] ?? null));
    test_assert("{$id} has permissions", is_array($meta['permissions'] ?? null) && $meta['permissions'] !== []);
    test_assert("{$id} has hooks", is_array($meta['hooks'] ?? null));
}

$buildScript = dirname(__DIR__) . '/scripts/build-plugin-zip.sh';
test_assert('build script exists', is_file($buildScript));

$buildOutput = [];
$buildCode = 0;
exec('bash ' . escapeshellarg($buildScript) . ' commerce 1.0.0 2>&1', $buildOutput, $buildCode);
$zipPath = trim($buildOutput[0] ?? '');
test_assert('commerce zip builds from marketplace_slug', $buildCode === 0 && is_file($zipPath));

if (is_file($zipPath)) {
    $zip = new ZipArchive();
    test_assert('commerce zip opens', $zip->open($zipPath) === true);
    test_assert('commerce zip has root plugin.json', $zip->locateName('plugin.json') !== false);
    test_assert('commerce zip has root plugin.php', $zip->locateName('plugin.php') !== false);
    $zip->close();
    @unlink($zipPath);
}

test_assert(
    'birkly_plugin_install_path finds commerce in dev tree',
    birkly_plugin_install_path('commerce') !== null
);

exit(test_finish('official_plugins_manifest_test'));
