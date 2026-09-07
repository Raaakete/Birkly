<?php
/**
 * P81-M3: nested sub-collections in template engine, page bundle, and public_read helpers.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/storage/StorageFactory.php';
require_once dirname(__DIR__) . '/core/public_read.php';
require_once dirname(__DIR__) . '/core/page_bundle_runner.php';
require_once dirname(__DIR__) . '/core/birkly_template_engine.php';
require_once dirname(__DIR__) . '/core/project_ssr.php';

$failures = 0;
$tmpRoot = sys_get_temp_dir() . '/birkly-nested-subs-' . bin2hex(random_bytes(4));
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

mkdir($content . '/collections/posts/entries/post1/sub_entries/comments/c1/sub_entries/replies', 0755, true);
file_put_contents($content . '/collections/posts/collection.json', json_encode([
    'slug' => 'posts',
    'title' => 'Posts',
    'allow_public_read' => true,
    'sub_collections' => [
        [
            'slug' => 'comments',
            'sub_collections' => [
                ['slug' => 'replies', 'parent_sub_collection' => 'comments'],
            ],
        ],
    ],
], JSON_PRETTY_PRINT));
file_put_contents($content . '/collections/posts/entries/post1.json', json_encode([
    'id' => 'post1',
    'slug' => 'post1',
    'status' => 'published',
    'title' => 'Post',
], JSON_PRETTY_PRINT));
file_put_contents($content . '/collections/posts/entries/post1/sub_entries/comments/c1.json', json_encode([
    'id' => 'c1',
    'title' => 'Comment',
    'status' => 'published',
], JSON_PRETTY_PRINT));
file_put_contents($content . '/collections/posts/entries/post1/sub_entries/comments/c1/sub_entries/replies/r1.json', json_encode([
    'id' => 'r1',
    'title' => 'Reply',
    'body' => 'Nested reply',
    'status' => 'published',
], JSON_PRETTY_PRINT));

StorageFactory::reset();
$storage = StorageFactory::createStorage($content);

$nested = birkly_public_list_sub_entries('posts', 'post1', 'replies', 'comments', 'c1', $storage);
$nestedBodies = array_map(static fn($e) => $e['body'] ?? '', $nested['data']['entries'] ?? []);
assert_true('public_read nested sub_entries helper loads replies', in_array('Nested reply', $nestedBodies, true), json_encode($nested));

$bundleKey = birkly_public_sub_entries_bundle_key('posts', 'post1', 'replies', 'comments', 'c1');
$bundle = birkly_run_page_bundle_requests($storage, [[
    'type' => 'sub_entries',
    'into' => $bundleKey,
    'parent_collection' => 'posts',
    'parent_entry_id' => 'post1',
    'parent_sub_collection' => 'comments',
    'parent_sub_entry_id' => 'c1',
    'sub_collection' => 'replies',
]], 'en', ['trusted_ssr' => true]);
assert_true(
    'page_bundle nested sub_entries request returns replies',
    count($bundle['data'][$bundleKey] ?? []) === 1,
    json_encode($bundle, JSON_UNESCAPED_SLASHES)
);

$data = [
    'entry' => ['id' => 'post1', 'slug' => 'post1', 'title' => 'Post'],
    'query' => ['collection' => 'posts', 'slug' => 'post1'],
    'comment' => ['id' => 'c1', 'slug' => 'c1'],
    $bundleKey => [
        ['body' => 'Nested reply'],
    ],
];
$html = "{for each reply in 'replies' of 'comments'.{comment.id}}<r>{'body'}</r>{endfor}";
$rendered = birkly_template_engine_render($html, $data);
assert_true('template engine renders nested sub-collection loop', str_contains($rendered, 'Nested reply'), $rendered);

$ssrHtml = "{for each comment in 'comments' of 'posts'.current}{for each reply in 'replies' of 'comments'.{comment.id}}<r>{'body'}</r>{endfor}{endfor}";
$ssrData = birkly_project_ssr_build_data($ssrHtml, ['collection' => 'posts', 'slug' => 'post1'], 'en', $storage);
$ssrRendered = birkly_template_engine_render($ssrHtml, $ssrData);
assert_true('SSR build loads nested subs for post detail template', str_contains($ssrRendered, 'Nested reply'), $ssrRendered);

delete_dir($tmpRoot);
StorageFactory::reset();

echo "\nnested_sub_collections_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
