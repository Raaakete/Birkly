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

echo "==> i18n keys\n";
$en = json_decode((string) file_get_contents($root . '/lang/en.json'), true);
assert_true(($en['admin']['ai_presence'] ?? '') === 'Agent Guide', 'en ai_presence label');
assert_true(isset($en['admin']['ai_presence_tab_sources']), 'en sources tab key');
$de = json_decode((string) file_get_contents($root . '/lang/de.json'), true);
assert_true(isset($de['admin']['ai_presence']), 'de ai_presence key');

birkly_ai_presence_reset_for_tests();

if ($failures > 0) {
    fwrite(STDERR, "$failures test group(s) failed\n");
    exit(1);
}

echo "All ai_presence tests passed.\n";
