<?php
/**
 * P3-M2-T4: AI action permission enforcement tests.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/ai_permissions.php';

test_reset_failures();
echo "AI permissions tests\n\n";

$viewer = ['id' => 'ai-test-viewer', 'type' => 'ai', 'role' => 'viewer'];
$editor = ['id' => 'ai-test-editor', 'type' => 'ai', 'role' => 'editor'];
$admin = ['id' => 'ai-test-admin', 'type' => 'ai', 'role' => 'admin'];

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

exit(test_finish('ai_permissions_test'));
