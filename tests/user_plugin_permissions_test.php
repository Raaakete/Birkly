<?php
/**
 * P3-M8: User plugin permission enforcement tests.
 * Run: php tests/user_plugin_permissions_test.php
 */

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
require_once $root . '/core/plugins.php';
require_once $root . '/plugins/user_management/includes/UserStorage.php';
require_once $root . '/plugins/user_management/includes/UserPermissions.php';
require_once $root . '/plugins/user_management/includes/EntryPermissions.php';

test_reset_failures();
echo "User plugin permissions tests\n\n";

$storage = new UserStorage();
$permissions = new UserPermissions();
$entryPermissions = new EntryPermissions();

$testUserId = 'user_m8_test_' . bin2hex(random_bytes(4));
$otherUserId = 'user_m8_other_' . bin2hex(random_bytes(4));
$collectionSlug = 'm8_test_collection';

function m8_cleanup_user(UserStorage $storage, string $userId): void
{
    $path = dirname(__DIR__) . '/data/plugins/user_management/users/' . $userId . '.json';
    if (is_file($path)) {
        unlink($path);
    }
}

$storage->saveUser($testUserId, [
    'id' => $testUserId,
    'email' => $testUserId . '@m8.test',
    'name' => 'M8 Test User',
    'role' => 'member',
    'tier' => 'all',
    'status' => 'active',
    'approved' => true,
]);
$storage->saveUser($otherUserId, [
    'id' => $otherUserId,
    'email' => $otherUserId . '@m8.test',
    'name' => 'M8 Other User',
    'role' => 'member',
    'tier' => 'all',
    'status' => 'active',
    'approved' => true,
]);

$previousCollectionPerms = $storage->readMetadata('collection_permissions');
$storage->saveMetadata('collection_permissions', [
    $collectionSlug => [
        'all' => [
            'create' => true,
            'edit_own' => true,
            'delete_own' => false,
            'delete_all' => false,
        ],
    ],
]);

// --- Site role defaults ---
test_assert('member role allows read via userCan', $permissions->userCan($testUserId, 'read', $collectionSlug));
test_assert('member role denies create without tier matrix', !$permissions->userCan($otherUserId, 'create', 'no_matrix_collection'));

// --- Collection tier matrix ---
test_assert('tier matrix grants create', $permissions->userCan($testUserId, 'create', $collectionSlug));
test_assert('tier matrix grants edit_own for author', $permissions->userCan(
    $testUserId,
    'edit',
    ['collection' => $collectionSlug, 'author_id' => $testUserId, 'entry' => 'entry_1']
));
test_assert('tier matrix denies edit_own for non-author', !$permissions->userCan(
    $otherUserId,
    'edit',
    ['collection' => $collectionSlug, 'author_id' => $testUserId, 'entry' => 'entry_1']
));
test_assert('tier matrix denies delete_own when unchecked', !$permissions->userCan(
    $testUserId,
    'delete',
    ['collection' => $collectionSlug, 'author_id' => $testUserId, 'entry' => 'entry_1']
));

// --- EntryPermissions delegation ---
$ownEntry = ['id' => 'entry_1', 'author_id' => $testUserId];
$foreignEntry = ['id' => 'entry_2', 'author_id' => $otherUserId];

test_assert('EntryPermissions canEditEntry for owner', $entryPermissions->canEditEntry($testUserId, $ownEntry, $collectionSlug));
test_assert('EntryPermissions denies edit for non-owner', !$entryPermissions->canEditEntry($otherUserId, $ownEntry, $collectionSlug));
test_assert('EntryPermissions canDeleteEntry false when tier denies', !$entryPermissions->canDeleteEntry($testUserId, $ownEntry, $collectionSlug));

// --- Admin page registry ---
$pluginJson = json_decode(file_get_contents($root . '/plugins/user_management/plugin.json'), true);
$navPage = $pluginJson['navigation']['page'] ?? '';
test_assert('plugin.json navigation uses site_users', $navPage === 'site_users');
test_assert(
    'site_users admin template exists',
    is_file($root . '/plugins/user_management/admin/site_users.html')
);

global $birkly_admin_pages;
test_assert(
    'register_admin_page maps site_users',
    isset($birkly_admin_pages['site_users'])
        && is_file($birkly_admin_pages['site_users'])
);

// Restore metadata and remove test users
if ($previousCollectionPerms === null) {
    $storage->saveMetadata('collection_permissions', []);
} else {
    $storage->saveMetadata('collection_permissions', $previousCollectionPerms);
}
m8_cleanup_user($storage, $testUserId);
m8_cleanup_user($storage, $otherUserId);

exit(test_finish('user_plugin_permissions_test'));
