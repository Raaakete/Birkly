<?php
/**
 * SEO pack tests (P67-M4/M5/M8).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
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
assert_true(stripos($injected, 'application/ld+json') !== false, 'injects JSON-LD');

echo "==> Conflict detection\n";
$htmlWithTitle = '<!DOCTYPE html><html><head><title>Existing</title></head><body></body></html>';
$skipped = birkly_site_seo_inject_head($htmlWithTitle, ['page_title' => 'New']);
assert_true(stripos($skipped, '<title>New</title>') === false, 'skips title when present');

echo "==> Robots augment\n";
$robots = birkly_site_seo_robots_augment("User-agent: *\nAllow: /\n");
assert_true(stripos($robots, 'Sitemap:') !== false, 'robots gets Sitemap line');

echo "==> Signature part\n";
assert_true(str_starts_with(birkly_site_seo_signature_part(), 'seo'), 'signature part prefix');

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
