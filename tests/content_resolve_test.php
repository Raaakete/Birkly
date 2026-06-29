<?php
/**
 * Content resolution tests for AI/MCP read actions.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../api/storage.php';
require_once __DIR__ . '/../core/content_resolve.php';
require_once __DIR__ . '/../core/ai_tool_registry.php';
require_once __DIR__ . '/../api/ai_read_actions.php';

test_reset_failures();
echo "Content resolve tests\n\n";

$root = dirname(__DIR__);
$collectionsRoot = $root . '/content/collections';
$testDir = 'col_test_resolve_' . getmypid();
$testPath = $collectionsRoot . '/' . $testDir;
$backupExists = is_dir($testPath);
$testSlug = 'resolve_test_' . getmypid();
$testName = 'Resolve Test ' . getmypid();

@mkdir($testPath . '/entries', 0755, true);
file_put_contents($testPath . '/collection.json', json_encode([
    'id' => 'col-resolve-test-' . getmypid(),
    'slug' => $testSlug,
    'name' => $testName,
    'title' => $testName,
]));
file_put_contents($testPath . '/entries/entry1.json', json_encode([
    'id' => 'entry1',
    'slug' => 'welcome',
    'title' => 'Welcome',
    'status' => 'published',
    'body' => str_repeat('x', 50000),
]));

$storage = new Storage($root . '/content');

try {
    test_assert('resolve by slug', birkly_content_resolve_collection_dir($storage, $testSlug) === $testDir);
    test_assert('resolve by display name', birkly_content_resolve_collection_dir($storage, $testName) === $testDir);
    test_assert('resolve by id', birkly_content_resolve_collection_dir($storage, 'col-resolve-test-' . getmypid()) === $testDir);
    test_assert('unknown collection returns null', birkly_content_resolve_collection_dir($storage, 'missing') === null);

    $adminUser = ['id' => 'user-admin', 'role' => 'admin', 'permissions' => ['*']];
    $byName = executeReadAction([
        'type' => 'list_entries',
        'data' => ['collection' => $testName, 'summary' => true],
    ], $adminUser);
    test_assert('list_entries by display name succeeds', ($byName['success'] ?? false) === true);
    test_assert('list_entries returns canonical slug', ($byName['data']['collection'] ?? '') === $testSlug);
    test_assert('list_entries summary omits large body', (function ($result) {
        $entry = $result['data']['entries'][0] ?? [];
        return isset($entry['title']) && !isset($entry['body']);
    })($byName));

    $mcpDispatch = birkly_ai_dispatch_tool_call('list_entries', ['collection' => $testName], $adminUser, 'mcp');
    test_assert('mcp dispatch succeeds for display name', !($mcpDispatch['isError'] ?? false));
    test_assert('mcp dispatch returns summary json', str_contains($mcpDispatch['content'][0]['text'] ?? '', 'welcome'));
} finally {
    if (!$backupExists) {
        @unlink($testPath . '/entries/entry1.json');
        @rmdir($testPath . '/entries');
        @unlink($testPath . '/collection.json');
        @rmdir($testPath);
    }
}

exit(test_finish('content_resolve_test'));
