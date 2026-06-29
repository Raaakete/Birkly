<?php
/**
 * P5-M3 — AI agents tests.
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

try {
    test_assert('empty agent list', birkly_ai_agents_list() === []);

    $agent = birkly_ai_agents_create([
        'name' => 'Test Agent',
        'role' => 'editor',
        'description' => 'Helps with content',
        'require_approval' => true,
    ], 'user-test');

    test_assert('agent has id', !empty($agent['id']));
    test_assert('agent has mcp_endpoint_id', !empty($agent['mcp_endpoint_id']));
    test_assert('agent role is editor', ($agent['role'] ?? '') === 'editor');
    test_assert('connector URL uses /mcp', str_contains($agent['connector_url'] ?? '', '/mcp'));

    $endpoint = birkly_mcp_find_endpoint($agent['mcp_endpoint_id']);
    test_assert('linked endpoint exists', is_array($endpoint));
    test_assert('endpoint auth_mode oauth', ($endpoint['auth_mode'] ?? '') === 'oauth');

    $updated = birkly_ai_agents_update($agent['id'], [
        'name' => 'Renamed Agent',
        'role' => 'viewer',
    ]);
    test_assert('update returns agent', is_array($updated));
    test_assert('name updated', ($updated['name'] ?? '') === 'Renamed Agent');
    test_assert('role updated', ($updated['role'] ?? '') === 'viewer');

    $endpointAfter = birkly_mcp_find_endpoint($agent['mcp_endpoint_id']);
    test_assert('endpoint role synced', ($endpointAfter['role'] ?? '') === 'viewer');

    test_assert('revoke succeeds', birkly_ai_agents_revoke($agent['id']));
    $revoked = birkly_ai_agents_find($agent['id']);
    test_assert('agent status revoked', ($revoked['status'] ?? '') === 'revoked');

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
