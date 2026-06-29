<?php
/**
 * P5.1 — AI agent authorization and visibility tests.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/ai_agents.php';

test_reset_failures();
echo "AI agent authorization tests\n\n";

$adminUser = ['id' => 'user-admin', 'role' => 'admin', 'permissions' => ['*']];
$editorUser = ['id' => 'user-editor', 'role' => 'editor', 'permissions' => ['manage_content', 'read', 'write']];
$viewerUser = ['id' => 'user-viewer', 'role' => 'viewer', 'permissions' => ['read']];
$settingsManager = ['id' => 'user-settings', 'role' => 'editor', 'permissions' => ['manage_settings']];

$personalAgent = [
    'id' => BIRKLY_AI_AGENT_PERSONAL_ID,
    'type' => 'personal',
    'name' => 'Personal',
    'role' => 'viewer',
    'status' => 'active',
];
$adminService = [
    'id' => 'ai_agent_admin_svc',
    'type' => 'service',
    'name' => 'Admin Service',
    'role' => 'admin',
    'visible_to_roles' => ['admin'],
    'allowed_users' => [],
    'status' => 'active',
    'created_by' => 'user-admin',
];
$editorService = [
    'id' => 'ai_agent_editor_svc',
    'type' => 'service',
    'name' => 'Editor Service',
    'role' => 'editor',
    'visible_to_roles' => ['editor'],
    'allowed_users' => [],
    'status' => 'active',
    'created_by' => 'user-admin',
];
$assignedService = [
    'id' => 'ai_agent_assigned_svc',
    'type' => 'service',
    'name' => 'Assigned Service',
    'role' => 'admin',
    'visible_to_roles' => [],
    'allowed_users' => ['user-viewer'],
    'status' => 'active',
    'created_by' => 'user-admin',
];
$adminEndpoint = ['id' => 'mcp_ep_admin', 'name' => 'Admin Endpoint', 'role' => 'admin'];
$editorEndpoint = ['id' => 'mcp_ep_editor', 'name' => 'Editor Endpoint', 'role' => 'editor'];

test_assert('admin is admin', birkly_ai_agents_is_admin($adminUser));
test_assert('settings manager is admin', birkly_ai_agents_is_admin($settingsManager));
test_assert('editor is not admin', !birkly_ai_agents_is_admin($editorUser));

test_assert('editor can see personal agent', birkly_ai_user_can_see_agent($personalAgent, $editorUser));
test_assert('viewer can see personal agent', birkly_ai_user_can_see_agent($personalAgent, $viewerUser));
test_assert('editor can authorize personal agent', birkly_ai_user_can_authorize_agent($personalAgent, $editorEndpoint, $editorUser));

test_assert('admin can see admin-only service agent', birkly_ai_user_can_see_agent($adminService, $adminUser));
test_assert('editor cannot see admin-only service agent', !birkly_ai_user_can_see_agent($adminService, $editorUser));
test_assert('editor cannot authorize admin-only service agent', !birkly_ai_user_can_authorize_agent($adminService, $adminEndpoint, $editorUser));

test_assert('editor can see editor service agent', birkly_ai_user_can_see_agent($editorService, $editorUser));
test_assert('editor can authorize editor service agent', birkly_ai_user_can_authorize_agent($editorService, $editorEndpoint, $editorUser));

test_assert('viewer can see assigned admin service agent', birkly_ai_user_can_see_agent($assignedService, $viewerUser));
test_assert('viewer can authorize assigned admin service agent', birkly_ai_user_can_authorize_agent($assignedService, $adminEndpoint, $viewerUser));
test_assert('editor cannot see assigned-only service agent', !birkly_ai_user_can_see_agent($assignedService, $editorUser));

test_assert('personal consent mentions user role', str_contains(
    birkly_ai_agents_consent_summary($personalAgent, $editorEndpoint, $editorUser)['body'],
    'editor'
));
test_assert('service consent mentions agent role', str_contains(
    birkly_ai_agents_consent_summary($editorService, $editorEndpoint, $editorUser)['body'],
    'editor'
));

test_assert('effective role caps editor on admin agent', birkly_ai_effective_role('admin', 'editor') === 'editor');
test_assert('effective role keeps admin for admin user', birkly_ai_effective_role('admin', 'admin') === 'admin');

require_once dirname(__DIR__) . '/core/ai_permissions.php';

$usersDir = dirname(__DIR__) . '/users';
$backupUsers = [];
foreach (['user-editor', 'user-viewer'] as $testUserId) {
    $path = $usersDir . '/' . $testUserId . '.json';
    if (is_file($path)) {
        $backupUsers[$testUserId] = file_get_contents($path);
    }
}
@mkdir($usersDir, 0755, true);
file_put_contents($usersDir . '/user-editor.json', json_encode([
    'id' => 'user-editor',
    'email' => 'editor@test.local',
    'role' => 'editor',
    'permissions' => ['manage_content', 'read', 'write'],
]));
file_put_contents($usersDir . '/user-viewer.json', json_encode([
    'id' => 'user-viewer',
    'email' => 'viewer@test.local',
    'role' => 'viewer',
    'permissions' => ['read'],
]));

$personalActor = birkly_ai_agents_mcp_actor_for_token($editorEndpoint, $personalAgent, [
    'authorized_by' => 'user-editor',
    'authorized_role' => 'editor',
    'agent_type' => 'personal',
]);
test_assert('personal actor uses authorizer permissions', birkly_ai_user_can_action($personalActor, 'create_entry'));
test_assert('personal actor denies admin settings', !birkly_ai_user_can_action($personalActor, 'update_settings'));

$viewerActor = birkly_ai_agents_mcp_actor_for_token($editorEndpoint, $personalAgent, [
    'authorized_by' => 'user-viewer',
    'authorized_role' => 'viewer',
    'agent_type' => 'personal',
]);
test_assert('personal viewer actor read allowed', birkly_ai_user_can_action($viewerActor, 'list_collections'));
test_assert('personal viewer actor write denied', !birkly_ai_user_can_action($viewerActor, 'create_entry'));

$serviceActor = birkly_ai_agents_mcp_actor_for_token($adminEndpoint, $adminService, [
    'authorized_by' => 'user-editor',
    'authorized_role' => 'editor',
    'agent_type' => 'service',
]);
test_assert('service actor caps role for non-admin authorizer', ($serviceActor['role'] ?? '') === 'editor');

foreach ($backupUsers as $testUserId => $contents) {
    file_put_contents($usersDir . '/' . $testUserId . '.json', $contents);
}
foreach (['user-editor', 'user-viewer'] as $testUserId) {
    if (!isset($backupUsers[$testUserId]) && is_file($usersDir . '/' . $testUserId . '.json')) {
        unlink($usersDir . '/' . $testUserId . '.json');
    }
}

exit(test_finish('ai_agent_auth_test'));
