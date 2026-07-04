<?php
/**
 * P6.2 — Shopping Cart plugin scaffold tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Shopping Cart plugin tests\n\n";

$root = dirname(__DIR__);
$pluginDir = $root . '/plugins/shopping_cart';

test_assert('plugin.json exists', is_file($pluginDir . '/plugin.json'));
test_assert('plugin.php exists', is_file($pluginDir . '/plugin.php'));
test_assert('CheckoutService.php exists', is_file($pluginDir . '/includes/CheckoutService.php'));
test_assert('ConsumerPaymentHelper.php exists', is_file($pluginDir . '/includes/ConsumerPaymentHelper.php'));
test_assert('cart admin html exists', is_file($pluginDir . '/admin/cart.html'));
test_assert('cart api exists', is_file($pluginDir . '/api/cart.php'));
test_assert('shop_products seed exists', is_file($pluginDir . '/collections/shop_products/collection.json'));

$metadata = json_decode(file_get_contents($pluginDir . '/plugin.json'), true);
test_assert('plugin.json parses', is_array($metadata));
test_assert('navigation page is shopping_cart', ($metadata['navigation']['page'] ?? '') === 'shopping_cart');
test_assert('depends on payments service', in_array('payment_shelf', array_column($metadata['dependencies']['services'] ?? [], 'id'), true));

$pluginPhp = file_get_contents($pluginDir . '/plugin.php');
test_assert('registers shopping_cart API route', strpos($pluginPhp, "register_api_endpoint('shopping_cart'") !== false);
test_assert('registers admin page', strpos($pluginPhp, "register_admin_page('shopping_cart'") !== false);
test_assert('grants access_payment_shelf on activation', strpos($pluginPhp, "grant_plugin_capability('shopping_cart', 'access_payment_shelf'") !== false);

$adminJs = file_get_contents($pluginDir . '/admin/cart.js');
test_assert('admin js uses list_orders', strpos($adminJs, 'list_orders') !== false);

$initPhp = file_get_contents($pluginDir . '/init.php');
test_assert('registers payment event handlers', strpos($initPhp, 'registerPaymentEventHandlers') !== false);
test_assert('order_created automation trigger', strpos($initPhp, "'order_created'") !== false);

exit(test_finish('Shopping Cart plugin tests'));
