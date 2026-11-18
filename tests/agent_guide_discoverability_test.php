<?php
/**
 * Agent Guide HTTP discoverability contract (routes, JSON validity, injection).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/ai_presence.php';
require_once $root . '/core/ai_presence_discovery.php';
require_once $root . '/core/hosting_urls.php';

$failures = 0;

function ag_disc_assert(bool $cond, string $label): void
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

$draft = birkly_ai_presence_default_draft();
$draft['identity']['official_name'] = 'Discoverability Test Co';
$draft['identity']['short_description'] = 'Short desc for discovery.';
$draft['publication_settings']['enabled'] = true;
birkly_ai_presence_save_draft($draft);
$pub = birkly_ai_presence_publish();
ag_disc_assert(($pub['ok'] ?? false) === true, 'publish ok');

echo "==> Routes\n";
require_once $root . '/core/http_routes.php';
$routes = birkly_http_route_map();
foreach ([
    '/.well-known/agent.json',
    '/agent-guide.json',
] as $path) {
    ag_disc_assert(isset($routes[$path]), 'route registered: ' . $path);
}

echo "==> Manifest + JSON validity\n";
$manifest = birkly_ai_presence_discovery_manifest_body();
$manifestDecoded = is_string($manifest) ? json_decode($manifest, true) : null;
ag_disc_assert(is_array($manifestDecoded), 'manifest is valid JSON');
ag_disc_assert(($manifestDecoded['schema'] ?? '') === 'birkly.agent_guide_discovery.v1', 'manifest schema');
ag_disc_assert(!empty($manifestDecoded['canonical_json']), 'manifest canonical_json');

$exportBody = birkly_ai_presence_get_public_export_body();
$exportDecoded = is_string($exportBody) ? json_decode($exportBody, true) : null;
ag_disc_assert(is_array($exportDecoded), 'agent guide export is valid JSON');
ag_disc_assert(isset($exportDecoded['usage_hints']['discovery_fetch']), 'export documents discovery_fetch');
ag_disc_assert(!empty($exportDecoded['discovery_urls']['agent_manifest']), 'discovery_urls includes manifest');

echo "==> Link header\n";
$link = birkly_ai_presence_discovery_http_link_header();
ag_disc_assert(str_contains($link, 'rel="canonical"'), 'Link header has canonical JSON');
ag_disc_assert(str_contains($link, 'rel="alternate"'), 'Link header has alternates');

echo "==> HTML injection\n";
$html = '<!DOCTYPE html><html><head></head><body><div data-part="footer"></div></body></html>';
$injected = birkly_ai_presence_inject_discovery_into_html($html);
ag_disc_assert(str_contains($injected, 'data-birkly-agent-guide="body-nav"'), 'body nav injected');
ag_disc_assert(str_contains($injected, 'agent-guide-manifest'), 'manifest meta injected');
ag_disc_assert(str_contains($injected, 'for-agents.md'), 'markdown alternate in head');
ag_disc_assert(str_contains($injected, 'birkly-agent-guide-noscript'), 'noscript for data-part footer');

echo "==> Sitemap lists new URLs\n";
$xml = birkly_ai_presence_discovery_sitemap_xml();
ag_disc_assert(stripos($xml, '/.well-known/agent.json') !== false, 'sitemap lists agent.json manifest');
ag_disc_assert(stripos($xml, '/agent-guide.json') !== false, 'sitemap lists agent-guide.json shortcut');

birkly_ai_presence_reset_for_tests();
if ($hostingBackup !== null) {
    file_put_contents($root . '/settings/hosting.json', $hostingBackup);
} elseif (is_file($root . '/settings/hosting.json')) {
    @unlink($root . '/settings/hosting.json');
}

exit($failures > 0 ? 1 : 0);