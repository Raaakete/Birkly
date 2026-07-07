<?php
/**
 * P18-M3 — Plugin health service tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Plugin health tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/core/plugins.php';
require_once $root . '/core/plugins/health.php';
require_once $root . '/core/plugins/permissions.php';

load_plugins();

$unknown = birkly_plugin_health_check('nonexistent_plugin_xyz');
test_assert('unknown plugin status', ($unknown['status'] ?? '') === 'unknown');

$automation = birkly_plugin_health_check('automation');
test_assert('automation plugin healthy or warning', in_array($automation['status'] ?? '', ['healthy', 'warning'], true));

$all = birkly_plugin_health_all();
test_assert('health all includes automation', isset($all['automation']));

grant_plugin_capability('automation', 'modify_entries', 'global');
$withWarning = birkly_plugin_health_check('automation');
test_assert(
    'undeclared capability triggers warning',
    ($withWarning['status'] ?? '') === 'warning' || !empty($withWarning['warnings'])
);

revoke_plugin_capability('automation', 'modify_entries', 'global');

exit(test_finish('plugin_health_test'));
