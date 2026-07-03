<?php
/**
 * P5.4-M3 — AI knowledge coverage gate (context pack + MCP topics + docs tool).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/api/ai_helpers.php';

test_reset_failures();
echo "AI knowledge coverage tests\n\n";

$pack = loadBirklyContextPack();
$requiredPackTerms = [
    'conditionals' => '{if',
    'single-entry' => '{entry',
    'sub-collections' => 'sub-collection',
    'admin templating' => '{admin_url}',
    'fieldtypes' => 'fieldtypes',
    'plugins' => 'birkly_is_plugin_active',
    'API' => '/api/',
    'security' => 'Never expose',
    'MCP' => 'MCP',
    'filters' => 'truncate',
];

foreach ($requiredPackTerms as $label => $needle) {
    test_assert(
        "context pack mentions $label",
        stripos($pack, $needle) !== false,
        "missing: $needle"
    );
}

$layoutPackTerms = [
    'content-editor-table' => 'content-editor-table',
    'content-editor-grid' => 'content-editor-grid',
    'layout studio' => 'Layout panel',
    'grid-template-columns' => 'grid-template-columns',
    'editor metadata stripped' => 'data-ce-*',
];

foreach ($layoutPackTerms as $label => $needle) {
    test_assert(
        "context pack layout: $label",
        stripos($pack, $needle) !== false,
        "missing layout term: $needle"
    );
}

$templating = readBirklyDocsTopic('templating');
test_assert('read_birkly_docs templating loads', $templating !== null && ($templating['text'] ?? '') !== '');
test_assert('templating topic mentions endfor', stripos($templating['text'] ?? '', '{endfor}') !== false || stripos($templating['text'] ?? '', 'endfor') !== false);

$adminTopic = readBirklyDocsTopic('admin');
test_assert('read_birkly_docs admin loads', $adminTopic !== null);

require_once dirname(__DIR__) . '/core/mcp_protocol.php';
$resources = birkly_mcp_handle_resources_list();
$uris = array_column($resources['resources'] ?? [], 'uri');
foreach (['birkly://docs/templating', 'birkly://docs/admin', 'birkly://docs/plugins', 'birkly://docs/ai'] as $uri) {
    test_assert("MCP resource registered: $uri", in_array($uri, $uris, true));
}

exit(test_finish('ai_knowledge_coverage_test'));
