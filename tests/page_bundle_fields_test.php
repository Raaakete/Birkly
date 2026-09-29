<?php
/**
 * P100-M7 — lean bundle keep-keys come from core plus active plugins.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
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

$pluginsDir = sys_get_temp_dir() . '/birkly-bundle-fields-' . getmypid();
mkdir($pluginsDir . '/commerce', 0775, true);
mkdir($pluginsDir . '/events', 0775, true);
file_put_contents($pluginsDir . '/commerce/plugin.json', json_encode([
    'name' => 'Commerce',
    'page_bundle' => ['fields' => ['price', 'commerce', '../nope']],
], JSON_THROW_ON_ERROR));
file_put_contents($pluginsDir . '/events/plugin.json', json_encode([
    'name' => 'Events',
    'page_bundle' => ['fields' => ['start', 'venue']],
], JSON_THROW_ON_ERROR));

$GLOBALS['birkly_page_bundle_plugins_dir'] = $pluginsDir;
$GLOBALS['birkly_page_bundle_active'] = [
    'commerce' => true,
    'events' => true,
];

require_once $root . '/core/page_bundle_helpers.php';

$helpers = (string) file_get_contents($root . '/core/page_bundle_helpers.php');
$registry = (string) file_get_contents($root . '/core/page_bundle_fields.php');
assert_true(!str_contains($helpers, "'commerce'") && !str_contains($helpers, "'events'"), 'bundle helper has no plugin slug');
assert_true(!str_contains($registry, "'commerce'") && !str_contains($registry, "'events'"), 'bundle registry has no plugin slug');

$entry = [
    'id' => 'talk',
    'title' => 'Talk',
    'slug' => 'talk',
    'status' => 'published',
    'excerpt' => 'Hi',
    'price' => '12',
    'commerce' => ['sku' => 'talk'],
    'start' => '2026-10-01',
    'venue' => 'Hall',
    'custom_note' => 'keep',
    '_hidden' => 'no',
];

$active = birkly_page_bundle_prune_entry($entry, true);
assert_true(($active['price'] ?? null) === '12', 'active commerce keeps price');
assert_true(($active['commerce']['sku'] ?? null) === 'talk', 'active commerce keeps commerce');
assert_true(($active['start'] ?? null) === '2026-10-01', 'active events keeps start');
assert_true(($active['venue'] ?? null) === 'Hall', 'active events keeps venue');
assert_true(($active['excerpt'] ?? null) === 'Hi', 'core excerpt stays');
assert_true(($active['custom_note'] ?? null) === 'keep', 'undeclared custom fields stay');
assert_true(!array_key_exists('_hidden', $active), 'internal keys stay dropped');

$GLOBALS['birkly_page_bundle_active'] = [
    'commerce' => false,
    'events' => false,
];
$inactive = birkly_page_bundle_prune_entry($entry, true);
assert_true(!array_key_exists('price', $inactive), 'inactive commerce omits price');
assert_true(!array_key_exists('commerce', $inactive), 'inactive commerce omits commerce');
assert_true(!array_key_exists('start', $inactive), 'inactive events omits start');
assert_true(!array_key_exists('venue', $inactive), 'inactive events omits venue');
assert_true(($inactive['title'] ?? null) === 'Talk', 'system title stays without plugins');
assert_true(($inactive['excerpt'] ?? null) === 'Hi', 'core excerpt stays without plugins');
assert_true(($inactive['custom_note'] ?? null) === 'keep', 'undeclared fields stay without plugins');

$commercePlugin = dirname($root) . '/Birkly-Plugin-Commerce/plugin.json';
$eventsPlugin = dirname($root) . '/Birkly-Plugin-Events/plugin.json';
assert_true(is_file($commercePlugin) && is_file($eventsPlugin), 'plugin packages are available');
$commerceJson = json_decode((string) file_get_contents($commercePlugin), true);
$eventsJson = json_decode((string) file_get_contents($eventsPlugin), true);
assert_true(($commerceJson['page_bundle']['fields'] ?? null) === ['price', 'commerce'], 'Commerce declares bundle fields');
assert_true(($eventsJson['page_bundle']['fields'] ?? null) === ['start', 'venue'], 'Events declares bundle fields');

if ($failures > 0) {
    fwrite(STDERR, "page_bundle_fields_test: {$failures} failure(s)\n");
    exit(1);
}

echo "page_bundle_fields_test: PASS\n";
