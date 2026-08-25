<?php
/**
 * Agent Guide discovery tests (P66).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/ai_presence.php';
require_once $root . '/core/ai_presence_discovery.php';

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

echo "==> Publication settings defaults\n";
$draft = birkly_ai_presence_default_draft();
$settings = birkly_ai_presence_discovery_publication_settings($draft);
assert_true($settings['footer_discovery_link'] === true, 'footer link default on');
assert_true($settings['footer_discovery_line'] === false, 'footer line default off');
assert_true($settings['discovery_sitemap'] === true, 'sitemap default on');
assert_true($settings['discovery_indexnow'] === true, 'indexnow default on');
assert_true($settings['discovery_ai_crawlers'] === true, 'ai crawlers default on');

echo "==> Publish + robots augment\n";
$draft['identity']['official_name'] = 'Discovery Test Co';
$draft['identity']['short_description'] = 'We test AI discovery.';
$draft['publication_settings']['enabled'] = true;
birkly_ai_presence_save_draft($draft);
$result = birkly_ai_presence_publish();
assert_true(($result['ok'] ?? false) === true, 'publish ok for discovery tests');

$robots = birkly_ai_presence_robots_txt_augment("User-agent: *\nAllow: /\n");
assert_true(stripos($robots, 'Sitemap:') !== false, 'robots includes Sitemap directive');
assert_true(stripos($robots, 'GPTBot') !== false, 'robots includes GPTBot allow');
assert_true(stripos($robots, 'LLMS:') !== false, 'robots includes LLMS directive');

echo "==> Sitemap XML\n";
$xml = birkly_ai_presence_discovery_sitemap_xml();
assert_true(str_contains($xml, '<urlset'), 'sitemap is valid xml urlset');
assert_true(stripos($xml, '/for-agents') !== false, 'sitemap lists for-agents');
assert_true(stripos($xml, 'agent-guide.json') !== false, 'sitemap lists agent-guide.json');
assert_true(stripos($xml, 'llms.txt') !== false, 'sitemap lists llms.txt');

echo "==> Discovery URL map + cross-links\n";
$map = birkly_ai_presence_discovery_url_map();
assert_true(!empty($map['for_agents']), 'url map has for_agents');
assert_true(!empty($map['agent_guide_json']), 'url map has agent_guide_json');
assert_true(!empty($map['llms_txt']), 'url map has llms_txt');
assert_true(!empty($map['sitemap']), 'url map has sitemap');
assert_true(!empty($map['robots']), 'url map has robots');

$llmsBody = birkly_ai_presence_llms_txt_body();
assert_true($llmsBody !== null, 'llms.txt body when live');
assert_true(stripos($llmsBody, '## For AI systems') !== false, 'llms.txt has For AI systems section');
assert_true(stripos($llmsBody, 'sitemap') !== false, 'llms.txt links to sitemap');
assert_true(stripos($llmsBody, 'robots.txt') !== false, 'llms.txt links to robots.txt');
assert_true(stripos($robots, 'Sitemap:') !== false && stripos($robots, 'agent-guide') !== false, 'robots cross-links guide endpoints');

$exportBody = birkly_ai_presence_get_public_export_body();
assert_true($exportBody !== null && stripos($exportBody, 'discovery_urls') !== false, 'agent-guide.json includes discovery_urls');

$forAgentsHtml = birkly_ai_presence_for_agents_html();
assert_true($forAgentsHtml !== null && stripos($forAgentsHtml, 'sitemap') !== false, 'for-agents HTML links sitemap');
assert_true($forAgentsHtml !== null && stripos($forAgentsHtml, 'llms.txt') !== false, 'for-agents HTML links llms.txt');

echo "==> Template summary\n";
$summary = birkly_ai_presence_discovery_template_summary();
assert_true(is_array($summary), 'template summary is array when live');
assert_true($summary !== null && ($summary['official_name'] ?? '') === 'Discovery Test Co', 'summary has official name');
assert_true($summary !== null && str_contains((string) ($summary['summary_line'] ?? ''), 'Agent Guide'), 'summary line mentions guide');

echo "==> Discovery status API shape\n";
$status = birkly_ai_presence_discovery_status();
assert_true($status['live'] === true, 'status reports live');
assert_true(is_array($status['checks'] ?? null) && count($status['checks']) >= 5, 'status has checklist rows');
$meshCheck = null;
foreach ($status['checks'] as $check) {
    if (($check['id'] ?? '') === 'discovery_mesh') {
        $meshCheck = $check;
        break;
    }
}
assert_true($meshCheck !== null && ($meshCheck['status'] ?? '') === 'pass', 'discovery mesh check passes');

echo "==> IndexNow key\n";
$key = birkly_ai_presence_discovery_indexnow_key();
assert_true(preg_match('/^[a-f0-9]{32}$/', $key) === 1, 'indexnow key is 32 hex chars');

$meta = birkly_ai_presence_load_meta();
assert_true(($meta['discovery_indexnow_key'] ?? '') === $key, 'indexnow key stored in meta');

echo "==> HTTP routes\n";
require_once $root . '/core/http_routes.php';
$routes = birkly_http_route_map();
assert_true(isset($routes['/sitemap.xml']), 'sitemap.xml route registered');
assert_true(isset($routes['/for-agents.md']), 'for-agents.md route registered');
assert_true(isset($routes['/for-agents.json']), 'for-agents.json route registered');

echo "==> SSR signature\n";
require_once $root . '/core/project_ssr.php';
assert_true(str_contains(birkly_project_ssr_inject_signature(), 'dsc7'), 'SSR inject signature bumped to dsc7');

birkly_ai_presence_reset_for_tests();

exit($failures > 0 ? 1 : 0);
