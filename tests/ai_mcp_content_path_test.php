<?php

declare(strict_types=1);

/**
 * MCP write actions must resolve collections regardless of PHP cwd.
 * Regression: relative ../content broke create_entry when cwd was not api/.
 */

require_once dirname(__DIR__) . '/api/ai_helpers.php';
require_once dirname(__DIR__) . '/api/ai_approvals.php';

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

$root = dirname(__DIR__);
$testSlug = 'mcp-path-test-' . bin2hex(random_bytes(4));
$collectionDir = $root . '/content/collections/' . $testSlug;

if (!is_dir($collectionDir)) {
    mkdir($collectionDir, 0755, true);
    mkdir($collectionDir . '/entries', 0755, true);
    file_put_contents(
        $collectionDir . '/collection.json',
        json_encode([
            'name' => 'MCP Path Test',
            'slug' => $testSlug,
            'created_at' => date('c'),
            'updated_at' => date('c'),
            'entry_method' => 'admin_only',
        ], JSON_PRETTY_PRINT)
    );
}

$originalCwd = getcwd();
chdir(sys_get_temp_dir());

$storage = birkly_ai_content_storage();
$resolved = birkly_content_resolve_collection_dir_name($storage, $testSlug);
assert_true(
    'content storage resolves collection from non-api cwd',
    $resolved === $testSlug,
    'expected ' . $testSlug . ', got ' . (string) $resolved
);

$aiUser = ['id' => 'ai_agent_test', 'role' => 'admin'];
$result = executeCreateEntry([
    'collection' => $testSlug,
    'title' => 'MCP cwd regression entry',
    'status' => 'published',
], $aiUser, null);

assert_true(
    'executeCreateEntry succeeds when cwd is not api/',
    ($result['success'] ?? false) === true,
    (string) ($result['message'] ?? json_encode($result))
);

chdir($originalCwd);

// Cleanup test collection
if (is_dir($collectionDir)) {
    foreach (glob($collectionDir . '/entries/*.json') ?: [] as $entryFile) {
        unlink($entryFile);
    }
    @rmdir($collectionDir . '/entries');
    @unlink($collectionDir . '/collection.json');
    @rmdir($collectionDir);
}

exit($failures > 0 ? 1 : 0);
