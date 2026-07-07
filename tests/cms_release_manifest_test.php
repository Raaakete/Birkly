<?php
/**
 * P10-M1 — CMS release manifest tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "CMS release manifest tests\n\n";

$root = dirname(__DIR__);
$manifestPath = $root . '/scripts/cms-release-manifest.json';

test_assert('manifest file exists', is_file($manifestPath));

$manifest = json_decode(file_get_contents($manifestPath), true);
test_assert('manifest parses as JSON object', is_array($manifest));

$official = $manifest['official_plugin_ids'] ?? [];
test_assert('official_plugin_ids is array', is_array($official));
foreach (['commerce', 'events', 'automation', 'user_management'] as $id) {
    test_assert("official_plugin_ids includes $id", in_array($id, $official, true));
}

$excludes = $manifest['excludes'] ?? [];
test_assert('excludes plugins/* pattern', in_array('plugins/*', $excludes, true));
test_assert('ships plugins README exception', in_array('!plugins/README.md', $excludes, true));

test_assert('empty_plugins_dir enabled', ($manifest['empty_plugins_dir'] ?? false) === true);

$shipped = $manifest['plugins_dir_shipped_files'] ?? [];
test_assert('plugins README.md in shipped list', in_array('plugins/README.md', $shipped, true));

$buildScript = $root . '/scripts/build-cms-release.sh';
test_assert('build-cms-release.sh exists', is_file($buildScript));

$outZip = sys_get_temp_dir() . '/birkly-release-test-' . getmypid() . '.zip';
@unlink($outZip);
$envOut = 'OUT=' . escapeshellarg($outZip);
$cmd = $envOut . ' bash ' . escapeshellarg($buildScript) . ' 2>&1';
$output = [];
$code = 0;
exec($cmd, $output, $code);
test_assert('build-cms-release.sh exits 0', $code === 0, implode("\n", $output));
test_assert('release zip created', is_file($outZip));

$zip = new ZipArchive();
$opened = $zip->open($outZip);
test_assert('release zip opens', $opened === true);

$hasPluginPackage = false;
$hasPluginsReadme = false;
$hasPluginsGitkeep = false;
$hasStartHere = false;
$hasStartHereTxt = false;
if ($opened === true) {
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (preg_match('#^plugins/[^/]+/plugin\.php$#', $name)) {
            $hasPluginPackage = true;
        }
        if ($name === 'plugins/README.md') {
            $hasPluginsReadme = true;
        }
        if ($name === 'plugins/.gitkeep') {
            $hasPluginsGitkeep = true;
        }
        if ($name === 'START_HERE.html') {
            $hasStartHere = true;
        }
        if ($name === 'START_HERE.txt') {
            $hasStartHereTxt = true;
        }
    }
    $zip->close();
}

test_assert('release zip excludes plugin packages', $hasPluginPackage === false);
test_assert('release zip includes plugins/README.md', $hasPluginsReadme === true);
test_assert('release zip includes plugins/.gitkeep', $hasPluginsGitkeep === true);
test_assert('release zip includes START_HERE.html at zip root', $hasStartHere === true);
test_assert('release zip includes START_HERE.txt', $hasStartHereTxt === true);

$includes = $manifest['includes'] ?? [];
test_assert('manifest includes START_HERE.html', in_array('START_HERE.html', $includes, true));
test_assert('manifest includes START_HERE.txt', in_array('START_HERE.txt', $includes, true));

@unlink($outZip);

exit(test_finish('cms_release_manifest_test'));
