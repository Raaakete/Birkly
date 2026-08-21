<?php
/**
 * page_bundle + entry cache tests for newly created collections.
 *
 * Verifies:
 * - batch bundle succeeds for same-day collections mixed with older ones
 * - cache mtime validation resolves physical collection directory (not slug-only path)
 * - collection create invalidates stale entry cache via revision bump
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/storage/StorageFactory.php';
require_once dirname(__DIR__) . '/core/page_bundle_runner.php';
require_once dirname(__DIR__) . '/core/page_bundle_helpers.php';
require_once dirname(__DIR__) . '/core/cache.php';
require_once dirname(__DIR__) . '/core/birkly_data_paths.php';

$failures = 0;
$tmpRoot = sys_get_temp_dir() . '/birkly-page-bundle-new-' . bin2hex(random_bytes(4));
$content = $tmpRoot . '/content';
$cacheDir = $tmpRoot . '/cache';
$dataRoot = $tmpRoot . '/data';

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

function delete_dir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            delete_dir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

mkdir($content . '/collections/kursseite/entries', 0755, true);
mkdir($content . '/collections/usps/entries', 0755, true);
mkdir($cacheDir, 0755, true);
mkdir($dataRoot . '/cache', 0755, true);

putenv('BIRKLY_DATA=' . $dataRoot);
$GLOBALS['__birkly_data_root'] = $dataRoot;

file_put_contents($content . '/collections/kursseite/collection.json', json_encode([
    'slug' => 'kursseite',
    'title' => 'Kursseite',
    'allow_public_read' => true,
    'created_at' => date('c'),
], JSON_PRETTY_PRINT));
file_put_contents($content . '/collections/kursseite/entries/main.json', json_encode([
    'id' => 'main',
    'slug' => 'main',
    'status' => 'published',
    'title' => 'Course',
    'content' => '<p>Hello</p>',
    'created_at' => date('c'),
    'updated_at' => date('c'),
], JSON_PRETTY_PRINT));

file_put_contents($content . '/collections/usps/collection.json', json_encode([
    'slug' => 'usps',
    'title' => 'USPs',
    'allow_public_read' => true,
], JSON_PRETTY_PRINT));
file_put_contents($content . '/collections/usps/entries/one.json', json_encode([
    'id' => 'one',
    'slug' => 'one',
    'status' => 'published',
    'title' => 'USP',
], JSON_PRETTY_PRINT));

StorageFactory::reset();
$storage = StorageFactory::createStorage($content);

$requests = [
    ['type' => 'list_entries', 'into' => 'kursseite', 'collection' => 'kursseite'],
    ['type' => 'list_entries', 'into' => 'usps', 'collection' => 'usps'],
];

$bundle = birkly_run_page_bundle_requests($storage, $requests, 'en', ['trusted_ssr' => true]);
assert_true(
    'mixed new/old collections return ok bundle',
    ($bundle['status'] ?? '') === 'ok'
    && count($bundle['data']['kursseite'] ?? []) === 1
    && count($bundle['data']['usps'] ?? []) === 1,
    json_encode($bundle, JSON_UNESCAPED_SLASHES)
);

// Legacy dir name site_elements with hyphen slug in meta — cache path must follow dir, not slug-only.
mkdir($content . '/collections/site_elements/entries', 0755, true);
file_put_contents($content . '/collections/site_elements/collection.json', json_encode([
    'slug' => 'site-elements',
    'title' => 'Site Elements',
    'allow_public_read' => true,
], JSON_PRETTY_PRINT));
file_put_contents($content . '/collections/site_elements/entries/header.json', json_encode([
    'id' => 'header',
    'slug' => 'header',
    'status' => 'published',
    'title' => 'Header',
], JSON_PRETTY_PRINT));

$resolvedDir = birkly_cache_collection_entries_dir('site-elements');
assert_true(
    'cache entries dir resolves legacy underscore folder',
    is_string($resolvedDir) && str_ends_with($resolvedDir, '/site_elements/entries'),
    'got: ' . var_export($resolvedDir, true)
);

$cache = new Cache($cacheDir);
$cacheFile = $cacheDir . '/collections/site-elements/entries.json';
if (!is_dir(dirname($cacheFile))) {
    mkdir(dirname($cacheFile), 0755, true);
}
file_put_contents($cacheFile, json_encode([
    'entries' => [[
        'id' => 'stale',
        'slug' => 'stale',
        'status' => 'published',
        'title' => 'Stale',
        'content' => 'stale body',
    ]],
    'cache_time' => time() - 3600,
    'count' => 1,
], JSON_PRETTY_PRINT));
$cached = $cache->getEntries('site-elements');
assert_true(
    'stale cache invalidated when live entries dir is newer',
    is_array($cached) && ($cached[0]['slug'] ?? '') === 'header',
    json_encode($cached, JSON_UNESCAPED_SLASHES)
);

$revBefore = birkly_public_content_revision_read();
birkly_public_invalidate_collection_cache('kursseite');
$revAfter = birkly_public_content_revision_read();
assert_true('collection cache invalidation bumps public revision', (int) $revAfter > (int) $revBefore);

delete_dir($tmpRoot);
putenv('BIRKLY_DATA');
unset($GLOBALS['__birkly_data_root']);
StorageFactory::reset();

echo "\npage_bundle_new_collection_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
