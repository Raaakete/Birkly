<?php
/**
 * P102-M4 — anonymous sub-entry reads follow the page-bundle gate.
 * Missing status stays visible. Explicit drafts stay hidden unless an admin session is reading.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/storage/StorageFactory.php';
require_once dirname(__DIR__) . '/core/public_read.php';

$failures = 0;
$tmpRoot = sys_get_temp_dir() . '/birkly-p102-sub-' . bin2hex(random_bytes(4));
$content = $tmpRoot . '/content';

function p102_sub_assert(string $name, bool $ok, string $detail = ''): void
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

function p102_sub_delete(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            p102_sub_delete($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

$_SERVER['HTTP_HOST'] = 'cms.example.com';
$_SERVER['HTTP_ORIGIN'] = 'https://cms.example.com';

mkdir($content . '/collections/notes/entries/note-1/sub_entries/comments', 0755, true);
file_put_contents($content . '/collections/notes/collection.json', json_encode([
    'slug' => 'notes',
    'allow_public_read' => true,
]));
file_put_contents($content . '/collections/notes/entries/note-1.json', json_encode([
    'id' => 'note-1',
    'status' => 'published',
    'title' => 'Public note',
]));
file_put_contents($content . '/collections/notes/entries/note-1/sub_entries/comments/visible.json', json_encode([
    'id' => 'visible',
    'status' => 'published',
    'body' => 'shown',
]));
file_put_contents($content . '/collections/notes/entries/note-1/sub_entries/comments/legacy.json', json_encode([
    'id' => 'legacy',
    'body' => 'no status field',
]));
file_put_contents($content . '/collections/notes/entries/note-1/sub_entries/comments/secret.json', json_encode([
    'id' => 'secret',
    'status' => 'draft',
    'body' => 'hidden',
]));
file_put_contents($content . '/collections/notes/entries/draft-note.json', json_encode([
    'id' => 'draft-note',
    'status' => 'draft',
    'title' => 'Draft note',
]));
file_put_contents($content . '/collections/notes/entries/unstated-note.json', json_encode([
    'id' => 'unstated-note',
    'title' => 'Missing parent status',
]));

mkdir($content . '/collections/private-notes/entries/secret-parent', 0755, true);
file_put_contents($content . '/collections/private-notes/collection.json', json_encode([
    'slug' => 'private-notes',
    'allow_public_read' => false,
]));
file_put_contents($content . '/collections/private-notes/entries/secret-parent.json', json_encode([
    'id' => 'secret-parent',
    'status' => 'published',
    'title' => 'Private parent',
]));

StorageFactory::reset();
$storage = StorageFactory::createStorage($content);

$publicParent = birkly_sub_entries_anonymous_parent_visible($storage, 'notes', 'note-1');
p102_sub_assert('published parent on a public collection stays readable', ($publicParent['ok'] ?? false) === true, json_encode($publicParent));

$draftParent = birkly_sub_entries_anonymous_parent_visible($storage, 'notes', 'draft-note');
p102_sub_assert('draft parent is not public', ($draftParent['ok'] ?? true) === false && ($draftParent['http'] ?? 0) === 404, json_encode($draftParent));

$unstatedParent = birkly_sub_entries_anonymous_parent_visible($storage, 'notes', 'unstated-note');
p102_sub_assert('parent without status is not public', ($unstatedParent['ok'] ?? true) === false && ($unstatedParent['http'] ?? 0) === 404, json_encode($unstatedParent));

$privateParent = birkly_sub_entries_anonymous_parent_visible($storage, 'private-notes', 'secret-parent');
p102_sub_assert('collection without public read is denied before local-dev bypass', ($privateParent['ok'] ?? true) === false && ($privateParent['http'] ?? 0) === 403, json_encode($privateParent));

$rows = [
    ['id' => 'visible', 'status' => 'published', 'body' => 'shown'],
    ['id' => 'legacy', 'body' => 'no status field'],
    ['id' => 'secret', 'status' => 'draft', 'body' => 'hidden'],
];
$guest = birkly_sub_entries_for_reader($rows, false);
$guestIds = array_column($guest, 'id');
p102_sub_assert('anonymous reader keeps published and status-less rows', $guestIds === ['visible', 'legacy'], json_encode($guestIds));

$admin = birkly_sub_entries_for_reader($rows, true);
$adminIds = array_column($admin, 'id');
p102_sub_assert('admin session still receives drafts', $adminIds === ['visible', 'legacy', 'secret'], json_encode($adminIds));

$api = (string) file_get_contents(dirname(__DIR__) . '/api/sub_entries.php');
p102_sub_assert('public list uses the reader filter', str_contains($api, 'birkly_sub_entries_for_reader'));
p102_sub_assert('anonymous requests use the page-bundle parent gate', str_contains($api, 'birkly_sub_entries_anonymous_parent_visible'));
p102_sub_assert('POST actions are still outside the public GET list', str_contains($api, "\$method === 'GET'"));

p102_sub_delete($tmpRoot);

if ($failures > 0) {
    fwrite(STDERR, "p102_sub_entries_visibility_test failed ($failures)\n");
    exit(1);
}

echo "p102_sub_entries_visibility_test ok\n";
