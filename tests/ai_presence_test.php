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
assert_true($ps2['version'] === 1, 'version 1 after first publish');
assert_true($ps2['has_unpublished_changes'] === false, 'no unpublished changes when draft matches');
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
