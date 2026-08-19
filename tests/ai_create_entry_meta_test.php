<?php

declare(strict_types=1);

$testRoot = sys_get_temp_dir() . '/birkly-create-entry-meta-' . uniqid();
mkdir($testRoot . '/content/collections/mcp-probe', 0755, true);
mkdir($testRoot . '/content/collections/mcp-probe/entries', 0755, true);
file_put_contents($testRoot . '/content/collections/mcp-probe/collection.json', json_encode([
    'name' => 'MCP Probe',
    'slug' => 'mcp-probe',
    'entry_method' => 'admin_only',
], JSON_PRETTY_PRINT));
file_put_contents($testRoot . '/content/collections/mcp-probe/schema.json', json_encode([
    'fields' => [
        ['name' => 'title', 'type' => 'text', 'label' => 'Title'],
        ['name' => 'display_name', 'type' => 'text', 'label' => 'Display name'],
        ['name' => 'is_public', 'type' => 'checkbox', 'label' => 'Public'],
    ],
], JSON_PRETTY_PRINT));

$GLOBALS['__birkly_project_root'] = $testRoot;
$GLOBALS['__HARU_INTERNAL_CALL'] = true;

require_once dirname(__DIR__) . '/core/ai_write_validation.php';
require_once dirname(__DIR__) . '/api/ai_approvals.php';

$failures = 0;

$merged = birkly_ai_merge_entry_write_meta_from_payload(
    [
        'collection' => 'mcp-probe',
        'title' => 'Probe Person',
        'status' => 'published',
        'data' => ['display_name' => 'Probe Person', 'is_public' => true],
    ],
    ['display_name' => 'Probe Person', 'is_public' => true]
);
assert_true('merge helper applies top-level title', ($merged['title'] ?? '') === 'Probe Person');
assert_true('merge helper applies top-level status', ($merged['status'] ?? '') === 'published');
assert_true('merge helper preserves nested fields', ($merged['display_name'] ?? null) === 'Probe Person');

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

$aiUser = ['id' => 'ai_test', 'role' => 'admin'];
$result = executeCreateEntry([
    'collection' => 'mcp-probe',
    'title' => 'Probe Person',
    'status' => 'published',
    'data' => [
        'display_name' => 'Probe Person',
        'is_public' => true,
    ],
], $aiUser, null);

assert_true('create_entry succeeds', ($result['success'] ?? false) === true, json_encode($result));
$entry = $result['data']['entry'] ?? [];
assert_true('top-level title applied', ($entry['title'] ?? '') === 'Probe Person');
assert_true('top-level status applied', ($entry['status'] ?? '') === 'published');
assert_true('slug derived from title', ($entry['slug'] ?? '') === 'probe-person');
assert_true('nested field preserved', ($entry['display_name'] ?? null) === 'Probe Person');

exit($failures > 0 ? 1 : 0);
