<?php
/**
 * P20-M1: page_bundle runner regression tests.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/storage/StorageFactory.php';
require_once dirname(__DIR__) . '/api/domain_auth.php';
require_once dirname(__DIR__) . '/core/page_bundle_runner.php';

$root = dirname(__DIR__);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
$storage = get_storage($root . '/demo-content/blog');

test_reset_failures();
echo "Page bundle runner tests\n\n";

$listResult = birkly_run_page_bundle_requests($storage, [
    ['type' => 'list_entries', 'into' => 'blog', 'collection' => 'blog'],
    ['type' => 'list_entries', 'into' => 'site_elements', 'collection' => 'site_elements'],
], 'en');

test_assert('list_entries status ok', ($listResult['status'] ?? '') === 'ok');
test_assert('revision present', !empty($listResult['revision']));
test_assert('blog key present', isset($listResult['data']['blog']));
test_assert('blog is array', is_array($listResult['data']['blog'] ?? null));
test_assert('site_elements key present', isset($listResult['data']['site_elements']));

$slug = null;
foreach ($listResult['data']['blog'] as $entry) {
    if (!empty($entry['slug'])) {
        $slug = $entry['slug'];
        break;
    }
}
test_assert('found blog slug for get_entry test', $slug !== null, 'slug: ' . (string) $slug);

if ($slug) {
    $into = '__entry__:blog:' . $slug;
    $getResult = birkly_run_page_bundle_requests($storage, [
        ['type' => 'get_entry', 'into' => $into, 'collection' => 'blog', 'slug' => $slug],
    ], 'en');
    test_assert('get_entry status ok', ($getResult['status'] ?? '') === 'ok');
    test_assert('get_entry returns entry', is_array($getResult['data'][$into] ?? null));
    test_assert('get_entry slug matches', ($getResult['data'][$into]['slug'] ?? '') === $slug);
}

$mixed = birkly_run_page_bundle_requests($storage, [
    ['type' => 'list_entries', 'into' => 'services', 'collection' => 'services'],
    ['type' => 'get_entry', 'into' => '__entry__:blog:missing-slug-xyz', 'collection' => 'blog', 'slug' => 'missing-slug-xyz'],
], 'en');
test_assert('mixed bundle status ok', ($mixed['status'] ?? '') === 'ok');
test_assert('services listed', is_array($mixed['data']['services'] ?? null));

exit(test_finish('page_bundle_test'));
