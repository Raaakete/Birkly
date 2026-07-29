<?php
/**
 * CMS marketplace display currency settings and conversion.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/api/storage.php';
require_once $root . '/core/marketplace.php';

$errors = [];
$settingsDir = sys_get_temp_dir() . '/birkly-mp-currency-' . bin2hex(random_bytes(4));
mkdir($settingsDir, 0777, true);
$GLOBALS['__birkly_marketplace_settings_dir'] = $settingsDir;

try {
    if (birkly_marketplace_display_currency() !== 'EUR') {
        $errors[] = 'Default display currency should be EUR';
    }

    birkly_marketplace_sync_display_currency('USD');
    birkly_marketplace_settings_reset();
    if (birkly_marketplace_display_currency() !== 'USD') {
        $errors[] = 'Saved display currency should persist';
    }

    $converted = birkly_marketplace_convert_cents(1000, 'EUR', 'USD');
    if ($converted < 1000 || $converted > 1200) {
        $errors[] = 'EUR to USD conversion out of expected range';
    }

    try {
        birkly_marketplace_sync_display_currency('XXX');
        $errors[] = 'Unsupported currency should fail';
    } catch (InvalidArgumentException) {
        // expected
    }
} finally {
    unset($GLOBALS['__birkly_marketplace_settings_dir'], $GLOBALS['__birkly_marketplace_settings']);
    array_map('unlink', glob($settingsDir . '/*') ?: []);
    @rmdir($settingsDir);
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

echo "marketplace_display_currency_test: PASS\n";
exit(0);
