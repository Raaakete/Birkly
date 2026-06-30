<?php
/**
 * P5.2-M1: Default CMS roles seeding and listing.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/http_test_helpers.php';
require_once dirname(__DIR__) . '/core/roles.php';

test_reset_failures();
echo "Roles tests\n\n";

$root = dirname(__DIR__);
$settingsDir = $root . '/settings';
$rolesFile = $settingsDir . '/roles.json';
$backupSettings = sys_get_temp_dir() . '/birkly-roles-settings-' . getmypid();

if (is_dir($settingsDir)) {
    birkly_test_backup_directory($settingsDir, $backupSettings);
} else {
    mkdir($settingsDir, 0755, true);
}

if (is_file($rolesFile)) {
    unlink($rolesFile);
}

$roles = birkly_ensure_default_roles();
test_assert('roles.json created when missing', is_file($rolesFile));
test_assert('default seed has 4 roles', count($roles) >= 4, 'count ' . count($roles));

$ids = birkly_list_role_ids();
test_assert('list includes admin', in_array('admin', $ids, true));
test_assert('list includes editor', in_array('editor', $ids, true));
test_assert('list includes author', in_array('author', $ids, true));
test_assert('list includes viewer', in_array('viewer', $ids, true));

// Empty file should re-seed
file_put_contents($rolesFile, '[]');
$reseeded = birkly_ensure_default_roles();
test_assert('empty roles.json re-seeded', count($reseeded) >= 4);

if (is_dir($backupSettings)) {
    birkly_test_restore_directory($settingsDir, $backupSettings);
    birkly_test_remove_directory($backupSettings);
}

exit(test_finish('roles_test'));
