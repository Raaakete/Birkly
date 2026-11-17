<?php
/**
 * birkly.io bug report — sitemap, SEO save sanitization, discovery contracts.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$GLOBALS['__birkly_project_root'] = sys_get_temp_dir() . '/birkly_sitemap_' . bin2hex(random_bytes(4));

require_once $root . '/core/site_seo.php';
require_once $root . '/core/discovery.php';
require_once $root . '/core/ai_presence_discovery.php';
require_once $root . '/core/project_site.php';

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

file_put_contents($projectRoot . '/project/index.html', "<!DOCTYPE html><html><head><title>Home</title></head><body></body></html>\n");
file_put_contents(
    $projectRoot . '/project/secret.html',
    "<!DOCTYPE html><html><head><meta name=\"robots\" content=\"noindex,nofollow\"><title>Secret</title></head><body></body></html>\n"
);
file_put_contents(
    $projectRoot . '/project/post.html',
    '<!DOCTYPE html><html><head><meta name="birkly-collection" content="blog"><title>Post</title></head><body></body></html>' . "\n"
);

$seoPath = birkly_site_seo_settings_path();
@unlink($seoPath);
birkly_site_seo_reset_cache();

echo "==> SEO save strips nested seo wrapper and unknown keys\n";
$normalized = birkly_site_seo_normalize([
    'seo' => [
        'defaults' => ['site_title' => 'Wrapped'],
        'evil_nested' => true,
    ],
    'not_allowed' => ['x' => 1],
]);
assert_true(($normalized['defaults']['site_title'] ?? '') === 'Wrapped', 'unwrap seo.defaults.site_title');
assert_true(!isset($normalized['evil_nested']), 'unknown nested dropped');
assert_true(!isset($normalized['not_allowed']), 'unknown top-level dropped');

echo "==> Sitemap skips noindex and index.html canonical path\n";
require_once $root . '/core/hosting_urls.php';
$GLOBALS['__birkly_hosting_config'] = [
    'public_site_mode' => 'project',
    'public_site_url' => 'https://example.test',
    'cms_base_url' => 'https://cms.example.test',
    'installed' => true,
];

$entries = birkly_ai_presence_discovery_sitemap_entries();
$locs = array_column($entries, 'loc');
assert_true(in_array('https://example.test/', $locs, true) || in_array('https://example.test', $locs, true), 'index maps to site root');
assert_true(!in_array('https://example.test/index.html', $locs, true), 'no duplicate /index.html loc');
assert_true(!in_array('https://example.test/secret.html', $locs, true), 'noindex page omitted');
assert_true(!in_array('https://example.test/post.html', $locs, true), 'detail template omitted from raw scan');

echo "==> Published SEO sitemap is authoritative (no raw scan duplicates)\n";
$config = birkly_seo_url_map_scan_project();
$config['url_map']['excludes'][] = 'secret.html';
birkly_site_seo_save($config);
birkly_discovery_save_pack('seo', ['enabled' => true]);
birkly_site_seo_publish();
$publishedEntries = birkly_ai_presence_discovery_sitemap_entries();
$publishedLocs = array_column($publishedEntries, 'loc');
assert_true(
    !in_array('https://example.test/secret.html', $publishedLocs, true),
    'excluded/noindex not in published merge'
);

echo "==> Health uses template title when override empty\n";
$health = birkly_seo_health_checks();
$missingHome = false;
foreach ($health as $check) {
    if (str_contains((string) ($check['label'] ?? ''), 'Missing title') && str_contains((string) ($check['label'] ?? ''), '/')) {
        if (str_contains((string) ($check['label'] ?? ''), 'Home') || preg_match('#/:?/?$#', (string) ($check['label'] ?? ''))) {
            $missingHome = true;
        }
    }
}
assert_true($missingHome === false, 'home page with <title> not flagged missing');

@unlink($seoPath);
@unlink($projectRoot . '/project/index.html');
@unlink($projectRoot . '/project/secret.html');
@unlink($projectRoot . '/project/post.html');
@rmdir($projectRoot . '/project');
@rmdir($projectRoot . '/settings');
@rmdir($projectRoot);
echo "==> Discovery admin title edit uses modal prompt\n";
$discoveryJs = (string) file_get_contents($root . '/admin/js/discovery.js');
assert_true(str_contains($discoveryJs, 'BirklyFeedback.prompt'), 'discovery.js uses BirklyFeedback.prompt');

unset($GLOBALS['__birkly_project_root'], $GLOBALS['__birkly_hosting_config']);

exit($failures > 0 ? 1 : 0);