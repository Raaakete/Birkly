<?php
/**
 * P68 Site URL Map tests (M1/M4).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$GLOBALS['__birkly_project_root'] = sys_get_temp_dir() . '/birkly_p68_' . bin2hex(random_bytes(4));

require_once $root . '/core/site_seo.php';
require_once $root . '/core/discovery.php';

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

$projectRoot = (string) $GLOBALS['__birkly_project_root'];
@mkdir($projectRoot . '/settings', 0755, true);
@mkdir($projectRoot . '/project', 0755, true);
@mkdir($projectRoot . '/content/collections/blog', 0755, true);
@mkdir($projectRoot . '/content/collections/blog/entries', 0755, true);

file_put_contents($projectRoot . '/project/index.html', "<!DOCTYPE html><html><head><title>Home</title></head><body>Home</body></html>\n");
file_put_contents($projectRoot . '/project/about.html', "<!DOCTYPE html><html><head><title>About</title></head><body>About</body></html>\n");
file_put_contents(
    $projectRoot . '/project/post.html',
    '<!DOCTYPE html><html><head><meta name="birkly-collection" content="blog"><title>Post</title></head><body></body></html>' . "\n"
);
file_put_contents($projectRoot . '/content/collections/blog/collection.json', json_encode(['name' => 'Blog', 'slug' => 'blog']) . "\n");
file_put_contents($projectRoot . '/content/collections/blog/schema.json', json_encode([
    'fields' => [
        ['name' => 'title', 'type' => 'text'],
        ['name' => 'excerpt', 'type' => 'textarea'],
        ['name' => 'content', 'type' => 'textarea'],
    ],
]) . "\n");
file_put_contents($projectRoot . '/content/collections/blog/entries/hello.json', json_encode([
    'id' => 'hello',
    'slug' => 'hello',
    'title' => 'Hello post',
    'excerpt' => 'Short teaser',
    'status' => 'published',
    'updated_at' => '2026-08-25T10:00:00+00:00',
]) . "\n");

$seoPath = birkly_site_seo_settings_path();
@unlink($seoPath);
birkly_site_seo_reset_cache();

echo "==> Schema v3 defaults\n";
$defaults = birkly_site_seo_defaults();
assert_true(($defaults['_schema_version'] ?? 0) === 3, 'schema version 3');
assert_true(isset($defaults['url_map']['pages']), 'url_map.pages default');
assert_true(isset($defaults['detail_bindings']), 'detail_bindings default');

echo "==> v2 upgrade preserves defaults\n";
$upgraded = birkly_site_seo_normalize([
    '_schema_version' => 2,
    'enabled' => true,
    'defaults' => ['description' => 'Kept'],
]);
assert_true(($upgraded['_schema_version'] ?? 0) === 3, 'v2 upgrades to v3');
assert_true(($upgraded['defaults']['description'] ?? '') === 'Kept', 'v2 fields preserved');

echo "==> Project scan\n";
$scanned = birkly_seo_url_map_scan_project();
assert_true(isset($scanned['url_map']['pages']['/']), 'scan finds home page');
assert_true(isset($scanned['url_map']['pages']['/about.html']), 'scan finds about page');
assert_true(count($scanned['detail_bindings']) >= 1, 'scan auto-suggests detail binding');
$binding = $scanned['detail_bindings'][0];
assert_true(($binding['collection'] ?? '') === 'blog', 'binding collection from meta');
assert_true(($binding['template_path'] ?? '') === 'post.html', 'binding template path');
$homePage = $scanned['url_map']['pages']['/'] ?? [];
assert_true(($homePage['file'] ?? '') === 'index.html', 'page row stores project file');

echo "==> Docs mirror exclusion\n";
@mkdir($projectRoot . '/project/01-admin', 0755, true);
file_put_contents(
    $projectRoot . '/project/01-admin/dashboard.html',
    "<!DOCTYPE html><html><head><title>Admin docs</title></head><body></body></html>\n"
);
$withDocs = birkly_seo_url_map_scan_project();
assert_true(!isset($withDocs['url_map']['pages']['/01-admin/dashboard.html']), 'docs mirror path excluded');
assert_true(
    (int) (($withDocs['scan']['stats']['excluded'] ?? 0)) >= 1,
    'scan stats count excluded docs files'
);
@unlink($projectRoot . '/project/01-admin/dashboard.html');
@rmdir($projectRoot . '/project/01-admin');

echo "==> Rescan prunes stale scan rows\n";
$configWithStale = birkly_site_seo_load();
$configWithStale['url_map']['pages']['/stale-page.html'] = [
    'label' => 'Stale',
    'source' => 'scan',
    'file' => 'stale-page.html',
    'overrides' => [],
];
birkly_site_seo_save($configWithStale);
$rescanned = birkly_seo_url_map_scan_project();
assert_true(!isset($rescanned['url_map']['pages']['/stale-page.html']), 'stale scan row removed on rescan');

echo "==> Automated setup\n";
@unlink($seoPath);
birkly_site_seo_reset_cache();
$setup = birkly_seo_automated_setup();
assert_true($setup['ok'] ?? false, 'automated setup succeeds');
assert_true(is_array($setup['summary'] ?? null), 'automated setup returns summary');
assert_true(($setup['summary']['pages'] ?? 0) >= 2, 'automated setup summary includes pages');

echo "==> Rescan materializes detail URLs\n";
$scanSave = birkly_seo_scan_and_save();
assert_true($scanSave['ok'] ?? false, 'scan and save returns ok');
assert_true(($scanSave['summary']['detail_urls'] ?? 0) >= 1, 'rescan materializes detail urls');
$adminRows = $scanSave['rows'] ?? [];
$detailRow = null;
foreach ($adminRows as $row) {
    if (($row['type'] ?? '') === 'detail') {
        $detailRow = $row;
        break;
    }
}
assert_true($detailRow !== null, 'admin rows include detail');
assert_true(($detailRow['project_file'] ?? '') === 'post.html', 'detail row shows template file');
assert_true(($detailRow['listing_subtitle'] ?? '') !== '', 'detail row has readable subtitle');

echo "==> Excerpt field guess\n";
assert_true(birkly_seo_excerpt_field_guess('blog') === 'excerpt', 'excerpt field guessed');

echo "==> Materialize detail URLs on publish\n";
birkly_site_seo_save($scanned);
birkly_discovery_save_pack('seo', ['enabled' => true]);
birkly_site_seo_publish();
$config = birkly_site_seo_load();
$detailKey = birkly_seo_detail_key('post.html', 'blog', 'hello');
assert_true(isset($config['url_map']['details'][$detailKey]), 'detail URL materialized on publish');

echo "==> SSR context builder\n";
$context = birkly_seo_build_context(
    '/post.html',
    ['collection' => 'blog', 'slug' => 'hello'],
    ['entry' => ['title' => 'Hello post', 'excerpt' => 'Short teaser']],
    '<html><head><title>Post</title></head></html>'
);
assert_true(($context['page_title'] ?? '') === 'Hello post', 'detail title from entry');
assert_true(($context['meta_description'] ?? '') === 'Short teaser', 'detail description from excerpt');
assert_true(str_contains((string) ($context['canonical'] ?? ''), 'collection=blog'), 'canonical has collection');

$pageContext = birkly_seo_build_context(
    '/about.html',
    [],
    [],
    '<html><head><title>About us</title></head></html>'
);
assert_true(($pageContext['page_title'] ?? '') === 'About us', 'static page title from rendered html');

echo "==> URL map admin rows\n";
$rows = birkly_seo_url_map_admin_rows($config);
assert_true(count($rows) >= 3, 'admin rows include pages and details');

echo "==> SEO redirect match\n";
$config['url_map']['redirects'] = [['from' => '/old-about', 'to' => '/about.html', 'status' => 301]];
birkly_site_seo_save($config);
birkly_discovery_save_pack('seo', ['enabled' => true]);
birkly_site_seo_publish();
$match = birkly_seo_match_redirect('/old-about');
assert_true($match !== null && ($match['to_path'] ?? '') === '/about.html', 'seo redirect matches');

echo "==> Redirect loop detection\n";
$loops = birkly_seo_detect_redirect_loops([
    ['from' => '/a', 'to' => '/b'],
    ['from' => '/b', 'to' => '/a'],
]);
assert_true(count($loops) >= 1, 'detects redirect loop');

echo "==> Health checks\n";
$health = birkly_seo_health_checks();
assert_true(is_array($health), 'health checks array');

echo "==> Public SEO context\n";
$pub = birkly_seo_public_context('/about.html', [], '<html><head><title>About</title></head></html>');
assert_true(($pub['title'] ?? '') !== '', 'public context has title');

echo "==> Legacy meta import\n";
file_put_contents($projectRoot . '/content/collections/blog/entries/legacy.json', json_encode([
    'id' => 'legacy',
    'slug' => 'legacy',
    'title' => 'Legacy post',
    'excerpt' => 'Legacy excerpt',
    'meta_title' => 'Imported title',
    'meta_description' => 'Imported description',
    'status' => 'published',
]) . "\n");
$config = birkly_site_seo_load();
$config = birkly_seo_import_legacy_entry_meta($config);
$key = birkly_seo_detail_key('post.html', 'blog', 'legacy');
$imported = $config['url_map']['details'][$key]['overrides']['title'] ?? '';
assert_true($imported === 'Imported title', 'legacy meta_title imported');

@unlink($projectRoot . '/content/collections/blog/entries/legacy.json');

@unlink($seoPath);
array_map('unlink', glob($projectRoot . '/content/collections/blog/entries/*.json') ?: []);
@rmdir($projectRoot . '/content/collections/blog/entries');
@rmdir($projectRoot . '/content/collections/blog');
@unlink($projectRoot . '/project/index.html');
@unlink($projectRoot . '/project/about.html');
@unlink($projectRoot . '/project/post.html');
@rmdir($projectRoot . '/project');
@rmdir($projectRoot . '/settings');
@rmdir($projectRoot);
unset($GLOBALS['__birkly_project_root']);

exit($failures > 0 ? 1 : 0);
