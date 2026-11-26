<?php
/**
 * SEO pack tests (P67-M4/M5/M8).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/site_seo.php';
require_once $root . '/core/site_seo_url_map.php';
require_once $root . '/core/site_seo_json_ld.php';
require_once $root . '/core/discovery.php';
require_once $root . '/core/ai_presence.php';
require_once $root . '/core/hosting_urls.php';

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

echo "==> site_seo.php loads url_map helpers\n";
$loaded = birkly_site_seo_load();
assert_true(isset($loaded['url_map']['pages']) && is_array($loaded['url_map']['pages']), 'site_seo_load includes url_map');

$seoPath = birkly_site_seo_settings_path();
$discoveryPath = birkly_discovery_settings_path();
$seoBackup = is_file($seoPath) ? file_get_contents($seoPath) : null;
$discoveryBackup = is_file($discoveryPath) ? file_get_contents($discoveryPath) : null;
@unlink($seoPath);
@unlink($discoveryPath);
birkly_site_seo_reset_cache();
birkly_discovery_reset_cache();

echo "==> SEO inject head\n";
birkly_discovery_save_pack('seo', ['enabled' => true]);
birkly_site_seo_publish();
$html = '<!DOCTYPE html><html><head></head><body></body></html>';
$injected = birkly_site_seo_inject_head($html, [
    'page_title' => 'Hello',
    'meta_description' => 'Test description',
    'canonical' => 'https://example.test/page',
]);
assert_true(stripos($injected, '<title>Hello</title>') !== false, 'injects title');
assert_true(stripos($injected, 'name="description"') !== false, 'injects description');
assert_true(stripos($injected, 'rel="canonical"') !== false, 'injects canonical');
assert_true(stripos($injected, 'og:title') !== false, 'injects open graph');
assert_true(stripos($injected, 'name="twitter:card"') !== false, 'injects twitter card');
assert_true(stripos($injected, 'application/ld+json') !== false, 'injects JSON-LD');

echo "==> Conflict detection\n";
$htmlWithTitle = '<!DOCTYPE html><html><head><title>Existing</title></head><body></body></html>';
$skipped = birkly_site_seo_inject_head($htmlWithTitle, ['page_title' => 'New']);
assert_true(stripos($skipped, '<title>New</title>') === false, 'skips title when present');

echo "==> Robots augment\n";
$robots = birkly_site_seo_robots_augment("User-agent: *\nAllow: /\n");
assert_true(stripos($robots, 'Sitemap:') !== false, 'robots gets Sitemap line');

echo "==> SSR context inject\n";
birkly_discovery_save_pack('seo', ['enabled' => true]);
$config = birkly_site_seo_load();
$config['url_map']['pages']['/about.html'] = [
    'label' => 'About',
    'overrides' => ['title' => 'Custom About Title', 'description' => 'Custom about desc'],
];
birkly_site_seo_save($config);
birkly_site_seo_publish();
$ctx = birkly_seo_build_context('/about.html', [], [], '<html><head><title>About</title></head></html>');
$injectedPage = birkly_site_seo_inject_head($html, $ctx);
assert_true(stripos($injectedPage, '<title>Custom About Title</title>') !== false, 'page override title injected');

echo "==> save_seo partial merge\n";
$full = birkly_site_seo_load();
$full['enabled'] = true;
$full['published'] = true;
$full['defaults']['site_title'] = 'Preserve Me';
birkly_site_seo_save($full);
$partial = [
    'detail_bindings' => [
        [
            'id' => 'bind_test',
            'collection' => 'blog',
            'template_path' => 'blog-post.html',
            'excerpt_field' => 'excerpt',
        ],
    ],
];
birkly_site_seo_save($partial);
$afterPartial = birkly_site_seo_load();
assert_true(!empty($afterPartial['enabled']) && !empty($afterPartial['published']), 'partial save keeps enabled/published');
assert_true(($afterPartial['defaults']['site_title'] ?? '') === 'Preserve Me', 'partial save keeps defaults');

echo "==> Pretty detail canonical\n";
birkly_hosting_persist([
    'cms_base_url' => 'https://cms.example.test',
    'public_site_mode' => 'project',
    'public_site_url' => 'https://birkly.example.test',
]);
$config = birkly_site_seo_load();
$config['detail_bindings'] = [[
    'id' => 'bind_blog',
    'collection' => 'blog',
    'template_path' => 'blog-post.html',
    'url_pattern' => '/blog/{slug}',
]];
birkly_site_seo_save($config);
$pretty = birkly_seo_detail_public_url('blog-post.html', 'blog', 'hello-world');
assert_true($pretty === 'https://birkly.example.test/blog/hello-world', 'pretty detail URL without query string');

echo "==> Signature part\n";
assert_true(str_starts_with(birkly_site_seo_signature_part(), 'seo'), 'signature part prefix');

echo "==> Detail page JSON-LD graph\n";
$hostingBackup = is_file($root . '/settings/hosting.json') ? file_get_contents($root . '/settings/hosting.json') : null;
birkly_hosting_persist([
    'cms_base_url' => 'https://cms.example.test',
    'public_site_mode' => 'project',
    'public_site_url' => 'https://birkly.example.test',
]);
birkly_discovery_save_pack('agent_guide', ['enabled' => true]);
birkly_ai_presence_reset_for_tests();
$draft = birkly_ai_presence_default_draft();
$draft['identity']['official_name'] = 'Birkly';
$draft['identity']['short_description'] = 'Lightweight CMS for the modern web.';
$draft['identity']['website'] = 'https://birkly.example.test';
$draft['identity']['social_profiles'] = [
    ['platform' => 'github', 'url' => 'https://github.com/Raaakete/Birkly'],
    ['platform' => 'x', 'url' => 'https://x.com/birkly'],
];
$draft['publication_settings']['enabled'] = true;
birkly_ai_presence_save_draft($draft);
assert_true(($resultAg = birkly_ai_presence_publish())['ok'] ?? false, 'agent guide publish for json-ld test');

$config = birkly_site_seo_load();
$config['defaults']['description'] = 'Site-wide SEO description.';
$config['defaults']['og_image'] = 'https://birkly.example.test/brand-og.png';
$config['delivery']['surfaces']['json_ld_article'] = true;
birkly_site_seo_save($config);
birkly_site_seo_publish();

$detailContext = [
    'page_title' => 'Plain PHP in 2026',
    'entry_title' => 'Plain PHP in 2026',
    'meta_description' => 'Post excerpt must not become Organization description.',
    'canonical' => 'https://birkly.example.test/blog-post.html?collection=blog&slug=plain-php',
    'is_detail' => true,
    'collection_slug' => 'blog',
    'entry_date_published' => '2026-01-15T10:00:00+00:00',
    'entry_date_modified' => '2026-02-01T12:00:00+00:00',
    'entry_author' => 'Birkly Team',
    'entry_image_url' => 'https://birkly.example.test/media/hero.webp',
    'og_image' => 'https://birkly.example.test/media/hero.webp',
];
$detailHtml = '<!DOCTYPE html><html><head></head><body></body></html>';
$detailInjected = birkly_site_seo_inject_head($detailHtml, $detailContext);
$orgCount = substr_count($detailInjected, '"@type":"Organization"')
    + substr_count($detailInjected, '"@type": "Organization"');
assert_true($orgCount === 1, 'single Organization node in JSON-LD');
assert_true(str_contains($detailInjected, '#organization'), 'Organization has stable @id');
assert_true(str_contains($detailInjected, 'Site-wide SEO description'), 'Organization uses site description');
assert_true(!str_contains($detailInjected, 'Post excerpt must not become Organization description')
    || str_contains($detailInjected, '"description":"Post excerpt'), 'excerpt only on article not org');
assert_true(str_contains($detailInjected, 'BlogPosting'), 'blog detail uses BlogPosting');
assert_true(str_contains($detailInjected, 'datePublished'), 'article has datePublished');
assert_true(str_contains($detailInjected, 'dateModified'), 'article has dateModified');
assert_true(str_contains($detailInjected, 'Birkly Team'), 'article has author');
assert_true(str_contains($detailInjected, 'github.com/Raaakete/Birkly'), 'Organization has sameAs');
assert_true(stripos($detailInjected, 'property="og:type" content="article"') !== false, 'og:type article on detail');
assert_true(stripos($detailInjected, 'article:published_time') !== false, 'article published time meta');

$stacked = birkly_ai_presence_inject_discovery_into_html($detailInjected);
$orgCountAfterAg = substr_count($stacked, '"@type":"Organization"')
    + substr_count($stacked, '"@type": "Organization"');
assert_true($orgCountAfterAg === 1, 'Agent Guide inject does not duplicate Organization JSON-LD');

if ($hostingBackup !== null) {
    file_put_contents($root . '/settings/hosting.json', $hostingBackup);
} elseif (is_file($root . '/settings/hosting.json')) {
    @unlink($root . '/settings/hosting.json');
}
birkly_ai_presence_reset_for_tests();

if ($seoBackup !== null) {
    file_put_contents($seoPath, $seoBackup);
} elseif (is_file($seoPath)) {
    @unlink($seoPath);
}
if ($discoveryBackup !== null) {
    file_put_contents($discoveryPath, $discoveryBackup);
} elseif (is_file($discoveryPath)) {
    @unlink($discoveryPath);
}
birkly_site_seo_reset_cache();
birkly_discovery_reset_cache();

exit($failures > 0 ? 1 : 0);
