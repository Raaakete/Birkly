<?php
/**
 * Hub install must replace empty leftover plugin dirs (no plugin.php).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/marketplace.php';

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

$pluginsDir = sys_get_temp_dir() . '/birkly-mp-extract-' . bin2hex(random_bytes(4));
mkdir($pluginsDir . '/commerce', 0755, true);
$GLOBALS['__birkly_plugins_dir'] = $pluginsDir;

assert_true(
    birkly_marketplace_plugin_dir_is_leftover($pluginsDir . '/commerce'),
    'empty commerce dir without plugin.php is leftover'
);

$zipPath = $pluginsDir . '/sample.zip';
$zip = new ZipArchive();
assert_true($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'create sample zip');
$zip->addFromString('plugin.json', json_encode([
    'name' => 'Commerce',
    'version' => '1.0.0',
    'description' => 'test',
], JSON_THROW_ON_ERROR));
$zip->addFromString('plugin.php', "<?php\n");
$zip->close();

$extract = birkly_marketplace_extract_plugin_zip($zipPath, 'commerce', false);
assert_true((bool) ($extract['ok'] ?? false), 'extract replaces leftover empty plugin dir');
assert_true(is_file($pluginsDir . '/commerce/plugin.php'), 'plugin.php written after leftover replace');

// Valid installed plugin must still hard-fail without upgrade/replace.
$blocked = birkly_marketplace_extract_plugin_zip($zipPath, 'commerce', false);
assert_true(!($blocked['ok'] ?? true), 'valid plugin dir still blocks fresh install');
assert_true(
    str_contains(implode(' ', $blocked['errors'] ?? []), 'already exists'),
    'error mentions directory already exists'
);

$upgrade = birkly_marketplace_extract_plugin_zip($zipPath, 'commerce', true);
assert_true((bool) ($upgrade['ok'] ?? false), 'replaceExisting=true overwrites valid plugin dir');

// Runtime data colocated under the plugin dir must survive Hub upgrades.
mkdir($pluginsDir . '/commerce/connections', 0755, true);
file_put_contents($pluginsDir . '/commerce/connections/stripe.json', '{"provider":"stripe"}');
file_put_contents($pluginsDir . '/commerce/encryption_key.txt', 'test-key');
file_put_contents($pluginsDir . '/commerce/plugin.php', "<?php\n// old\n");

$zip2 = $pluginsDir . '/sample2.zip';
$zip = new ZipArchive();
assert_true($zip->open($zip2, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'create upgrade zip');
$zip->addFromString('plugin.json', json_encode([
    'name' => 'Commerce',
    'version' => '1.0.1',
    'description' => 'test',
], JSON_THROW_ON_ERROR));
$zip->addFromString('plugin.php', "<?php\n// new\n");
$zip->close();

$upgradePreserve = birkly_marketplace_extract_plugin_zip($zip2, 'commerce', true);
assert_true((bool) ($upgradePreserve['ok'] ?? false), 'upgrade with runtime data succeeds');
assert_true(
    is_file($pluginsDir . '/commerce/connections/stripe.json'),
    'connections survive Hub upgrade'
);
assert_true(
    is_file($pluginsDir . '/commerce/encryption_key.txt'),
    'encryption_key survives Hub upgrade'
);
assert_true(
    str_contains((string) file_get_contents($pluginsDir . '/commerce/plugin.php'), 'new'),
    'package files are replaced from zip'
);
assert_true(
    !is_dir($pluginsDir . '/.commerce-upgrade-') && count(glob($pluginsDir . '/.commerce-upgrade-*') ?: []) === 0,
    'upgrade backup dir is cleaned up'
);

// Leftover dir (no plugin.php) with runtime JSON must not be wiped on fresh install.
birkly_marketplace_remove_directory($pluginsDir . '/commerce');
mkdir($pluginsDir . '/commerce/orders', 0755, true);
file_put_contents($pluginsDir . '/commerce/orders/ord_leftover.json', '{"id":"ord_leftover"}');
file_put_contents($pluginsDir . '/commerce/encryption_key.txt', 'leftover-key');

$leftoverInstall = birkly_marketplace_extract_plugin_zip($zipPath, 'commerce', false);
assert_true((bool) ($leftoverInstall['ok'] ?? false), 'fresh install replaces leftover dir that has runtime data');
assert_true(is_file($pluginsDir . '/commerce/plugin.php'), 'plugin.php written after leftover replace with runtime');
assert_true(
    is_file($pluginsDir . '/commerce/orders/ord_leftover.json'),
    'orders survive leftover replace when runtime data present'
);
assert_true(
    is_file($pluginsDir . '/commerce/encryption_key.txt'),
    'encryption key survives leftover replace'
);

// A name that is only in plugin.json runtime_data is protected too.
birkly_marketplace_remove_directory($pluginsDir . '/commerce');
mkdir($pluginsDir . '/commerce/ledger', 0755, true);
file_put_contents($pluginsDir . '/commerce/plugin.json', json_encode([
    'name' => 'Commerce',
    'version' => '1.0.0',
    'runtime_data' => [
        'directories' => ['ledger', '../outside'],
        'files' => ['merchant_seal.txt'],
    ],
], JSON_THROW_ON_ERROR));
file_put_contents($pluginsDir . '/commerce/ledger/row.json', '{"id":"row"}');
file_put_contents($pluginsDir . '/commerce/merchant_seal.txt', 'seal');

$declared = birkly_marketplace_declared_runtime_data($pluginsDir . '/commerce');
assert_true($declared['directories'] === ['ledger'], 'declared directories ignore unsafe names');
assert_true($declared['files'] === ['merchant_seal.txt'], 'declared files are read from plugin.json');
assert_true(
    birkly_marketplace_plugin_dir_has_runtime_data($pluginsDir . '/commerce'),
    'declared runtime files count even when they are not in the builtin list'
);

$declaredInstall = birkly_marketplace_extract_plugin_zip($zipPath, 'commerce', false);
assert_true((bool) ($declaredInstall['ok'] ?? false), 'fresh install keeps a declared runtime directory');
assert_true(is_file($pluginsDir . '/commerce/ledger/row.json'), 'declared directory survives leftover replace');
assert_true(is_file($pluginsDir . '/commerce/merchant_seal.txt'), 'declared file survives leftover replace');
assert_true(!is_file($pluginsDir . '/outside'), 'declared names cannot escape the plugin directory');

// A leftover with no runtime files and no declaration is still replaceable.
birkly_marketplace_remove_directory($pluginsDir . '/commerce');
mkdir($pluginsDir . '/commerce', 0755, true);
file_put_contents($pluginsDir . '/commerce/notes.txt', 'not runtime');
$notesInstall = birkly_marketplace_extract_plugin_zip($zipPath, 'commerce', false);
assert_true((bool) ($notesInstall['ok'] ?? false), 'leftover without runtime data is replaceable');
assert_true(!is_file($pluginsDir . '/commerce/notes.txt'), 'undeclared leftover file is not preserved');
assert_true(is_file($pluginsDir . '/commerce/plugin.php'), 'replaceable leftover receives plugin.php');

@unlink($zipPath);
@unlink($zip2);
birkly_marketplace_remove_directory($pluginsDir);
unset($GLOBALS['__birkly_plugins_dir']);

if ($failures > 0) {
    fwrite(STDERR, "marketplace_extract_leftover_test: {$failures} failure(s)\n");
    exit(1);
}

echo "marketplace_extract_leftover_test: PASS\n";
