<?php
/**
 * Durable Hub license bindings survive settings reloads (CMS deploys).
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

$settingsDir = sys_get_temp_dir() . '/birkly-hub-lic-' . bin2hex(random_bytes(4));
$pluginsDir = $settingsDir . '/plugins';
mkdir($settingsDir, 0755, true);
mkdir($pluginsDir, 0755, true);
$GLOBALS['__birkly_marketplace_settings_dir'] = $settingsDir;
$GLOBALS['__birkly_plugins_dir'] = $pluginsDir;
birkly_marketplace_hub_licenses_reset();

$row = birkly_marketplace_remember_hub_license(
    'birkly-commerce',
    'BIRKLY-AAAA-BBBB-CCCC-DDDD',
    'tok_test',
    ['status' => 'active'],
    'Birkly Commerce',
    '1.0.59'
);

assert_true(($row['license_ref'] ?? '') === 'DDDD', 'license_ref stored from key');
assert_true(is_file($settingsDir . '/hub_licenses.json'), 'hub_licenses.json written under settings');

birkly_marketplace_hub_licenses_reset();
$pending = birkly_marketplace_pending_hub_licenses();
assert_true(count($pending) === 1, 'pending license restored after reset');
assert_true(($pending[0]['license_key'] ?? '') === 'BIRKLY-AAAA-BBBB-CCCC-DDDD', 'full key survives reload');
assert_true(($pending[0]['offer_slug'] ?? '') === 'birkly-commerce', 'offer slug restored');

// Simulate installed plugin — pending list should clear.
mkdir($pluginsDir . '/commerce', 0755, true);
file_put_contents($pluginsDir . '/commerce/plugin.json', json_encode([
    'name' => 'Commerce',
    'version' => '1.0.59',
    'marketplace_slug' => 'birkly-commerce',
], JSON_THROW_ON_ERROR));
file_put_contents($pluginsDir . '/commerce/plugin.php', "<?php\n");
file_put_contents($settingsDir . '/plugins.json', json_encode([
    'commerce' => [
        'active' => true,
        'marketplace_slug' => 'birkly-commerce',
        'marketplace_version' => '1.0.59',
        'license_ref' => 'DDDD',
    ],
], JSON_THROW_ON_ERROR));

assert_true(birkly_marketplace_pending_hub_licenses() === [], 'pending clears once offer is installed');

birkly_marketplace_forget_hub_license('birkly-commerce');
birkly_marketplace_hub_licenses_reset();
assert_true(birkly_marketplace_load_hub_licenses() === [], 'forget removes durable binding');

birkly_marketplace_remove_directory($settingsDir);
unset($GLOBALS['__birkly_marketplace_settings_dir'], $GLOBALS['__birkly_plugins_dir'], $GLOBALS['birkly_plugins']);
birkly_marketplace_hub_licenses_reset();

if ($failures > 0) {
    fwrite(STDERR, "marketplace_hub_licenses_test: {$failures} failure(s)\n");
    exit(1);
}

echo "marketplace_hub_licenses_test: PASS\n";
