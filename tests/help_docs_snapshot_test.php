<?php
/**
 * Product Help topics point at the docs origin. The install does not serve article bodies.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/help_docs.php';

$failures = 0;

function assert_true(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '  ' . $detail . "\n";
        }
    }
}

$url = birkly_help_topic_content_url(
    ['id' => 'collections', 'docs_path' => '02-content/collections.html'],
    'https://docs.birkly.io'
);
assert_true(
    'content url for collections',
    $url === 'https://docs.birkly.io/content/02-content/collections.html',
    $url
);

$pluginUrl = birkly_help_topic_content_url([
    'id' => 'commerce',
    'source' => 'plugin',
    'snapshot' => '/api/plugin_doc.php?plugin=commerce&doc=overview',
    'docs_path' => '06-extensibility/plugins/commerce.html',
]);
assert_true('plugin topic has no origin article url', $pluginUrl === '');

$normalized = birkly_normalize_help_topics([
    ['id' => 'content-model-overview', 'title' => 'Overview', 'docs_path' => '02-content/content-model-overview.html', 'snapshot' => '/admin/docs/snapshots/content-model-overview.md'],
]);
assert_true(
    'normalize drops product snapshot',
    count($normalized) === 1 && !isset($normalized[0]['snapshot'])
);

$merged = birkly_merge_help_topics(
    [['id' => 'entries', 'title' => 'Entries', 'snapshot' => '/admin/docs/snapshots/entries.md']],
    [['id' => 'entries', 'title' => 'Entries live', 'docs_path' => '02-content/entries.html']]
);
assert_true(
    'merge drops product snapshot',
    count($merged) === 1 && !isset($merged[0]['snapshot']) && ($merged[0]['docs_path'] ?? '') === '02-content/entries.html'
);

$root = dirname(__DIR__);
$runtime = '';
foreach (['core/help_docs.php', 'admin/js/help.js', 'api/help_manifest.php'] as $rel) {
    $runtime .= (string) file_get_contents($root . '/' . $rel);
}
assert_true('runtime does not read admin/docs/snapshots', !str_contains($runtime, 'admin/docs/snapshots'));

$helpJs = (string) file_get_contents($root . '/admin/js/help.js');
assert_true('help.js fetches /content/ fragments', str_contains($helpJs, "docsBase + '/content/'"));
assert_true(
    'help.js names the unavailable state',
    str_contains($helpJs, 'Documentation is unavailable because docs.birkly.io could not be reached.')
);

exit($failures > 0 ? 1 : 0);
