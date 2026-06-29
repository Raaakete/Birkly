<?php
/**
 * P5.1 — AI agents tests (personal + service).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/ai_agents.php';
require_once dirname(__DIR__) . '/core/mcp_endpoints.php';

test_reset_failures();
echo "AI agents tests\n\n";

$root = dirname(__DIR__);
$agentsFile = $root . '/settings/ai_agents.json';
$endpointsFile = $root . '/settings/mcp_endpoints.json';
$backupAgents = $agentsFile . '.bak.' . getmypid();
$backupEndpoints = $endpointsFile . '.bak.' . getmypid();

if (is_file($agentsFile)) {
    copy($agentsFile, $backupAgents);
} else {
    @unlink($backupAgents);
}
if (is_file($endpointsFile)) {
    copy($endpointsFile, $backupEndpoints);
} else {
    @unlink($backupEndpoints);
}

@unlink($agentsFile);
birkly_mcp_save_store(birkly_mcp_default_store());

try {
    test_assert('empty agent list', birkly_ai_agents_list() === []);

    $personal = birkly_ai_agents_ensure_personal();
    test_assert('personal agent created', ($personal['id'] ?? '') === BIRKLY_AI_AGENT_PERSONAL_ID);
    test_assert('personal agent type', ($personal['type'] ?? '') === 'personal');
    test_assert('personal connector URL', str_contains($personal['connector_url'] ?? '', '/mcp/agents/' . BIRKLY_AI_AGENT_PERSONAL_ID));

    $agent = birkly_ai_agents_create([
        'name' => 'Test Service Agent',
        'role' => 'editor',
        'description' => 'Helps with content',
        'visible_to_roles' => ['editor'],
        'require_approval' => true,
    ], 'user-test');

    test_assert('service agent type', ($agent['type'] ?? '') === 'service');
    test_assert('service visible_to_roles saved', in_array('editor', $agent['visible_to_roles'] ?? [], true));
    test_assert('connector URL is per-agent', str_contains($agent['connector_url'] ?? '', '/mcp/agents/' . $agent['id']));

    $adminUser = ['id' => 'user-admin', 'role' => 'admin', 'permissions' => ['*']];
    $editorUser = ['id' => 'user-editor', 'role' => 'editor', 'permissions' => ['read', 'write', 'manage_content']];

    $forAdmin = birkly_ai_agents_list_for_user($adminUser);
    test_assert('admin sees personal and service', count($forAdmin) >= 2);

    $forEditor = birkly_ai_agents_list_service_for_user($editorUser);
    test_assert('editor sees assigned service agent', count($forEditor) === 1);

    $updated = birkly_ai_agents_update($agent['id'], [
        'visible_to_roles' => ['admin'],
    ], $adminUser);
    test_assert('visibility update works', in_array('admin', $updated['visible_to_roles'] ?? [], true));

    $forEditorAfter = birkly_ai_agents_list_service_for_user($editorUser);
    test_assert('editor no longer sees admin-only service', count($forEditorAfter) === 0);

    test_assert('personal cannot be revoked', (function () {
        try {
            birkly_ai_agents_revoke(BIRKLY_AI_AGENT_PERSONAL_ID);
            return false;
        } catch (RuntimeException $e) {
            return str_contains($e->getMessage(), 'cannot be deleted');
        }
    })());

    test_assert('service revoke succeeds', birkly_ai_agents_revoke($agent['id']));

    require_once dirname(__DIR__) . '/core/mcp_protocol.php';
    test_assert(
        'agent id parsed from connector path',
        birkly_mcp_agent_id_from_path('/mcp/agents/' . BIRKLY_AI_AGENT_PERSONAL_ID) === BIRKLY_AI_AGENT_PERSONAL_ID
    );

} finally {
    if (is_file($backupAgents)) {
        copy($backupAgents, $agentsFile);
        unlink($backupAgents);
    } else {
        @unlink($agentsFile);
    }
    if (is_file($backupEndpoints)) {
        copy($backupEndpoints, $endpointsFile);
        unlink($backupEndpoints);
    } else {
        @unlink($endpointsFile);
    }
}

exit(test_finish('ai_agents_test'));
