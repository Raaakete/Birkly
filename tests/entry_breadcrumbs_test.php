<?php
/**
 * Entry breadcrumb builder tests.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/admin/template-helpers.php';

if (!function_exists('__')) {
    function __(string $key): string
    {
        $map = [
            'admin.content' => 'Content',
            'admin.new_entry' => 'New Entry',
            'admin.untitled' => 'Untitled',
        ];

        return $map[$key] ?? $key;
    }
}

test_reset_failures();
echo "Entry breadcrumb tests\n\n";

$baseCollection = [
    'slug' => 'blog',
    'name' => 'Blog',
];

$newEntryCrumbs = build_entry_breadcrumbs([
    'collection' => $baseCollection,
    'entry' => ['title' => ''],
    'is_sub_entry' => false,
]);
test_assert('new entry ends with New Entry', ($newEntryCrumbs[2]['label'] ?? '') === 'New Entry');
test_assert('new entry has collection crumb', ($newEntryCrumbs[1]['label'] ?? '') === 'Blog');

$editEntryCrumbs = build_entry_breadcrumbs([
    'collection' => $baseCollection,
    'entry' => ['id' => 'post-1', 'title' => 'Hello World'],
    'is_sub_entry' => false,
]);
test_assert('existing entry uses title', ($editEntryCrumbs[2]['label'] ?? '') === 'Hello World');
test_assert('existing entry crumb is current', !empty($editEntryCrumbs[2]['is_current']));

$newSubEntryCrumbs = build_entry_breadcrumbs([
    'collection' => $baseCollection,
    'entry' => [
        '_parent_entry_title' => 'Parent Post',
    ],
    'is_sub_entry' => true,
    'sub_collection' => 'comments',
    'sub_entry_id' => '',
    'parent_entry_id' => 'post-1',
]);
test_assert('new sub-entry includes parent', ($newSubEntryCrumbs[2]['label'] ?? '') === 'Parent Post');
test_assert('new sub-entry ends with New Entry', str_ends_with($newSubEntryCrumbs[3]['label'] ?? '', 'New Entry'));

$nestedCrumbs = build_entry_breadcrumbs([
    'collection' => $baseCollection,
    'entry' => [
        'id' => 'reply-1',
        'title' => 'Nested Reply',
        '_parent_entry_title' => 'Parent Post',
        '_parent_sub_entry_title' => 'Parent Comment',
    ],
    'is_sub_entry' => true,
    'is_nested_sub_entry' => true,
    'sub_collection' => 'replies',
    'sub_entry_id' => 'reply-1',
    'parent_entry_id' => 'post-1',
    'parent_sub_collection' => 'comments',
    'parent_sub_entry_id' => 'comment-1',
]);
test_assert('nested breadcrumb has five items', count($nestedCrumbs) === 5);
test_assert('nested parent sub-entry crumb present', str_contains($nestedCrumbs[3]['label'] ?? '', 'Parent Comment'));

exit(test_finish('entry_breadcrumbs_test'));
