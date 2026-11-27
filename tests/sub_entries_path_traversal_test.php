<?php

declare(strict_types=1);

/**
 * Sub-entry path traversal: core validation, storage guard, public visibility, MCP bulk delete.
 * Run: php tests/sub_entries_path_traversal_test.php
 */

$root = dirname(__DIR__);
require_once $root . '/core/storage/StorageFactory.php';
require_once $root . '/core/storage/FlatFileStorage.php';
require_once $root . '/core/storage/safe_path.php';
require_once $root . '/core/sub_entries.php';
require_once $root . '/core/public_read.php';

$failures = 0;

function se_assert(string $name, bool $ok, string $detail = ''): void
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

function se_rmtree(string $dir): void
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
            se_rmtree($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

$tmpRoot = sys_get_temp_dir() . '/birkly-sub-path-' . bin2hex(random_bytes(4));
$content = $tmpRoot . '/content';
mkdir($content . '/collections/blog/entries/post-1/sub_entries/comments', 0755, true);
mkdir($tmpRoot . '/users', 0755, true);

file_put_contents($content . '/collections/blog/collection.json', json_encode([
    'slug' => 'blog',
    'allow_public_read' => true,
]));
file_put_contents($content . '/collections/blog/entries/post-1.json', json_encode([
    'id' => 'post-1',
    'status' => 'published',
    'title' => 'Post',
]));
file_put_contents($content . '/collections/blog/entries/post-1/sub_entries/comments/c1.json', json_encode([
    'id' => 'c1',
    'status' => 'published',
    'body' => 'ok',
]));
file_put_contents($tmpRoot . '/users/admin.json', json_encode([
    'id' => 'admin',
    'email' => 'admin@test',
    'password' => 'hashed-secret',
]));

StorageFactory::reset();
get_storage($content);
$storage = new FlatFileStorage($content);

// --- Reproduction: traversal must not read outside content/ ---
$escaped = $storage->read('collections/blog/entries/../../../users/admin.json');
se_assert('FlatFileStorage blocks traversal read outside content', $escaped === null);

$traversalList = $storage->list('collections/blog/entries/../../../users');
se_assert('FlatFileStorage blocks traversal list outside content', $traversalList === []);

try {
    getSubEntry('blog', '../../../users', 'admin', 'admin');
    se_assert('getSubEntry rejects traversal parent_entry_id', false);
} catch (InvalidArgumentException $e) {
    se_assert('getSubEntry rejects traversal parent_entry_id', true);
}

try {
    getSubEntries('blog', 'post-1', '../../users');
    se_assert('getSubEntries rejects traversal sub_collection', false);
} catch (InvalidArgumentException $e) {
    se_assert('getSubEntries rejects traversal sub_collection', true);
}

$leak = getSubEntry('blog', 'post-1', 'comments', 'c1');
se_assert('normal getSubEntry still works', is_array($leak) && ($leak['body'] ?? '') === 'ok');

$publishedOnly = birkly_sub_entries_for_reader([
    ['id' => 'a', 'status' => 'published'],
    ['id' => 'b', 'body' => 'no status'],
], false);
se_assert(
    'missing sub-entry status stays public when path is valid',
    count($publishedOnly) === 2
);

$smartSrc = (string) file_get_contents($root . '/api/smart404.php');
se_assert(
    'smart404 getCollectionSlugs uses storage guard',
    str_contains($smartSrc, 'get_storage()') && str_contains($smartSrc, 'birkly_validate_content_slug')
);

$handlerSrc = (string) file_get_contents($root . '/core/cms_mcp_handlers.php');
se_assert('MCP set_order targets sub_entries/', str_contains($handlerSrc, '/sub_entries/') && !str_contains($handlerSrc, '/sub_collections/'));

StorageFactory::reset();
$storageWithBase = StorageFactory::createStorage($content);
$stillWorks = $storageWithBase->read('collections/blog/entries/post-1/sub_entries/comments/c1.json');
se_assert('FlatFileStorage still reads .order-compatible paths', is_array($stillWorks));

se_rmtree($tmpRoot);

if ($failures > 0) {
    fwrite(STDERR, "sub_entries_path_traversal_test failed ($failures)\n");
    exit(1);
}

echo "sub_entries_path_traversal_test ok\n";