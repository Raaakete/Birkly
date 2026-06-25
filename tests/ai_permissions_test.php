<?php
/**
 * P3-M2-T4: AI action permission enforcement tests.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/ai_permissions.php';
require_once dirname(__DIR__) . '/api/ai_helpers.php';

test_reset_failures();
echo "AI permissions tests\n\n";

$viewer = ['id' => 'ai-test-viewer', 'type' => 'ai', 'role' => 'viewer'];
$editor = ['id' => 'ai-test-editor', 'type' => 'ai', 'role' => 'editor'];
$admin = ['id' => 'ai-test-admin', 'type' => 'ai', 'role' => 'admin'];

// --- Action permission map ---
test_assert('viewer can list_collections', birkly_ai_user_can_action($viewer, 'list_collections'));
test_assert('viewer can read_entry', birkly_ai_user_can_action($viewer, 'read_entry'));
test_assert('viewer cannot create_entry', !birkly_ai_user_can_action($viewer, 'create_entry'));
test_assert('viewer cannot delete_entry', !birkly_ai_user_can_action($viewer, 'delete_entry'));
test_assert('viewer cannot update_settings', !birkly_ai_user_can_action($viewer, 'update_settings'));
test_assert('viewer cannot list_users', !birkly_ai_user_can_action($viewer, 'list_users'));

test_assert('editor can create_entry', birkly_ai_user_can_action($editor, 'create_entry'));
test_assert('editor can update_entry', birkly_ai_user_can_action($editor, 'update_entry'));
test_assert('editor can delete_entry', birkly_ai_user_can_action($editor, 'delete_entry'));
test_assert('editor cannot update_settings', !birkly_ai_user_can_action($editor, 'update_settings'));

test_assert('admin can create_entry', birkly_ai_user_can_action($admin, 'create_entry'));
test_assert('admin can update_settings', birkly_ai_user_can_action($admin, 'update_settings'));
test_assert('admin can list_users', birkly_ai_user_can_action($admin, 'list_users'));

test_assert('unknown action denied', !birkly_ai_user_can_action($admin, 'drop_database'));

$denied = birkly_ai_enforce_action_permission($viewer, 'create_entry');
test_assert('enforce returns error for viewer create', is_array($denied) && ($denied['success'] ?? true) === false);

$allowed = birkly_ai_enforce_action_permission($editor, 'list_entries');
test_assert('enforce returns null when allowed', $allowed === null);

test_assert('MCP get_collection maps to read', birkly_ai_user_can_action($viewer, 'get_collection'));
test_assert('MCP create_entry blocked for viewer', !birkly_ai_user_can_action($viewer, 'create_entry'));

// --- ACL helpers (P3-M2-T2) ---
$ownerId = 'user-owner';
$otherId = 'user-other';
$adminUser = ['id' => 'user-admin', 'role' => 'admin'];

$privateConn = ['id' => 'ai-1', 'type' => 'ai', 'created_by' => $ownerId, 'shared' => false];
$sharedAllConn = ['id' => 'ai-2', 'type' => 'ai', 'created_by' => $ownerId, 'shared' => true, 'shared_type' => 'all'];
$sharedSpecificConn = [
    'id' => 'ai-3', 'type' => 'ai', 'created_by' => $ownerId,
    'shared_type' => 'specific', 'allowed_users' => [$otherId]
];

test_assert('normalize shared_type from boolean true', birkly_ai_normalize_shared_type(['shared' => true]) === 'all');
test_assert('normalize shared_type from legacy string', birkly_ai_normalize_shared_type(['shared' => 'specific']) === 'specific');
test_assert('normalize explicit shared_type', birkly_ai_normalize_shared_type(['shared_type' => 'none']) === 'none');

test_assert('owner can access private connection', birkly_ai_user_can_access_connection($privateConn, $ownerId));
test_assert('non-owner denied private connection', !birkly_ai_user_can_access_connection($privateConn, $otherId));
test_assert('any user can access shared-all connection', birkly_ai_user_can_access_connection($sharedAllConn, $otherId));
test_assert('specific user can access specific-shared connection', birkly_ai_user_can_access_connection($sharedSpecificConn, $otherId));
test_assert('non-listed user denied specific-shared connection', !birkly_ai_user_can_access_connection($sharedSpecificConn, 'user-stranger'));

test_assert('owner can manage connection', birkly_ai_user_can_manage_connection($privateConn, $ownerId));
test_assert('non-owner cannot manage connection', !birkly_ai_user_can_manage_connection($privateConn, $otherId));
test_assert('admin can manage others connection', birkly_ai_user_can_manage_connection($privateConn, 'user-admin', $adminUser));

exit(test_finish('ai_permissions_test'));
