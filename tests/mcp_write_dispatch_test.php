<?php
/**
 * MCP write dispatch — approvals are client-side only.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../core/ai_tool_registry.php';

test_reset_failures();
echo "MCP write dispatch tests\n\n";

$actor = [
    'id' => 'mcp_ep_write_test',
    'type' => 'mcp_endpoint',
    'role' => 'admin',
    'permissions' => ['*'],
    'require_approval' => true,
];

$result = birkly_ai_dispatch_tool_call(
    'create_collection',
    ['name' => 'MCP Test', 'slug' => 'mcp_test_' . getmypid()],
    $actor,
    'mcp'
);

$text = $result['content'][0]['text'] ?? '';
test_assert('mcp write does not queue birkly approval', !str_contains($text, 'queued in Birkly'));
test_assert('mcp write does not return approval id', !str_contains($text, 'approval ID'));

$chatActor = [
    'id' => 'ai_user_test_' . getmypid(),
    'type' => 'ai',
    'role' => 'admin',
    'permissions' => ['*'],
    'require_approval' => true,
];
$chatResult = birkly_ai_dispatch_tool_call(
    'create_collection',
    ['name' => 'Chat Test', 'slug' => 'chat_test_' . getmypid()],
    $chatActor,
    'chat'
);
$chatText = $chatResult['content'][0]['text'] ?? '';
test_assert('admin chat can still queue approval when enabled', str_contains($chatText, 'queued in Birkly') || str_contains($chatText, 'approval ID'));

exit(test_finish('mcp_write_dispatch_test'));
