<?php
/**
 * MCP entries.bulk CSRF bypass and entry id payload keys.
 * Run: php tests/cms_mcp_bulk_entries_test.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/cms_mcp_request.php';

function assert_true(string $label, bool $condition): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "{$label}: PASS\n");
}

$GLOBALS['__CMS_MCP_DISPATCH'] = true;
assert_true('MCP dispatch bypasses CSRF', cms_mcp_csrf_valid('', 'bulk_operation'));

unset($GLOBALS['__CMS_MCP_DISPATCH']);
assert_true('Normal path rejects empty CSRF', !cms_mcp_csrf_valid('', 'bulk_operation'));

$payload = [
    'collection' => 'newsletter',
    'action' => 'delete',
    'ids' => ['abc123'],
];
$GLOBALS['__CMS_MCP_JSON_INPUT'] = $payload;
$read = cms_mcp_read_json_body();
assert_true('MCP json input readable', ($read['ids'][0] ?? '') === 'abc123');
unset($GLOBALS['__CMS_MCP_JSON_INPUT']);

fwrite(STDOUT, "OK\n");