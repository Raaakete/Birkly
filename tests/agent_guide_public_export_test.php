<?php
/**
 * Agent Guide public text export — map vs full, backward compatibility, llms-full route.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/ai_presence.php';
require_once $root . '/core/agent_guide_public_export.php';
require_once $root . '/core/ai_presence_discovery.php';
require_once $root . '/core/http_routes.php';

$failures = 0;

function ag_export_assert(bool $cond, string $label): void
{
    global $failures;
    if ($cond) {
        echo "OK  $label\n";
        return;
    }
    echo "FAIL $label\n";
    $failures++;
}

birkly_ai_presence_reset_for_tests();

$hostingBackup = is_file($root . '/settings/hosting.json') ? file_get_contents($root . '/settings/hosting.json') : null;
birkly_hosting_persist([
    'cms_base_url' => 'https://cms.example.test',
    'public_site_mode' => 'project',
    'public_site_url' => 'https://birkly.example.test',
]);

echo "==> Legacy published snapshot (no detail keys) stays map/summary\n";
$legacy = birkly_ai_presence_default_draft();
$legacy['identity']['official_name'] = 'Legacy Export Co';
$legacy['identity']['short_description'] = 'Legacy short.';
$legacy['publication_settings']['enabled'] = true;
$legacy['identity']['architecture'] = 'Use {for each entry in \'blog\'} on list pages.';
birkly_ai_presence_save_draft($legacy);
ag_export_assert((birkly_ai_presence_publish()['ok'] ?? false) === true, 'legacy publish');
$publishedPath = birkly_ai_presence_dir() . '/published.json';
$rawPublished = birkly_ai_presence_read_json($publishedPath);
if (is_array($rawPublished['publication_settings'] ?? null)) {
    unset(
        $rawPublished['publication_settings']['llms_txt_detail'],
        $rawPublished['publication_settings']['for_agents_detail'],
        $rawPublished['publication_settings']['llms_full_txt_enabled'],
    );
    birkly_ai_presence_write_json($publishedPath, $rawPublished);
}

$published = birkly_ai_presence_load_published();
$settings = birkly_agent_guide_public_export_output_settings($published);
ag_export_assert($settings['llms_txt_detail'] === 'map', 'defaults llms to map when key missing');
ag_export_assert($settings['for_agents_detail'] === 'summary', 'defaults for-agents to summary when key missing');

$mapBody = birkly_agent_guide_public_export_llms_txt_map($published, birkly_ai_presence_load_meta());
ag_export_assert(is_string($mapBody) && !str_contains($mapBody, '## Architecture'), 'map llms omits architecture section');
ag_export_assert(str_contains((string) $mapBody, '## For AI systems'), 'map llms has For AI systems');

echo "==> Full export modes\n";
$draft = birkly_ai_presence_load_draft();
$draft['publication_settings']['llms_txt_detail'] = 'full';
$draft['publication_settings']['for_agents_detail'] = 'full';
$draft['claims'] = [[
    'id' => 'c1',
    'status' => 'supported',
    'statement' => 'We ship honest Agent Guides.',
]];
$draft['sources_of_truth'] = [[
    'id' => 's1',
    'label' => 'Docs',
    'url' => 'https://birkly.example.test/docs',
    'notes' => 'Primary documentation.',
    'priority' => 90,
]];
birkly_ai_presence_save_draft($draft);
ag_export_assert((birkly_ai_presence_publish()['ok'] ?? false) === true, 'full mode publish');

$published = birkly_ai_presence_load_published();
$meta = birkly_ai_presence_load_meta();
$fullLlms = birkly_ai_presence_llms_txt_body();
ag_export_assert(is_string($fullLlms) && str_contains($fullLlms, 'honest Agent Guides'), 'full llms includes supported claim');
ag_export_assert(str_contains($fullLlms, '{for each entry in \'blog\'}'), 'full llms preserves template literal');
ag_export_assert(str_contains($fullLlms, 'Primary documentation.'), 'full llms includes source notes');

$md = birkly_ai_presence_for_agents_markdown();
ag_export_assert(is_string($md) && str_contains($md, '## Supported claims'), 'full for-agents.md has claims section');

echo "==> Redaction\n";
$redactDraft = birkly_ai_presence_load_draft();
$redactDraft['publication_settings']['redact_public_fields'] = ['identity.full_description'];
$redactDraft['identity']['full_description'] = 'SECRET_FULL';
$redactDraft['identity']['short_description'] = 'Public short';
birkly_ai_presence_save_draft($redactDraft);
birkly_ai_presence_publish();
$redactPub = birkly_ai_presence_load_published();
$redactLlms = birkly_ai_presence_llms_txt_body();
ag_export_assert(is_string($redactLlms) && !str_contains($redactLlms, 'SECRET_FULL'), 'redacted field absent from llms');
ag_export_assert(str_contains((string) $redactLlms, 'Public short'), 'non-redacted field present');

echo "==> Related section descriptions\n";
$rel = [
    'name' => 'Birkly Docs',
    'url' => 'https://docs.birkly.io/',
    'type' => 'documentation',
    'description' => 'The full documentation covers the content model.',
];
$relLine = birkly_agent_guide_public_export_relationship_markdown_line($rel);
ag_export_assert(str_contains($relLine, '(Documentation):'), 'relationship line includes type');
ag_export_assert(str_contains($relLine, 'full documentation'), 'relationship line includes description');

$pubWithRel = birkly_ai_presence_load_published();
$pubWithRel['relationships'] = [$rel];
$mdWithRel = birkly_agent_guide_public_export_for_agents_markdown($pubWithRel, birkly_ai_presence_load_meta());
ag_export_assert(is_string($mdWithRel) && str_contains($mdWithRel, 'full documentation'), 'for-agents.md exports relationship description');

echo "==> llms-full HTML extraction\n";
$sampleHtml = '<html><body>'
    . '<noscript class="birkly-agent-guide-discovery">JavaScript is disabled in your browser</noscript>'
    . '<header><nav>Menu skip</nav></header>'
    . '<main><h1>Product</h1><p>Real copy here.</p>'
    . '<pre><code>curl https://example.test</code></pre></main>'
    . str_repeat("\n\n\n", 10)
    . '</body></html>';
$pageText = birkly_agent_guide_public_export_html_page_text($sampleHtml);
ag_export_assert(!str_contains($pageText, 'JavaScript is disabled'), 'page text strips noscript discovery bar');
ag_export_assert(str_contains($pageText, 'Real copy here'), 'page text keeps main content');
ag_export_assert(str_contains($pageText, 'curl https://example.test'), 'page text keeps pre/code');
ag_export_assert(!str_contains($pageText, 'Menu skip'), 'page text drops nav/header');
ag_export_assert(!preg_match("/\n{3,}/", $pageText), 'page text has no 3+ blank line runs');

echo "==> llms-full route + snapshot\n";
$fullDraft = birkly_ai_presence_load_draft();
$fullDraft['publication_settings']['llms_full_txt_enabled'] = true;
birkly_ai_presence_save_draft($fullDraft);
$b = birkly_agent_guide_public_export_build_llms_full_snapshot($fullDraft, birkly_ai_presence_load_meta());
ag_export_assert(($b['ok'] ?? false) === true, 'llms-full snapshot build');
ag_export_assert(is_readable(birkly_ai_presence_dir() . '/llms-full.snapshot.md'), 'snapshot file written');

$routes = birkly_http_route_map();
ag_export_assert(isset($routes['/llms-full.txt']), 'llms-full route registered');
$map = birkly_ai_presence_discovery_url_map($fullDraft, birkly_ai_presence_load_meta());
ag_export_assert(!empty($map['llms_full_txt']), 'url map includes llms_full_txt when enabled');

birkly_ai_presence_reset_for_tests();
if ($hostingBackup !== null) {
    file_put_contents($root . '/settings/hosting.json', $hostingBackup);
} elseif (is_file($root . '/settings/hosting.json')) {
    @unlink($root . '/settings/hosting.json');
}

exit($failures > 0 ? 1 : 0);