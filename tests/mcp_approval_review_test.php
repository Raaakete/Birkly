<?php
/**
 * MCP approval queue visibility tests.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../api/storage.php';
require_once __DIR__ . '/../api/ai_helpers.php';
require_once __DIR__ . '/../core/mcp_endpoints.php';
require_once __DIR__ . '/../core/ai_agents.php';

test_reset_failures();
echo "MCP approval review tests\n\n";

$endpoint = birkly_mcp_create_endpoint([
    'name' => 'Approval Review Test',
    'role' => 'editor',
    'require_approval' => true,
    'auth_mode' => 'oauth',
], 'test');

$personal = birkly_ai_agents_ensure_personal();
$personalEndpointId = (string) ($personal['mcp_endpoint_id'] ?? '');

$authorizer = ['id' => 'user-editor-1', 'role' => 'editor', 'email' => 'editor@example.com'];
$admin = ['id' => 'user-admin-1', 'role' => 'admin', 'email' => 'admin@example.com'];
$stranger = ['id' => 'user-viewer-9', 'role' => 'viewer', 'email' => 'viewer@example.com'];

$personalApproval = [
    'id' => 'approval_test_personal',
    'ai_user_id' => $personalEndpointId,
    'action_type' => 'create_entry',
    'action_data' => ['collection' => 'blog'],
    'authorized_by' => $authorizer['id'],
    'source' => 'mcp',
    'status' => 'pending',
];

$serviceApproval = [
    'id' => 'approval_test_service',
    'ai_user_id' => $endpoint['id'],
    'action_type' => 'create_entry',
    'action_data' => ['collection' => 'blog'],
    'authorized_by' => $authorizer['id'],
    'source' => 'mcp',
    'status' => 'pending',
];

test_assert(
    'personal MCP authorizer can review own approval',
    birkly_ai_user_can_review_approval($personalApproval, $authorizer['id'], $authorizer)
);
test_assert(
    'admin can review personal MCP approval',
    birkly_ai_user_can_review_approval($personalApproval, $admin['id'], $admin)
);
test_assert(
    'unrelated user cannot review personal MCP approval',
    !birkly_ai_user_can_review_approval($personalApproval, $stranger['id'], $stranger)
);
test_assert(
    'admin can review service MCP approval',
    birkly_ai_user_can_review_approval($serviceApproval, $admin['id'], $admin)
);
test_assert(
    'legacy outbound approval still requires ai user record',
    !birkly_ai_user_can_review_approval([
        'ai_user_id' => 'missing-ai-user',
        'id' => 'approval_legacy',
    ], $admin['id'], $admin)
);

birkly_mcp_revoke_endpoint($endpoint['id']);

exit(test_finish('mcp_approval_review_test'));
