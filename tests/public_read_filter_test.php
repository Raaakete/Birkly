<?php
/**
 * P81-M1: unified public-read filter contract across public_read, page_bundle, SSR.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/storage/StorageFactory.php';
require_once dirname(__DIR__) . '/core/public_read.php';
require_once dirname(__DIR__) . '/core/page_bundle_runner.php';
require_once dirname(__DIR__) . '/core/project_ssr.php';
require_once dirname(__DIR__) . '/core/plugins.php';

$failures = 0;
$tmpRoot = sys_get_temp_dir() . '/birkly-public-read-filter-' . bin2hex(random_bytes(4));
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

$GLOBALS['__p81_test_viewer'] = '';

add_filter('birkly_public_entries_filter', static function (array $entries, string $slug, string $context) {
    $viewer = (string) ($GLOBALS['__p81_test_viewer'] ?? '');
    return array_values(array_filter($entries, static function ($entry) use ($viewer) {
        if (!is_array($entry)) {
            return false;
        }
        $visibility = (string) ($entry['visibility'] ?? 'public');
        if ($visibility === 'private') {
            return false;
        }
        if ($visibility === 'relationship') {
            $allowed = $entry['allowed_users'] ?? [];
            return $viewer !== '' && is_array($allowed) && in_array($viewer, $allowed, true);
        }
        return true;
    }));
}, 10);

add_filter('birkly_public_entry_filter', static function ($entry, string $slug, string $context) {
    if (!is_array($entry)) {
        return null;
    }
    $filtered = apply_filters('birkly_public_entries_filter', [$entry], $slug, $context);
    return $filtered[0] ?? null;
}, 10);

mkdir($content . '/collections/posts/entries', 0755, true);
file_put_contents($content . '/collections/posts/collection.json', json_encode([
    'slug' => 'posts',
    'title' => 'Posts',
    'allow_public_read' => true,
], JSON_PRETTY_PRINT));

$seed = [
    ['id' => 'pub', 'slug' => 'pub', 'status' => 'published', 'title' => 'Public', 'visibility' => 'public'],
    ['id' => 'priv', 'slug' => 'priv', 'status' => 'published', 'title' => 'Private', 'visibility' => 'private'],
    [
        'id' => 'rel',
        'slug' => 'rel',
        'status' => 'published',
        'title' => 'Relationship',
        'visibility' => 'relationship',
        'allowed_users' => ['member-a'],
    ],
];
foreach ($seed as $row) {
    file_put_contents($content . '/collections/posts/entries/' . $row['id'] . '.json', json_encode($row, JSON_PRETTY_PRINT));
}

StorageFactory::reset();
$storage = StorageFactory::createStorage($content);

$GLOBALS['__p81_test_viewer'] = '';
$listGuest = birkly_public_list_entries($storage, 'posts');
$guestSlugs = array_map(static fn($e) => $e['slug'] ?? '', $listGuest['data']['entries'] ?? []);
assert_true('guest list hides private and relationship entries', $guestSlugs === ['pub'], json_encode($guestSlugs));

$getPrivate = birkly_public_get_entry($storage, 'posts', 'priv');
assert_true('guest get private entry returns error', ($getPrivate['status'] ?? '') === 'error');

$GLOBALS['__p81_test_viewer'] = 'member-a';
$listMember = birkly_public_list_entries($storage, 'posts');
$memberSlugs = array_map(static fn($e) => $e['slug'] ?? '', $listMember['data']['entries'] ?? []);
assert_true('connected member sees relationship entry on list', in_array('rel', $memberSlugs, true) && !in_array('priv', $memberSlugs, true), json_encode($memberSlugs));

$getRel = birkly_public_get_entry($storage, 'posts', 'rel');
assert_true('connected member can get relationship entry', ($getRel['status'] ?? '') === 'ok' && ($getRel['data']['entry']['slug'] ?? '') === 'rel');

$bundle = birkly_run_page_bundle_requests($storage, [
    ['type' => 'list_entries', 'into' => 'posts', 'collection' => 'posts'],
    ['type' => 'get_entry', 'into' => 'one', 'collection' => 'posts', 'slug' => 'rel'],
], 'en', ['trusted_ssr' => true]);
$bundleSlugs = array_map(static fn($e) => $e['slug'] ?? '', $bundle['data']['posts'] ?? []);
assert_true('page_bundle list uses filter hook path', $bundleSlugs === ['pub', 'rel'], json_encode($bundleSlugs));
assert_true('page_bundle get uses filter hook path', is_array($bundle['data']['one'] ?? null) && ($bundle['data']['one']['slug'] ?? '') === 'rel');

$html = '{for each entry in \'posts\'}{slug}{endfor}';
$ssrData = birkly_project_ssr_build_data($html, [], 'en', $storage, [
    ['type' => 'list_entries', 'into' => 'posts', 'collection' => 'posts'],
]);
$ssrSlugs = array_map(static fn($e) => $e['slug'] ?? '', $ssrData['posts'] ?? []);
assert_true('SSR data path inherits filtered list_entries helper', $ssrSlugs === ['pub', 'rel'], json_encode($ssrSlugs));

delete_dir($tmpRoot);
StorageFactory::reset();

echo "\npublic_read_filter_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
