<?php
/**
 * A public read can define get_plugin_setting before the plugin registry loads.
 * Later writes on the live registry must be visible in the same process.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
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

$tmp = sys_get_temp_dir() . '/birkly-setting-precedence-' . getmypid();
mkdir($tmp, 0775, true);
$GLOBALS['__birkly_plugin_state_settings_dir'] = $tmp;
$GLOBALS['haru_plugins_loaded'] = true;
$GLOBALS['birkly_plugins'] = [];

require_once $root . '/core/public_bootstrap.php';
require_once $root . '/core/plugins.php';

$GLOBALS['birkly_plugins']['sample_plugin'] = [
    'id' => 'sample_plugin',
    'active' => true,
    'settings' => ['provider' => 'manual'],
];

assert_true(get_plugin_setting('sample_plugin', 'provider') === 'manual', 'live registry is read before the public settings cache');

set_plugin_setting('sample_plugin', 'provider', 'stripe_tax');
assert_true(get_plugin_setting('sample_plugin', 'provider') === 'stripe_tax', 'a later setting write is visible to get_plugin_setting');

if ($failures > 0) {
    fwrite(STDERR, "plugin_setting_precedence_test: {$failures} failure(s)\n");
    exit(1);
}

echo "plugin_setting_precedence_test: PASS\n";
