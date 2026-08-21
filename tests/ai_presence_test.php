<?php
/**
 * AI Presence full test suite (P46-M14).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/ai_presence.php';

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

echo "==> Storage tests\n";
passthru('php ' . escapeshellarg($root . '/tests/ai_presence_storage_test.php'), $code);
if ($code !== 0) {
    $failures++;
}

birkly_ai_presence_reset_for_tests();

echo "==> Schema file\n";
assert_true(is_readable($root . '/schema/ai_presence.schema.json'), 'schema exists');

echo "==> Core PHP files syntax\n";
foreach ([
    'core/ai_presence.php',
    'core/ai_presence_pro.php',
    'core/agent_guide_sources.php',
    'api/ai_presence.php',
    'api/public/ai_presence.php',
    'api/well_known_brand_context.php',
    'api/well_known_agent_guide.php',
] as $rel) {
    exec('php -l ' . escapeshellarg($root . '/' . $rel) . ' 2>&1', $out, $lintCode);
    assert_true($lintCode === 0, "syntax $rel");
}

echo "==> HTTP route map\n";
require_once $root . '/core/http_routes.php';
$routes = birkly_http_route_map();
assert_true(isset($routes['/.well-known/brand-context.json']), 'legacy well-known route registered');
assert_true(isset($routes['/.well-known/agent-guide.json']), 'agent guide well-known route registered');
assert_true(isset($routes['/llms.txt']), 'llms.txt route registered');
assert_true(is_readable($root . '/api/llms_txt.php'), 'llms.txt handler exists');

echo "==> Admin template\n";
assert_true(is_readable($root . '/admin/templates/ai-presence.html'), 'admin template exists');
assert_true(is_readable($root . '/admin/js/ai-presence.js'), 'admin js exists');
assert_true(is_readable($root . '/admin/js/agent-guide-wizard.js'), 'agent guide wizard js exists');
assert_true(is_readable($root . '/admin/css/components/agent-guide-wizard.css'), 'agent guide wizard css exists');

echo "==> Publish state API\n";
birkly_ai_presence_reset_for_tests();
$draft = birkly_ai_presence_default_draft();
$draft['identity']['official_name'] = 'Test Co';
$draft['publication_settings']['enabled'] = true;
birkly_ai_presence_save_draft($draft);
$ps = birkly_ai_presence_publish_state($draft);
assert_true($ps['never_published'] === true, 'never_published before first publish');
assert_true($ps['is_live'] === false, 'not live before first publish');
$result = birkly_ai_presence_publish();
assert_true(($result['ok'] ?? false) === true, 'test publish ok');
$ps2 = birkly_ai_presence_publish_state();
assert_true($ps2['is_live'] === true, 'live after publish');
require_once $root . '/core/project_ssr.php';
$htmlSample = "<!DOCTYPE html><html><head></head><body><p>hi</p></body></html>";
$guideHtml = birkly_project_ssr_inject_webmcp($htmlSample);
assert_true(stripos($guideHtml, 'agent-guide.json') !== false, 'SSR injects agent guide discovery when live');
assert_true(stripos($guideHtml, 'llms.txt') !== false, 'SSR injects llms.txt discovery when live');
birkly_ai_presence_unpublish();
$offGuideHtml = birkly_project_ssr_inject_webmcp($htmlSample);
assert_true(stripos($offGuideHtml, 'llms.txt') === false, 'SSR skips discovery when guide unpublished');
// Republish for remaining tests
$repubDraft = birkly_ai_presence_load_draft();
$repubDraft['publication_settings']['enabled'] = true;
birkly_ai_presence_save_draft($repubDraft);
birkly_ai_presence_publish();
assert_true($ps2['version'] === 1, 'version 1 after first publish');
assert_true($ps2['has_unpublished_changes'] === false, 'no unpublished changes when draft matches');

echo "==> llms.txt from published Agent Guide\n";
$llms = birkly_ai_presence_llms_txt_body();
assert_true(is_string($llms) && $llms !== '', 'llms.txt body when published');
assert_true(preg_match('/^#\s+.+/m', (string) $llms) === 1, 'llms.txt has H1');
assert_true(preg_match('/\[.+\]\(.+\)/', (string) $llms) === 1, 'llms.txt has a Markdown link');
assert_true(strlen((string) $llms) >= 50, 'llms.txt length meets Lighthouse minimum');

echo "==> HTML discovery injection\n";
$htmlSample = '<!DOCTYPE html><html><head></head><body></body></html>';
$withDiscovery = birkly_ai_presence_inject_discovery_into_html($htmlSample);
assert_true(stripos($withDiscovery, 'agent-guide.json') !== false, 'discovery injects agent-guide link');
assert_true(stripos($withDiscovery, 'llms.txt') !== false, 'discovery injects llms.txt link');
assert_true(stripos($withDiscovery, 'rel="describedby"') !== false, 'discovery injects describedby link');
assert_true(stripos($withDiscovery, 'application/ld+json') !== false, 'discovery injects JSON-LD');
$llmsUrl = birkly_ai_presence_llms_txt_url();
assert_true(is_string($llmsUrl) && str_ends_with($llmsUrl, '/llms.txt'), 'llms.txt URL resolves');
$robots = birkly_ai_presence_robots_txt_augment("User-agent: *\nAllow: /\n");
assert_true(stripos($robots, 'LLMS:') !== false, 'robots.txt augmented with LLMS directive');
assert_true(stripos($robots, '/llms.txt') !== false, 'robots LLMS points at llms.txt');

$draft2 = birkly_ai_presence_load_draft();
$draft2['identity']['short_description'] = 'Changed';
birkly_ai_presence_save_draft($draft2);
$ps3 = birkly_ai_presence_publish_state($draft2);
assert_true($ps3['has_unpublished_changes'] === true, 'unpublished changes when draft edited');

$unpub = birkly_ai_presence_unpublish();
assert_true(($unpub['ok'] ?? false) === true, 'unpublish ok');
$ps4 = birkly_ai_presence_publish_state();
assert_true($ps4['publishing_enabled'] === false, 'publishing disabled after unpublish');
assert_true($ps4['is_live'] === false, 'not live after unpublish');
assert_true(birkly_ai_presence_llms_txt_body() === null, 'llms.txt null after unpublish');

echo "==> Publish validates before persisting enabled flag\n";
birkly_ai_presence_reset_for_tests();
$invalidDraft = birkly_ai_presence_default_draft();
$invalidDraft['publication_settings']['enabled'] = false;
$invalidDraft['claims'] = [['id' => 'bad', 'status' => 'not_a_real_status']];
birkly_ai_presence_ensure_dir();
birkly_ai_presence_write_json(birkly_ai_presence_dir() . '/draft.json', $invalidDraft);
$resultInvalid = birkly_ai_presence_publish();
assert_true(($resultInvalid['ok'] ?? true) === false, 'publish returns errors for invalid draft');
assert_true(!empty($resultInvalid['errors']), 'publish includes validation errors');
$stillDisabled = birkly_ai_presence_load_draft();
assert_true(empty($stillDisabled['publication_settings']['enabled']), 'invalid publish does not flip enabled flag');

$draftReenable = birkly_ai_presence_load_draft();
$draftReenable['publication_settings']['enabled'] = true;
birkly_ai_presence_save_draft($draftReenable);
$ps5 = birkly_ai_presence_publish_state($draftReenable);
assert_true($ps5['is_live'] === false, 'not live when draft re-enabled without republish');
assert_true($ps5['needs_republish'] === true, 'needs_republish when snapshot still disabled');
require_once $root . '/core/ai_presence_pro.php';
assert_true(birkly_ai_presence_public_agent_guide_for_tools('summary') === null, 'MCP read blocked until republish');

echo "==> Legacy published snapshot without enabled flag\n";
birkly_ai_presence_reset_for_tests();
$legacyDraft = birkly_ai_presence_default_draft();
$legacyDraft['identity']['official_name'] = 'Legacy Co';
$legacyDraft['publication_settings']['enabled'] = true;
birkly_ai_presence_save_draft($legacyDraft);
assert_true(($result = birkly_ai_presence_publish())['ok'] ?? false, 'legacy test publish ok');
$legacyPath = birkly_ai_presence_dir() . '/published.json';
$legacyRaw = json_decode((string) file_get_contents($legacyPath), true);
unset($legacyRaw['publication_settings']['enabled']);
file_put_contents($legacyPath, json_encode($legacyRaw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
$legacyLoaded = birkly_ai_presence_load_published();
assert_true(!empty($legacyLoaded['publication_settings']['enabled']), 'legacy published infers enabled from version');
assert_true(birkly_ai_presence_is_publicly_live(), 'legacy published snapshot is publicly live');
assert_true(birkly_ai_presence_public_agent_guide_for_tools('summary') !== null, 'legacy published readable via MCP');

echo "==> i18n keys\n";
$en = json_decode((string) file_get_contents($root . '/lang/en.json'), true);
assert_true(($en['admin']['ai_presence'] ?? '') === 'Agent Guide', 'en ai_presence label');
assert_true(isset($en['admin']['ai_presence_tab_sources']), 'en sources tab key');
assert_true(isset($en['admin']['ag_wizard_title']), 'en wizard title key');
assert_true(isset($en['admin']['unpublish_ai_presence']), 'en unpublish key');
$de = json_decode((string) file_get_contents($root . '/lang/de.json'), true);
assert_true(isset($de['admin']['ai_presence']), 'de ai_presence key');

birkly_ai_presence_reset_for_tests();

if ($failures > 0) {
    fwrite(STDERR, "$failures test group(s) failed\n");
    exit(1);
}

echo "All ai_presence tests passed.\n";
