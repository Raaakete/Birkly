<?php
/**
 * P6.5-M5 — Plugin docs shelf, help manifest merge, AI/MCP integration.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/plugins.php';

test_reset_failures();
echo "Plugin docs shelf tests\n\n";

// --- Registry API ---

test_assert('register_plugin_docs function exists', function_exists('register_plugin_docs'));
test_assert('get_active_plugin_docs function exists', function_exists('get_active_plugin_docs'));
test_assert('birkly_build_help_manifest function exists', function_exists('birkly_build_help_manifest'));

$tmpPluginId = 'docs_shelf_test_' . getmypid();
$tmpDir = sys_get_temp_dir() . '/birkly-docs-test-' . getmypid();
mkdir($tmpDir . '/docs', 0755, true);
file_put_contents($tmpDir . '/docs/ai-context.md', '# Test AI doc\n\nfulfillment purchase_options');
file_put_contents($tmpDir . '/docs/help-overview.md', '# Test Help\n\nOverview content');
file_put_contents($tmpDir . '/docs/help-sub.md', '# Sub topic\n\nFieldtype help');

global $birkly_plugins;
$birkly_plugins[$tmpPluginId] = [
    'id' => $tmpPluginId,
    'path' => $tmpDir,
    'active' => true,
    'name' => 'Docs Test Plugin',
];

$registered = register_plugin_docs($tmpPluginId, [
    'title' => 'Docs Test',
    'summary' => 'Test plugin for docs shelf unit tests.',
    'docs' => [
        'ai' => $tmpDir . '/docs/ai-context.md',
        'help' => $tmpDir . '/docs/help-overview.md',
        'help_topics' => [
            ['id' => 'test-sub', 'title' => 'Sub topic', 'file' => $tmpDir . '/docs/help-sub.md'],
        ],
        'public_path' => '06-extensibility/plugins/test.html',
    ],
    'mcp_topic_slug' => 'docs_test_plugin',
    'keywords' => ['test', 'docs'],
]);

test_assert('register_plugin_docs succeeds for active plugin', $registered === true);

$all = get_all_plugin_docs();
test_assert('get_all_plugin_docs includes test plugin', isset($all[$tmpPluginId]));

$active = get_active_plugin_docs();
test_assert('get_active_plugin_docs includes test plugin', isset($active[$tmpPluginId]));

$inactive = get_plugin_docs($tmpPluginId);
test_assert('get_plugin_docs returns config', $inactive !== null && ($inactive['title'] ?? '') === 'Docs Test');

$birkly_plugins[$tmpPluginId]['active'] = false;
test_assert('get_plugin_docs null when inactive', get_plugin_docs($tmpPluginId) === null);
test_assert('get_active_plugin_docs excludes inactive', !isset(get_active_plugin_docs()[$tmpPluginId]));

$birkly_plugins[$tmpPluginId]['active'] = true;

$aiText = read_plugin_doc_markdown($tmpPluginId, 'ai');
test_assert('read_plugin_doc_markdown ai file', $aiText !== null && stripos($aiText, 'fulfillment') !== false);

$subText = read_plugin_doc_markdown($tmpPluginId, 'test-sub');
test_assert('read_plugin_doc_markdown help topic', $subText !== null && stripos($subText, 'Fieldtype') !== false);

// --- Help manifest merge ---

$manifest = birkly_build_help_manifest();
test_assert('help manifest has platform section', !empty($manifest['sections'][0]['id']) && $manifest['sections'][0]['id'] === 'platform');
test_assert('help manifest has plugins section', !empty($manifest['sections'][1]['id']) && $manifest['sections'][1]['id'] === 'plugins');

$coreTopics = array_filter($manifest['topics'] ?? [], fn($t) => ($t['source'] ?? '') === 'core');
test_assert('help manifest includes core topics', count($coreTopics) > 0);

$testPluginTopics = array_filter($manifest['topics'] ?? [], fn($t) => ($t['plugin_id'] ?? '') === $tmpPluginId);
test_assert('help manifest includes test plugin topics', count($testPluginTopics) >= 2);

$pluginTopic = null;
foreach ($manifest['topics'] as $t) {
    if (($t['id'] ?? '') === $tmpPluginId) {
        $pluginTopic = $t;
        break;
    }
}
test_assert('plugin topic marked section plugins', $pluginTopic !== null && ($pluginTopic['section'] ?? '') === 'plugins');
test_assert('plugin topic snapshot uses plugin_doc API', $pluginTopic !== null && strpos($pluginTopic['snapshot'] ?? '', '/api/plugin_doc.php') !== false);

// --- Commerce plugin integration (when present) ---

$birkly_plugins['commerce']['active'] = true;
if (is_file(dirname(__DIR__) . '/plugins/commerce/plugin.php')) {
    include_once dirname(__DIR__) . '/plugins/commerce/plugin.php';
}

$commerceDocs = get_plugin_docs('commerce');
if ($commerceDocs !== null) {
    test_assert('commerce registers plugin docs', ($commerceDocs['title'] ?? '') === 'Commerce');
    test_assert('commerce ai doc file exists', is_file($commerceDocs['docs']['ai'] ?? ''));
    test_assert('commerce help overview exists', is_file($commerceDocs['docs']['help'] ?? ''));

    $merged = birkly_build_help_manifest();
    $commerceTopics = array_filter($merged['topics'] ?? [], fn($t) => ($t['plugin_id'] ?? '') === 'commerce');
    test_assert('help manifest lists commerce when active', count($commerceTopics) >= 3);

    require_once dirname(__DIR__) . '/api/ai_helpers.php';
    $topic = readBirklyDocsTopic('commerce');
    test_assert('readBirklyDocsTopic commerce loads', $topic !== null && stripos($topic['text'] ?? '', 'purchase_options') !== false);

    require_once dirname(__DIR__) . '/core/mcp_protocol.php';
    $resources = birkly_mcp_handle_resources_list();
    $uris = array_column($resources['resources'] ?? [], 'uri');
    test_assert('MCP lists commerce plugin resource', in_array('birkly://docs/plugin/commerce', $uris, true));

    $pack = birkly_ai_context_pack_with_plugins();
    test_assert('AI context pack includes plugin summaries', stripos($pack, 'Active plugin extensions') !== false);
} else {
    test_assert('commerce docs skipped (plugin inactive or missing)', true);
}


// --- P7-M1: automation, user_management, events plugin docs ---

$pluginDocSlugs = [
    'automation' => ['plugin' => 'automation', 'needle' => 'workflow'],
    'user_management' => ['plugin' => 'user_management', 'needle' => 'registration'],
    'events' => ['plugin' => 'events', 'needle' => 'rsvp'],
];

foreach ($pluginDocSlugs as $slug => $meta) {
    $pluginId = $meta['plugin'];
    $pluginPath = dirname(__DIR__) . '/plugins/' . $pluginId . '/plugin.php';
    if (!is_file($pluginPath)) {
        test_assert("$pluginId plugin.php exists for docs", false);
        continue;
    }

    $birkly_plugins[$pluginId]['active'] = true;
    $registerDocs = dirname(__DIR__) . '/plugins/' . $pluginId . '/docs/register_docs.php';
    if (is_file($registerDocs)) {
        require $registerDocs;
    } elseif (is_file($pluginPath)) {
        include_once $pluginPath;
    }

    $docs = get_plugin_docs($pluginId);
    test_assert("$pluginId registers plugin docs", $docs !== null && ($docs['mcp_topic_slug'] ?? '') === $slug);
    test_assert("$pluginId ai doc file exists", is_file($docs['docs']['ai'] ?? ''));
    test_assert("$pluginId help overview exists", is_file($docs['docs']['help'] ?? ''));

    $bySlug = get_plugin_docs_by_topic_slug($slug);
    test_assert("get_plugin_docs_by_topic_slug($slug) resolves", $bySlug !== null && ($bySlug['plugin_id'] ?? '') === $pluginId);

    require_once dirname(__DIR__) . '/api/ai_helpers.php';
    $topic = readBirklyDocsTopic($slug);
    test_assert("readBirklyDocsTopic($slug) loads", $topic !== null && stripos($topic['text'] ?? '', $meta['needle']) !== false);

    test_assert("$pluginId help_topics registered", count($docs['docs']['help_topics'] ?? []) >= 2);
}


// --- API files exist ---

test_assert('api/help_manifest.php exists', is_file(dirname(__DIR__) . '/api/help_manifest.php'));
test_assert('api/plugin_doc.php exists', is_file(dirname(__DIR__) . '/api/plugin_doc.php'));

// --- help.js uses runtime manifest ---

$helpJs = (string) file_get_contents(dirname(__DIR__) . '/admin/js/help.js');
test_assert('help.js loads help_manifest API', strpos($helpJs, 'help_manifest.php') !== false);
test_assert('help.js renders platform nav section', strpos($helpJs, 'help-topic-nav-platform') !== false);
test_assert('help.js unified topic nav (no separate plugins section)', strpos($helpJs, 'help-topic-nav-plugins') === false);

// Cleanup temp plugin registration
unset($birkly_plugins[$tmpPluginId]);
global $birkly_plugin_docs;
unset($birkly_plugin_docs[$tmpPluginId]);
array_map('unlink', glob($tmpDir . '/docs/*'));
@rmdir($tmpDir . '/docs');
@rmdir($tmpDir);

exit(test_finish('plugin_docs_shelf_test'));
