<?php
/**
 * AI Presence storage unit tests (P46-M1).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/ai_presence.php';
require_once $root . '/core/ai_presence_links.php';
require_once $root . '/core/agent_guide_sources.php';

$failures = 0;

function assert_true(bool $cond, string $label): void
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

$default = birkly_ai_presence_default_draft();
assert_true(($default['schema_version'] ?? '') === BIRKLY_AI_PRESENCE_SCHEMA_VERSION, 'default draft schema_version');
assert_true(is_array($default['identity']), 'default identity object');

$errors = birkly_ai_presence_validate($default);
assert_true($errors === [], 'default draft validates');

$draft = $default;
$draft['identity']['official_name'] = 'Test Entity';
$draft['identity']['short_description'] = 'A test profile for Agent Guide.';
$draft['sources_of_truth'] = [[
    'id' => 'truth_test',
    'type' => 'website',
    'role' => 'narrative',
    'label' => 'Primary site',
    'url' => 'https://example.com/about',
]];
assert_true(birkly_ai_presence_save_draft($draft), 'save draft');

$loaded = birkly_ai_presence_load_draft();
assert_true(($loaded['identity']['official_name'] ?? '') === 'Test Entity', 'load draft roundtrip');
assert_true(count($loaded['sources_of_truth'] ?? []) === 1, 'sources_of_truth normalized');

$merged = birkly_ai_presence_merge_draft(['positioning' => ['best_known_for' => ['Quality']]]);
assert_true(in_array('Quality', $merged['positioning']['best_known_for'] ?? [], true), 'merge draft partial');

$nestedListMerge = birkly_ai_presence_merge_draft_arrays(
    ['best_known_for' => ['Quality']],
    ['best_known_for' => ['Quality', 'Service']]
);
assert_true(count($nestedListMerge['best_known_for'] ?? []) === 2, 'nested list merge with int keys');
assert_true(($nestedListMerge['best_known_for'][1] ?? '') === 'Service', 'nested list merge second item');

$manyProducts = [];
for ($i = 0; $i < 19; $i++) {
    $manyProducts[] = [
        'id' => 'prod_old_' . $i,
        'name' => 'Old product ' . $i,
        'type' => 'product',
    ];
}
birkly_ai_presence_save_draft(array_merge(birkly_ai_presence_load_draft(), ['products_services' => $manyProducts]));

$sevenProducts = [];
for ($i = 0; $i < 7; $i++) {
    $sevenProducts[] = [
        'id' => 'prod_clean_' . $i,
        'name' => 'Clean product ' . $i,
        'type' => 'product',
    ];
}
$replaced = birkly_ai_presence_merge_draft(['products_services' => $sevenProducts]);
assert_true(count($replaced['products_services'] ?? []) === 7, 'products_services replaced entirely on save_draft merge');
assert_true(($replaced['products_services'][0]['id'] ?? '') === 'prod_clean_0', 'products_services first item from patch');
assert_true(($replaced['products_services'][6]['id'] ?? '') === 'prod_clean_6', 'products_services seventh item from patch');
birkly_ai_presence_save_draft($replaced);
$roundtrip = birkly_ai_presence_load_draft();
assert_true(count($roundtrip['products_services'] ?? []) === 7, 'products_services save roundtrip keeps replacement count');

$merged = $roundtrip;

$completeness = birkly_ai_presence_completeness($merged);
assert_true($completeness['percent'] > 0, 'completeness score > 0');
assert_true($completeness['max'] === 100, 'completeness max 100');

$steps = birkly_ai_presence_next_steps($merged);
assert_true(is_array($steps), 'next steps array');

$draft['publication_settings']['enabled'] = true;
birkly_ai_presence_save_draft($draft);
$pub = birkly_ai_presence_publish();
assert_true(!empty($pub['ok']), 'publish succeeds when enabled');
assert_true((int) ($pub['version'] ?? 0) === 1, 'publish version 1');

$published = birkly_ai_presence_load_published();
assert_true($published !== null, 'published snapshot exists');
assert_true(($published['identity']['official_name'] ?? '') === 'Test Entity', 'published has official name');

$draft['identity']['official_name'] = 'Draft Only Change';
birkly_ai_presence_save_draft($draft);
$stillPublished = birkly_ai_presence_load_published();
assert_true(($stillPublished['identity']['official_name'] ?? '') === 'Test Entity', 'draft edits do not change published');

$exportBody = birkly_ai_presence_get_public_export_body();
assert_true(is_string($exportBody) && $exportBody !== '', 'public export body');

$preview = birkly_ai_presence_preview_query('What are you best known for?');
assert_true(($preview['answer'] ?? '') !== '', 'preview query returns answer');
assert_true(is_array($preview['sources']), 'preview query returns sources');

assert_true(!birkly_ai_presence_is_safe_external_url('http://127.0.0.1/secret'), 'blocks localhost URL');
assert_true(!birkly_ai_presence_is_safe_external_url('ftp://example.com'), 'blocks non-http URL');

$link = birkly_ai_presence_normalize_source_link([
    'role' => 'canonical_detail',
    'type' => 'public_page',
    'label' => 'Product page',
    'url' => 'https://example.com/product',
]);
assert_true(($link['url'] ?? '') === 'https://example.com/product', 'normalize source link');

$draftWithProduct = $merged;
$draftWithProduct['products_services'] = [[
    'id' => 'prod_test',
    'name' => 'Widget',
    'type' => 'product',
    'sources' => [$link],
    'public' => true,
]];
assert_true(birkly_ai_presence_product_has_grounded_source($draftWithProduct['products_services'][0]), 'product grounded with url');

$export = birkly_agent_guide_enrich_export($draftWithProduct);
assert_true(isset($export['usage_hints']), 'export includes usage_hints');
assert_true(is_array($export['products_services'][0]['sources'] ?? null), 'export product has sources');
assert_true(is_array($export['sources_of_truth'] ?? null), 'export includes sources_of_truth');

$sourceSuggestions = birkly_agent_guide_rule_source_suggestions(['identity' => ['website' => 'https://example.com']]);
assert_true(is_array($sourceSuggestions), 'agent guide source suggestions array');

$linkSuggestions = birkly_ai_presence_rule_link_suggestions($draftWithProduct);
assert_true(is_array($linkSuggestions), 'rule link suggestions array');

$schemaPath = birkly_ai_presence_schema_path();
assert_true(is_readable($schemaPath), 'schema file readable');
$schema = json_decode((string) file_get_contents($schemaPath), true);
assert_true(is_array($schema) && in_array(BIRKLY_AI_PRESENCE_SCHEMA_VERSION, $schema['properties']['schema_version']['enum'] ?? [], true), 'schema enum includes agent_guide.v1');

require_once $root . '/core/ai_presence_pro.php';
$draftWithClaim = $draftWithProduct;
$draftWithClaim['claims'] = [[
    'id' => 'claim_test',
    'statement' => 'We ship worldwide',
    'status' => 'supported',
    'evidence_ids' => ['ev_test'],
    'sources' => [],
]];
$draftWithClaim['evidence'] = [[
    'id' => 'ev_test',
    'type' => 'external_url',
    'title' => 'Shipping policy',
    'url' => 'https://example.com/shipping',
    'verification_status' => 'user_verified',
]];
$exportWithClaim = birkly_agent_guide_enrich_export($draftWithClaim);
$summaryView = birkly_ai_presence_public_view($exportWithClaim, 'summary');
assert_true(is_array($summaryView['sources_of_truth'] ?? null), 'summary view includes sources_of_truth');
assert_true(($summaryView['supported_claims'][0]['evidence_ids'][0] ?? '') === 'ev_test', 'summary claim includes evidence_ids');
$claimsView = birkly_ai_presence_public_view($exportWithClaim, 'claims');
assert_true(count($claimsView['evidence'] ?? []) === 1, 'claims view includes linked evidence');
$claimGraph = birkly_ai_presence_public_claim_graph($exportWithClaim, 'claim_test');
assert_true(is_array($claimGraph['evidence'][0] ?? null), 'claim graph resolves evidence URLs');
assert_true(($claimGraph['evidence'][0]['url'] ?? '') === 'https://example.com/shipping', 'claim graph evidence url');

$validateDraft = $draftWithClaim;
$validateDraft['claims'][0]['sources'] = [[
    'id' => 'claim_src_test',
    'type' => 'external_url',
    'label' => 'Policy',
    'url' => 'https://example.com/policy',
]];
$validateDraft['identity']['website'] = 'https://example.com';
$validateResults = birkly_ai_presence_validate_draft_links($validateDraft, true);
assert_true(is_array($validateResults), 'validate_draft_links mutate=true returns results');
assert_true(count($validateResults) >= 3, 'validate_draft_links checks evidence, claim, and product sources');
assert_true(($validateDraft['evidence'][0]['link_status'] ?? '') !== 'unknown', 'validate_draft_links mutates evidence link_status');
assert_true(($validateDraft['evidence'][0]['last_verified_at'] ?? '') !== '', 'validate_draft_links sets evidence last_verified_at');
assert_true(($validateDraft['claims'][0]['sources'][0]['link_status'] ?? '') !== 'unknown', 'validate_draft_links mutates claim source link_status');
assert_true(($validateDraft['products_services'][0]['sources'][0]['link_status'] ?? '') !== 'unknown', 'validate_draft_links mutates product source link_status');
$validateDraftDry = $validateDraft;
$dryResults = birkly_ai_presence_validate_draft_links($validateDraftDry, false);
assert_true(is_array($dryResults) && count($dryResults) >= 3, 'validate_draft_links mutate=false dry run');

birkly_ai_presence_reset_for_tests();

if ($failures > 0) {
    fwrite(STDERR, "$failures test(s) failed\n");
    exit(1);
}

echo "All ai_presence storage tests passed.\n";
