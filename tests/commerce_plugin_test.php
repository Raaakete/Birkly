<?php
/**
 * P6.4-M0 — Commerce plugin scaffold tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Commerce plugin tests\n\n";

$root = dirname(__DIR__);
$pluginDir = $root . '/plugins/commerce';

test_assert('plugin.json exists', is_file($pluginDir . '/plugin.json'));
test_assert('plugin.php exists', is_file($pluginDir . '/plugin.php'));
test_assert('PaymentShelf.php exists', is_file($pluginDir . '/includes/PaymentShelf.php'));
test_assert('CredentialStore.php exists', is_file($pluginDir . '/includes/CredentialStore.php'));
test_assert('Commerce fieldtype exists', is_file($pluginDir . '/fieldtypes/Commerce/Commerce.php'));
test_assert('CatalogIndexer exists', is_file($pluginDir . '/includes/CatalogIndexer.php'));
test_assert('commerce admin html exists', is_file($pluginDir . '/admin/commerce.html'));
test_assert('commerce admin js exists', is_file($pluginDir . '/admin/commerce.js'));

$metadata = json_decode(file_get_contents($pluginDir . '/plugin.json'), true);
test_assert('plugin.json parses', is_array($metadata));
test_assert('navigation page is commerce', ($metadata['navigation']['page'] ?? '') === 'commerce');
test_assert('commerce_shelf service declared', in_array('commerce_shelf', array_column($metadata['services'] ?? [], 'id'), true));
test_assert('payment_shelf alias declared', in_array('payment_shelf', array_column($metadata['services'] ?? [], 'id'), true));

$capabilities = array_keys($metadata['capabilities'] ?? []);
test_assert('manage_commerce capability', in_array('manage_commerce', $capabilities, true));
test_assert('manage_payments legacy alias', in_array('manage_payments', $capabilities, true));
test_assert('access_commerce_shelf capability', in_array('access_commerce_shelf', $capabilities, true));
test_assert('access_payment_shelf legacy alias', in_array('access_payment_shelf', $capabilities, true));

$pluginPhp = file_get_contents($pluginDir . '/plugin.php');
test_assert('registers commerce_shelf service', strpos($pluginPhp, "register_plugin_service('commerce_shelf'") !== false);
test_assert('registers payment_shelf alias', strpos($pluginPhp, "register_plugin_service('payment_shelf'") !== false);
test_assert('registers commerce API route', strpos($pluginPhp, "register_api_endpoint('commerce'") !== false);
test_assert('registers commerce admin page', strpos($pluginPhp, "register_admin_page('commerce'") !== false);
test_assert('BIRKLY_COMMERCE_BOOTSTRAP guard', strpos($pluginPhp, 'BIRKLY_COMMERCE_BOOTSTRAP') !== false);

$paymentsShim = file_get_contents($root . '/plugins/payments/plugin.php');
test_assert('payments plugin is shim', strpos($paymentsShim, 'commerce/plugin.php') !== false);

$pluginsJsonPath = $root . '/settings/plugins.json';
$pluginsJsonBackup = sys_get_temp_dir() . '/birkly-commerce-plugins-' . getmypid() . '.json';
$hadPluginsJson = is_file($pluginsJsonPath);
if ($hadPluginsJson) {
    copy($pluginsJsonPath, $pluginsJsonBackup);
}
if (!is_dir(dirname($pluginsJsonPath))) {
    mkdir(dirname($pluginsJsonPath), 0755, true);
}
file_put_contents($pluginsJsonPath, json_encode([
    'commerce' => ['active' => true, 'settings' => []],
], JSON_PRETTY_PRINT));
unset($GLOBALS['haru_plugins_loaded']);

require_once $root . '/core/plugins.php';
global $birkly_plugins;
load_plugins();

test_assert('commerce plugin active after load', ($birkly_plugins['commerce']['active'] ?? false) === true);

$shelf = get_plugin_service('commerce_shelf', false);
test_assert('commerce_shelf service registered', $shelf instanceof PaymentShelf);

$aliasShelf = get_plugin_service('payment_shelf', false);
test_assert('payment_shelf alias same instance', $aliasShelf === $shelf);

$providers = $shelf->listProviders();
test_assert('eight providers listed', count($providers) === 8);

if ($hadPluginsJson) {
    copy($pluginsJsonBackup, $pluginsJsonPath);
} else {
    @unlink($pluginsJsonPath);
}
@unlink($pluginsJsonBackup);

exit(test_finish('Commerce plugin tests'));
