<?php
/**
 * Plugin Birkly version constraints (alpha/beta + requires_birkly).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/plugins/version_constraints.php';
require_once $root . '/core/plugins/dependencies.php';
require_once $root . '/core/platform_ops.php';

$errors = [];

if (!birkly_version_satisfies_constraint('8.2.0', '>=8.1')) {
    $errors[] = 'PHP-style >= constraint should pass';
}
if (birkly_version_satisfies_constraint('8.0.1', '>=8.1')) {
    $errors[] = 'PHP-style >= constraint should fail below minimum';
}
if (!birkly_version_satisfies_constraint('1.0.1-p86.2', '>=1.0.0')) {
    $errors[] = 'Pre-release CMS tag should satisfy >=1.0.0';
}

$commerceMetaPath = $root . '/plugins/commerce/plugin.json';
if (!is_file($commerceMetaPath)) {
    $errors[] = 'commerce plugin.json missing for fixture';
} else {
    $commerceMeta = json_decode((string) file_get_contents($commerceMetaPath), true);
    if (!is_array($commerceMeta)) {
        $errors[] = 'commerce plugin.json invalid';
    } else {
        $constraint = birkly_plugin_birkly_constraint_from_metadata($commerceMeta);
        if ($constraint !== '>=0.1.0') {
            $errors[] = 'commerce should use requires_birkly (>=0.1.0), got ' . var_export($constraint, true);
        }
        if (!birkly_version_satisfies_constraint('1.0.0', $constraint ?? '')) {
            $errors[] = '1.0.0 should satisfy commerce requires_birkly';
        }
    }
}

$versionFile = $root . '/VERSION';
$originalVersion = is_file($versionFile) ? (string) file_get_contents($versionFile) : null;
file_put_contents($versionFile, "0.0.1\n");
$GLOBALS['__birkly_platform_config'] = ['release_channel' => 'alpha'];

$compat = birkly_cms_version_for_plugin_compat();
if ($compat !== '1.0.0') {
    $errors[] = 'alpha 0.0.1 should floor compat version to 1.0.0, got ' . $compat;
}

$GLOBALS['__birkly_platform_config'] = ['release_channel' => 'stable'];
$compatStable = birkly_cms_version_for_plugin_compat();
if ($compatStable !== '0.0.1') {
    $errors[] = 'stable 0.0.1 should not floor compat version, got ' . $compatStable;
}

if ($originalVersion !== null) {
    file_put_contents($versionFile, $originalVersion);
} else {
    @unlink($versionFile);
}
unset($GLOBALS['__birkly_platform_config']);

require_once $root . '/core/plugins.php';
load_plugins();
$GLOBALS['__birkly_platform_config'] = ['release_channel' => 'alpha'];
file_put_contents($versionFile, "0.0.1\n");

$depErrors = check_plugin_dependencies('commerce');
foreach ($depErrors as $msg) {
    if (str_contains($msg, 'Birkly version mismatch')) {
        $errors[] = 'commerce on alpha 0.0.1 should not fail Birkly version: ' . $msg;
        break;
    }
}

if ($originalVersion !== null) {
    file_put_contents($versionFile, $originalVersion);
} else {
    @unlink($versionFile);
}
unset($GLOBALS['__birkly_platform_config']);

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

echo "plugin_version_constraints_test: OK\n";
