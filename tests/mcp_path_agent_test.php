<?php
/**
 * Per-agent MCP URL ↔ OAuth token matching.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../core/mcp_protocol.php';
require_once __DIR__ . '/../core/mcp_endpoints.php';
require_once __DIR__ . '/../core/ai_agents.php';

test_reset_failures();
echo "MCP path agent match tests\n\n";

$personal = birkly_ai_agents_ensure_personal();
$endpointId = (string) ($personal['mcp_endpoint_id'] ?? '');
$actor = [
    'id' => $endpointId,
    'type' => 'mcp_endpoint',
    'role' => 'admin',
];

test_assert(
    'personal path matches linked endpoint token',
    birkly_mcp_actor_matches_path_agent($actor, BIRKLY_AI_AGENT_PERSONAL_ID)
);
test_assert(
    'generic mcp path allows any valid token',
    birkly_mcp_actor_matches_path_agent($actor, null)
);
test_assert(
    'wrong agent path rejects token',
    !birkly_mcp_actor_matches_path_agent($actor, 'ai_agent_deadbeef00000000')
);

exit(test_finish('mcp_path_agent_test'));
