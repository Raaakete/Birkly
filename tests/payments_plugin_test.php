<?php
/**
 * P6.1 — Payments plugin scaffold tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Payments plugin tests\n\n";

$root = dirname(__DIR__);
$pluginDir = $root . '/plugins/payments';

test_assert('plugin.json exists', is_file($pluginDir . '/plugin.json'));
test_assert('plugin.php exists', is_file($pluginDir . '/plugin.php'));
test_assert('PaymentShelf.php exists', is_file($pluginDir . '/includes/PaymentShelf.php'));
test_assert('CredentialStore.php exists', is_file($pluginDir . '/includes/CredentialStore.php'));
test_assert('ActivityLog.php exists', is_file($pluginDir . '/includes/ActivityLog.php'));
test_assert('WebhookRouter.php exists', is_file($pluginDir . '/includes/WebhookRouter.php'));
test_assert('payments admin html exists', is_file($pluginDir . '/admin/payments.html'));
test_assert('payments admin js exists', is_file($pluginDir . '/admin/payments.js'));
test_assert('payments admin css exists', is_file($pluginDir . '/admin/payments.css'));

$metadata = json_decode(file_get_contents($pluginDir . '/plugin.json'), true);
test_assert('plugin.json parses', is_array($metadata));
test_assert('navigation page is payments', ($metadata['navigation']['page'] ?? '') === 'payments');
test_assert('payment_shelf service declared', in_array('payment_shelf', array_column($metadata['services'] ?? [], 'id'), true));

$capabilities = array_keys($metadata['capabilities'] ?? []);
test_assert('manage_payments capability', in_array('manage_payments', $capabilities, true));
test_assert('view_payment_activity capability', in_array('view_payment_activity', $capabilities, true));
test_assert('view_payment_stats capability', in_array('view_payment_stats', $capabilities, true));
test_assert('access_payment_shelf capability', in_array('access_payment_shelf', $capabilities, true));

$settingsSchema = $metadata['settings_schema'] ?? [];
test_assert('enable_dashboard_widget schema', isset($settingsSchema['enable_dashboard_widget']));
test_assert('enable_dashboard_widget default false', ($settingsSchema['enable_dashboard_widget']['default'] ?? true) === false);
test_assert('dashboard_widget_roles schema', isset($settingsSchema['dashboard_widget_roles']));
test_assert('dashboard_widget_roles default admin', ($settingsSchema['dashboard_widget_roles']['default'] ?? []) === ['admin']);

$pluginPhp = file_get_contents($pluginDir . '/plugin.php');
test_assert('registers payment_shelf service', strpos($pluginPhp, "register_plugin_service('payment_shelf'") !== false);
test_assert('registers payments API route', strpos($pluginPhp, "register_api_endpoint('payments'") !== false);
test_assert('registers payments_webhook route', strpos($pluginPhp, "register_api_endpoint('payments_webhook'") !== false);
test_assert('registers admin page', strpos($pluginPhp, "register_admin_page('payments'") !== false);

$providerFiles = [
    'StripeProvider.php', 'MollieProvider.php', 'PayPalProvider.php', 'AdyenProvider.php',
    'SquareProvider.php', 'RazorpayProvider.php', 'KomojuProvider.php', 'PayssionProvider.php',
];
foreach ($providerFiles as $file) {
    test_assert('provider ' . $file, is_file($pluginDir . '/includes/providers/' . $file));
}

$adminHtml = file_get_contents($pluginDir . '/admin/payments.html');
test_assert('admin html references external js', strpos($adminHtml, 'payments.js') !== false);
test_assert('admin html references css', strpos($adminHtml, 'payments.css') !== false);
test_assert('admin html has no inline script block', strpos($adminHtml, '<script>') === false);
test_assert('admin has connections tab', strpos($adminHtml, 'data-tab="connections"') !== false);
test_assert('admin has activity tab', strpos($adminHtml, 'data-tab="activity"') !== false);
test_assert('admin has settings tab', strpos($adminHtml, 'data-tab="settings"') !== false);

// Plugin loads and registers service when active
$pluginsJsonPath = $root . '/settings/plugins.json';
$pluginsJsonBackup = sys_get_temp_dir() . '/birkly-payments-plugins-' . getmypid() . '.json';
$hadPluginsJson = is_file($pluginsJsonPath);
if ($hadPluginsJson) {
    copy($pluginsJsonPath, $pluginsJsonBackup);
}
if (!is_dir(dirname($pluginsJsonPath))) {
    mkdir(dirname($pluginsJsonPath), 0755, true);
}
file_put_contents($pluginsJsonPath, json_encode([
    'payments' => ['active' => true, 'settings' => []],
], JSON_PRETTY_PRINT));
unset($GLOBALS['haru_plugins_loaded']);

require_once $root . '/core/plugins.php';
global $birkly_plugins;
load_plugins();

test_assert('payments plugin active after load', ($birkly_plugins['payments']['active'] ?? false) === true);

$shelf = get_plugin_service('payment_shelf', false);
test_assert('payment_shelf service registered', $shelf instanceof PaymentShelf);
test_assert('payment_shelf isConfigured false initially', $shelf->isConfigured() === false);

$providers = $shelf->listProviders();
test_assert('eight providers listed', count($providers) === 8);

if ($hadPluginsJson) {
    copy($pluginsJsonBackup, $pluginsJsonPath);
} else {
    @unlink($pluginsJsonPath);
}
@unlink($pluginsJsonBackup);

exit(test_finish('Payments plugin tests'));
