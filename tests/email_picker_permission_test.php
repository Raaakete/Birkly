<?php
/**
 * P100-M4 — email picker permissions come from core plus plugin registration.
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

function checkPermission($user, $permission)
{
    $permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
    if (in_array('*', $permissions, true)) {
        return true;
    }

    return in_array($permission, $permissions, true);
}

function birkly_is_plugin_active(string $plugin_id): bool
{
    return !empty($GLOBALS['email_picker_test_active'][$plugin_id]);
}

require_once $root . '/core/connections/email_picker.php';

$connections = (string) file_get_contents($root . '/api/connections.php');
assert_true(!str_contains($connections, 'manage_commerce'), 'connections.php has no manage_commerce literal');
assert_true(!str_contains($connections, 'manage_payments'), 'connections.php has no manage_payments literal');
assert_true(str_contains($connections, 'birkly_email_picker_allowed'), 'connections.php asks the picker registry');

$commerceOnly = ['role' => 'editor', 'permissions' => ['manage_commerce']];
$paymentsOnly = ['role' => 'editor', 'permissions' => ['manage_payments']];
$settings = ['role' => 'editor', 'permissions' => ['manage_settings']];
$content = ['role' => 'editor', 'permissions' => ['manage_content']];
$admin = ['role' => 'admin', 'permissions' => ['*']];
$none = ['role' => 'viewer', 'permissions' => ['read']];

assert_true(birkly_email_picker_allowed($settings), 'core manage_settings can pick');
assert_true(birkly_email_picker_allowed($content), 'core manage_content can pick');
assert_true(birkly_email_picker_allowed($admin), 'core admin can pick');
assert_true(!birkly_email_picker_allowed($commerceOnly), 'manage_commerce is not special before registration');
assert_true(!birkly_email_picker_allowed($none), 'read-only role cannot pick');

$GLOBALS['email_picker_test_active'] = ['commerce' => true];
register_email_picker_permission('manage_commerce', 'commerce');
register_email_picker_permission('manage_payments', 'commerce');

assert_true(birkly_email_picker_allowed($commerceOnly), 'active commerce manager can pick');
assert_true(birkly_email_picker_allowed($paymentsOnly), 'active payments manager can pick');

$GLOBALS['email_picker_test_active'] = ['commerce' => false];
assert_true(!birkly_email_picker_allowed($commerceOnly), 'inactive commerce slug is not special');
assert_true(birkly_email_picker_allowed($settings), 'core manage_settings still works when commerce is inactive');

$plugin = dirname($root) . '/Birkly-Plugin-Commerce/plugin.php';
if (!is_file($plugin)) {
    $plugin = dirname($root) . '/birkly-plugin-commerce/plugin.php';
}
assert_true(is_file($plugin), 'Commerce plugin.php is available');
$pluginSrc = (string) file_get_contents($plugin);
assert_true(str_contains($pluginSrc, "register_email_picker_permission('manage_commerce', 'commerce')"), 'Commerce registers manage_commerce');
assert_true(str_contains($pluginSrc, "register_email_picker_permission('manage_payments', 'commerce')"), 'Commerce registers manage_payments');

if ($failures > 0) {
    fwrite(STDERR, "email_picker_permission_test: {$failures} failure(s)\n");
    exit(1);
}

echo "email_picker_permission_test: PASS\n";
