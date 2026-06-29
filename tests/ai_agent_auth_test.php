<?php
/**
 * AI agent OAuth authorization policy tests.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/ai_agents.php';

test_reset_failures();
echo "AI agent authorization tests\n\n";

$adminUser = ['id' => 'user-admin', 'role' => 'admin', 'permissions' => ['*']];
$editorUser = ['id' => 'user-editor', 'role' => 'editor', 'permissions' => ['manage_content', 'read', 'write']];
$viewerUser = ['id' => 'user-viewer', 'role' => 'viewer', 'permissions' => ['read']];
$settingsManager = ['id' => 'user-settings', 'role' => 'editor', 'permissions' => ['manage_settings']];

$adminAgent = ['id' => 'ai_agent_admin', 'name' => 'Admin Agent', 'role' => 'admin', 'created_by' => 'user-admin'];
$editorAgent = ['id' => 'ai_agent_editor', 'name' => 'Editor Agent', 'role' => 'editor', 'created_by' => 'user-admin'];
$adminEndpoint = ['id' => 'mcp_ep_admin', 'name' => 'Admin Endpoint', 'role' => 'admin'];
$editorEndpoint = ['id' => 'mcp_ep_editor', 'name' => 'Editor Endpoint', 'role' => 'editor'];

test_assert('admin can authorize admin agent', birkly_ai_user_can_authorize_agent($adminAgent, $adminEndpoint, $adminUser));
test_assert('editor cannot authorize admin agent', !birkly_ai_user_can_authorize_agent($adminAgent, $adminEndpoint, $editorUser));
test_assert('viewer cannot authorize editor agent', !birkly_ai_user_can_authorize_agent($editorAgent, $editorEndpoint, $viewerUser));
test_assert('editor can authorize editor agent', birkly_ai_user_can_authorize_agent($editorAgent, $editorEndpoint, $editorUser));
test_assert('settings manager cannot authorize admin agent', !birkly_ai_user_can_authorize_agent($adminAgent, $adminEndpoint, $settingsManager));
test_assert('settings manager can authorize editor agent', birkly_ai_user_can_authorize_agent($editorAgent, $editorEndpoint, $settingsManager));

test_assert('effective role caps editor on admin agent', birkly_ai_effective_role('admin', 'editor') === 'editor');
test_assert('effective role keeps admin for admin user', birkly_ai_effective_role('admin', 'admin') === 'admin');
test_assert('effective role keeps viewer for viewer agent', birkly_ai_effective_role('viewer', 'editor') === 'viewer');

exit(test_finish('ai_agent_auth_test'));
