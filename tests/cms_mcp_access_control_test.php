<?php

declare(strict_types=1);

/**
 * MCP CMS admin: viewer cannot run write actions; path segments cannot traverse.
 */

require_once dirname(__DIR__) . '/core/cms_mcp_admin.php';

$failures = 0;

function assert_true(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '  ' . $detail . "\n";
        }
    }
}

$viewer = [
    'id' => 'viewer-mcp-test',
    'role' => 'viewer',
    'email' => 'viewer@test.local',
    'name' => 'Viewer',
];

$meta = cms_mcp_admin_action_meta();
$writeDenied = 0;
$writeChecked = 0;
foreach ($meta as $action => $row) {
    if (empty($row['write'])) {
        continue;
    }
    $writeChecked++;
    $result = cms_mcp_dispatch_admin($action, [], $viewer);
  if (($result['status'] ?? '') === 'error' && str_contains((string) ($result['message'] ?? ''), 'Permission denied')) {
        $writeDenied++;
    }
}
assert_true(
    'viewer denied all write-flagged catalog actions',
    $writeChecked > 0 && $writeDenied === $writeChecked,
    "denied $writeDenied of $writeChecked"
);

assert_true(
    'viewer cannot marketplace.install',
    ($viewerInstall = cms_mcp_dispatch_admin('marketplace.install', ['slug' => 'commerce'], $viewer))['status'] === 'error'
    && str_contains((string) ($viewerInstall['message'] ?? ''), 'Permission denied')
);

assert_true(
    'viewer cannot project_git.connect',
    ($gitConnect = cms_mcp_dispatch_admin('project_git.connect', [], $viewer))['status'] === 'error'
    && str_contains((string) ($gitConnect['message'] ?? ''), 'Permission denied')
);

$readOk = cms_mcp_dispatch_admin('search.admin_search', ['query' => 'test', 'limit' => 1], $viewer);
assert_true(
    'viewer may still run read-only admin search',
    ($readOk['status'] ?? '') === 'ok'
);

$admin = [
    'id' => 'admin-mcp-test',
    'role' => 'admin',
    'email' => 'admin@test.local',
    'name' => 'Admin',
];

$traversal = cms_mcp_dispatch_admin('content.set_entry_order', [
    'collection' => '../outside',
    'order' => ['a'],
], $admin);
assert_true(
    'set_entry_order rejects traversal collection slug',
    ($traversal['status'] ?? '') === 'error'
    && str_contains((string) ($traversal['message'] ?? ''), 'Invalid collection slug')
);

$subTraversal = cms_mcp_dispatch_admin('sub_entries.set_order', [
    'collection' => 'blog',
    'entry_id' => '../etc',
    'sub_collection' => 'comments',
    'order' => ['x'],
], $admin);
assert_true(
    'sub_entries.set_order rejects traversal entry_id',
    ($subTraversal['status'] ?? '') === 'error'
    && str_contains((string) ($subTraversal['message'] ?? ''), 'Invalid entry id')
);

$bulkTraversal = cms_mcp_dispatch_admin('sub_entries.bulk', [
    'collection' => 'blog',
    'entry_id' => '../etc',
    'sub_collection' => 'comments',
    'bulk_action' => 'delete',
    'sub_entry_ids' => ['x'],
], $admin);
assert_true(
    'sub_entries.bulk rejects traversal entry_id',
    ($bulkTraversal['status'] ?? '') === 'error'
    && str_contains((string) ($bulkTraversal['message'] ?? ''), 'Invalid entry id')
);

$countTraversal = cms_mcp_dispatch_admin('sub_entries.count', [
    'collection' => 'blog',
    'entry_id' => '../etc',
    'sub_collection' => 'comments',
], $admin);
assert_true(
    'sub_entries.count rejects traversal entry_id',
    ($countTraversal['status'] ?? '') === 'error'
    && str_contains((string) ($countTraversal['message'] ?? ''), 'Invalid entry id')
);

require_once dirname(__DIR__) . '/core/ai_tool_registry.php';
$toolDenied = birkly_ai_dispatch_tool_call('cms_admin_action', [
    'action' => 'entries.save',
    'payload' => [],
], $viewer, 'mcp');
$toolText = $toolDenied['content'][0]['text'] ?? '';
assert_true(
    'cms_admin_action tool denies viewer before write dispatch',
    !empty($toolDenied['isError']) && str_contains($toolText, 'Permission denied')
);

assert_true(
    'manage_plugins supersets exclude read',
    !in_array('read', cms_mcp_permission_supersets('manage_plugins'), true)
);

exit($failures > 0 ? 1 : 0);