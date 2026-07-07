<?php
/**
 * P15-M3 — Help manifest wiring: settings, live Birkly-Docs fetch, offline fallback.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/help_docs.php';
require_once dirname(__DIR__) . '/core/plugins.php';

test_reset_failures();
echo "Help docs wiring tests\n\n";

test_assert('birkly_load_help_settings exists', function_exists('birkly_load_help_settings'));
test_assert('birkly_fetch_live_help_manifest exists', function_exists('birkly_fetch_live_help_manifest'));
test_assert('birkly_build_platform_help_manifest exists', function_exists('birkly_build_platform_help_manifest'));
test_assert('birkly_help_offline_manifest exists', function_exists('birkly_help_offline_manifest'));

$settingsPath = birkly_help_settings_path();
test_assert('settings/help_manifest.json exists', is_file($settingsPath));

$settings = birkly_load_help_settings();
test_assert('settings has docs_base_url', !empty($settings['docs_base_url']));
test_assert('settings has live_manifest_url', !empty($settings['live_manifest_url']));

$core = birkly_load_core_help_manifest();
test_assert('core manifest has topics', count($core['topics'] ?? []) > 0);

$offline = birkly_help_offline_manifest();
test_assert('offline manifest has topics', count($offline['topics'] ?? []) > 0);
test_assert('offline manifest source flag', ($offline['manifest_source'] ?? '') === 'offline');

$platform = birkly_build_platform_help_manifest();
test_assert('platform manifest has topics', count($platform['topics'] ?? []) > 0);
test_assert('platform manifest has docs_base_url', !empty($platform['docs_base_url']));
test_assert('manifest_source is live or offline', in_array($platform['manifest_source'] ?? '', ['live', 'offline'], true));

$gettingStarted = null;
foreach ($platform['topics'] as $topic) {
    if (($topic['id'] ?? '') === 'getting-started') {
        $gettingStarted = $topic;
        break;
    }
}
test_assert('getting-started topic present', $gettingStarted !== null);
test_assert(
    'getting-started docs_path points to Birkly-Docs',
    strpos((string) ($gettingStarted['docs_path'] ?? ''), '00-overview/getting-started') !== false
);

$merged = birkly_build_help_manifest();
test_assert('merged help manifest includes platform topics', count($merged['topics'] ?? []) >= count($platform['topics'] ?? []));
test_assert('merged manifest has platform section', ($merged['sections'][0]['id'] ?? '') === 'platform');

$docsManifestPath = dirname(__DIR__, 2) . '/Birkly-Docs/help-manifest.json';
if (is_file($docsManifestPath)) {
    $docsManifest = json_decode((string) file_get_contents($docsManifestPath), true);
    test_assert('Birkly-Docs help-manifest.json parses', is_array($docsManifest));
    test_assert('Birkly-Docs manifest has topics', count($docsManifest['topics'] ?? []) > 0);
} else {
    echo "  (skip Birkly-Docs manifest — sibling repo not present)\n";
}

test_assert('api/help_offline_manifest.php exists', is_file(dirname(__DIR__) . '/api/help_offline_manifest.php'));

$helpJs = (string) file_get_contents(dirname(__DIR__) . '/admin/js/help.js');
test_assert('help.js loads offline manifest fallback', strpos($helpJs, '/api/help_offline_manifest.php') !== false);
test_assert('help.js shows offline banner', strpos($helpJs, 'help-offline-banner') !== false);

// Simulate live merge with fixture manifest
$fixtureLive = [
    'version' => 2,
    'docs_base_url' => 'https://docs.example.test',
    'topics' => [
        [
            'id' => 'getting-started',
            'title' => 'Getting started (live)',
            'docs_path' => '00-overview/getting-started.html',
            'keywords' => ['live'],
        ],
        [
            'id' => 'new-live-topic',
            'title' => 'New live topic',
            'docs_path' => '02-content/collections.html',
        ],
    ],
];

$mergedTopics = birkly_merge_help_topics($core['topics'], $fixtureLive['topics']);
$gsMerged = array_values(array_filter($mergedTopics, fn($t) => ($t['id'] ?? '') === 'getting-started'))[0] ?? [];
test_assert('merge updates title from live', ($gsMerged['title'] ?? '') === 'Getting started (live)');
test_assert('merge adds new live topic', in_array('new-live-topic', array_column($mergedTopics, 'id'), true));

exit(test_finish('Help docs wiring tests'));
