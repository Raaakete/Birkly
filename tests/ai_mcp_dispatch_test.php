<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/ai_tool_registry.php';

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

$writeSummary = birkly_ai_mcp_tool_success_summary('write_project_file', ['path' => 'css/style.css'], [
    'success' => true,
    'data' => ['path' => 'css/style.css', 'written' => true],
]);
assert_true(
    'write summary mentions path',
    str_contains($writeSummary, 'SUCCESS') && str_contains($writeSummary, 'css/style.css')
);

$entrySummary = birkly_ai_mcp_tool_success_summary('create_entry', [
    'collection' => 'blog',
    'status' => 'published',
], [
    'success' => true,
    'data' => [
        'entry_id' => 'abc123',
        'entry' => ['status' => 'published'],
    ],
]);
assert_true(
    'entry summary mentions collection and status',
    str_contains($entrySummary, 'blog') && str_contains($entrySummary, 'published')
);

exit($failures > 0 ? 1 : 0);
