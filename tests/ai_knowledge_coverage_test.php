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

// P5.6-M1: sub-collections MCP resource registered
test_assert('MCP resource registered: birkly://docs/sub-collections', in_array('birkly://docs/sub-collections', $uris, true));

// P5.6-M1-T1+T2: context pack covers sub-collections admin knowledge
$subCollectionPackTerms = [
    'sub_collections field'    => 'sub_collections',
    'disk layout'              => 'sub_entries',
    'admin URL: entry-editor'  => 'entry-editor',
    'admin URL: sub_collection param' => 'sub_collection=',
    'workflow matrix'          => 'entry-detail',
];
foreach ($subCollectionPackTerms as $label => $needle) {
    test_assert(
        "context pack sub-collections: $label",
        stripos($pack, $needle) !== false,
        "missing: $needle"
    );
}

// P5.6-M1-T3: list_sub_entries and read_sub_entry in tool registry
require_once dirname(__DIR__) . '/core/ai_tool_registry.php';
$definitions = birkly_ai_tool_definitions();
test_assert('tool registry has list_sub_entries', isset($definitions['list_sub_entries']));
test_assert('tool registry has read_sub_entry', isset($definitions['read_sub_entry']));
test_assert('list_sub_entries is read category', ($definitions['list_sub_entries']['category'] ?? '') === 'read');
test_assert('read_sub_entry is read category', ($definitions['read_sub_entry']['category'] ?? '') === 'read');

// P5.6-M1-T3: write tools present and approval-gated (write category)
test_assert('tool registry has create_sub_entry', isset($definitions['create_sub_entry']));
test_assert('tool registry has update_sub_entry', isset($definitions['update_sub_entry']));
test_assert('tool registry has delete_sub_entry', isset($definitions['delete_sub_entry']));
test_assert('create_sub_entry is write category', ($definitions['create_sub_entry']['category'] ?? '') === 'write');
test_assert('update_sub_entry is write category', ($definitions['update_sub_entry']['category'] ?? '') === 'write');
test_assert('delete_sub_entry is write category', ($definitions['delete_sub_entry']['category'] ?? '') === 'write');

// P5.6-M1-T3: permissions registered
require_once dirname(__DIR__) . '/core/ai_permissions.php';
$permMap = birkly_ai_action_permission_map();
test_assert('permissions: list_sub_entries', isset($permMap['list_sub_entries']));
test_assert('permissions: read_sub_entry', isset($permMap['read_sub_entry']));
test_assert('permissions: create_sub_entry', isset($permMap['create_sub_entry']));
test_assert('permissions: update_sub_entry', isset($permMap['update_sub_entry']));
test_assert('permissions: delete_sub_entry', isset($permMap['delete_sub_entry']));

// P5.6-M1-T5: read_birkly_docs sub-collections topic
$subColTopic = readBirklyDocsTopic('sub-collections');
test_assert('read_birkly_docs sub-collections loads', $subColTopic !== null && ($subColTopic['text'] ?? '') !== '');
test_assert('sub-collections topic mentions disk layout', stripos($subColTopic['text'] ?? '', 'sub_entries') !== false);
test_assert('sub-collections topic mentions admin URLs', stripos($subColTopic['text'] ?? '', 'entry-editor') !== false);
test_assert('sub-collections topic mentions list_sub_entries tool', stripos($subColTopic['text'] ?? '', 'list_sub_entries') !== false);

exit(test_finish('ai_knowledge_coverage_test'));
