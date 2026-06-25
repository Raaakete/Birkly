<?php
/**
 * P3-M4: AI tool registry tests.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/ai_permissions.php';
require_once dirname(__DIR__) . '/core/ai_tool_registry.php';

test_reset_failures();
echo "AI tool registry tests\n\n";

$viewer = ['id' => 'ai-viewer', 'type' => 'ai', 'role' => 'viewer'];
$editor = ['id' => 'ai-editor', 'type' => 'ai', 'role' => 'editor'];
$admin = ['id' => 'ai-admin', 'type' => 'ai', 'role' => 'admin'];

$definitions = birkly_ai_tool_definitions();
$permissionMap = birkly_ai_action_permission_map();

test_assert('every permission action has a tool definition', count(array_diff_key($permissionMap, $definitions)) === 0);

$viewerTools = birkly_ai_tools_for_user($viewer);
$editorTools = birkly_ai_tools_for_user($editor);
$adminTools = birkly_ai_tools_for_user($admin);

test_assert('viewer has list_collections tool', isset($viewerTools['list_collections']));
test_assert('viewer lacks create_entry tool', !isset($viewerTools['create_entry']));
test_assert('editor has create_entry tool', isset($editorTools['create_entry']));
test_assert('admin has update_settings tool', isset($adminTools['update_settings']));

test_assert('list_collections is read action', birkly_ai_is_read_action('list_collections'));
test_assert('create_entry is not read action', !birkly_ai_is_read_action('create_entry'));

$openAiTools = birkly_ai_tools_openai_format($viewer);
test_assert('openai format returns array', is_array($openAiTools) && count($openAiTools) > 0);
test_assert('openai tool has function name', isset($openAiTools[0]['function']['name']));

$mcpTools = birkly_ai_tools_mcp_format($viewer);
test_assert('mcp format returns array', is_array($mcpTools) && count($mcpTools) > 0);
test_assert('mcp tool has inputSchema', isset($mcpTools[0]['inputSchema']));

$toolCalls = [[
    'function' => [
        'name' => 'list_collections',
        'arguments' => '{}',
    ],
]];
$actions = birkly_ai_tool_calls_to_actions($toolCalls);
test_assert('tool_calls convert to actions', count($actions) === 1 && $actions[0]['type'] === 'list_collections');

$getCollectionCall = [[
    'function' => [
        'name' => 'get_collection',
        'arguments' => '{"slug":"blog"}',
    ],
]];
$mapped = birkly_ai_tool_calls_to_actions($getCollectionCall);
test_assert('get_collection maps to read_collection', $mapped[0]['type'] === 'read_collection');

$prompt = birkly_ai_tools_prompt_section($viewer);
test_assert('prompt section mentions list_collections', strpos($prompt, 'list_collections') !== false);

exit(test_finish('ai_tool_registry_test'));
