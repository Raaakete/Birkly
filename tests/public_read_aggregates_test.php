<?php
/**
 * P81-M2: schema-driven sub-entry aggregates on public list/get + page_bundle.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/storage/StorageFactory.php';
require_once dirname(__DIR__) . '/core/public_read.php';
require_once dirname(__DIR__) . '/core/page_bundle_runner.php';

$failures = 0;
$tmpRoot = sys_get_temp_dir() . '/birkly-public-read-agg-' . bin2hex(random_bytes(4));
$content = $tmpRoot . '/content';

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

mkdir($content . '/collections/articles/entries/post1/sub_entries/comments', 0755, true);
file_put_contents($content . '/collections/articles/collection.json', json_encode([
    'slug' => 'articles',
    'title' => 'Articles',
    'allow_public_read' => true,
    'sub_collections' => [
        [
            'slug' => 'comments',
            'aggregate_on_parent' => ['count_as' => 'comments_count'],
        ],
    ],
], JSON_PRETTY_PRINT));
file_put_contents($content . '/collections/articles/entries/post1.json', json_encode([
    'id' => 'post1',
    'slug' => 'post1',
    'status' => 'published',
    'title' => 'Post',
], JSON_PRETTY_PRINT));
file_put_contents($content . '/collections/articles/entries/post1/sub_entries/comments/c1.json', json_encode([
    'id' => 'c1',
    'title' => 'Comment 1',
    'status' => 'published',
], JSON_PRETTY_PRINT));
file_put_contents($content . '/collections/articles/entries/post1/sub_entries/comments/c2.json', json_encode([
    'id' => 'c2',
    'title' => 'Comment 2',
    'status' => 'published',
], JSON_PRETTY_PRINT));

StorageFactory::reset();
$storage = StorageFactory::createStorage($content);

$list = birkly_public_list_entries($storage, 'articles');
$entry = $list['data']['entries'][0] ?? [];
assert_true('list includes schema-driven comments_count', ($entry['comments_count'] ?? null) === 2, json_encode($entry));

$get = birkly_public_get_entry($storage, 'articles', 'post1');
$one = $get['data']['entry'] ?? [];
assert_true('get includes schema-driven comments_count', ($one['comments_count'] ?? null) === 2, json_encode($one));

$bundle = birkly_run_page_bundle_requests($storage, [
    ['type' => 'list_entries', 'into' => 'articles', 'collection' => 'articles'],
], 'en', ['trusted_ssr' => true]);
$bundleEntry = $bundle['data']['articles'][0] ?? [];
assert_true('page_bundle list includes aggregates', ($bundleEntry['comments_count'] ?? null) === 2, json_encode($bundleEntry));

$publicPhp = file_get_contents(dirname(__DIR__) . '/api/public.php');
assert_true('public.php has no hardcoded club-posts slug', strpos($publicPhp, 'club-posts') === false);
assert_true('public.php has no hardcoded social_posts slug', strpos($publicPhp, 'social_posts') === false);

delete_dir($tmpRoot);
StorageFactory::reset();

echo "\npublic_read_aggregates_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
