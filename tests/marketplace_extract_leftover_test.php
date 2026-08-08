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

@unlink($zipPath);
birkly_marketplace_remove_directory($pluginsDir);
unset($GLOBALS['__birkly_plugins_dir']);

if ($failures > 0) {
    fwrite(STDERR, "marketplace_extract_leftover_test: {$failures} failure(s)\n");
    exit(1);
}

echo "marketplace_extract_leftover_test: PASS\n";
