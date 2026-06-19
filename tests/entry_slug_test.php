<?php
/**
 * P2-R11 — entry slug stays stable when title changes without slug in form.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../core/entry_slug.php';

test_reset_failures();

$existing = ['slug' => 'welcome-to-birkly', 'title' => 'Welcome to Birkly'];
$updatedTitle = ['title' => 'Review Title Test'];

test_assert(
    'update preserves slug when slug omitted',
    birkly_resolve_entry_slug('update', $updatedTitle, $existing, 'Review Title Test') === 'welcome-to-birkly'
);

test_assert(
    'create generates slug from title',
    birkly_resolve_entry_slug('create', $updatedTitle, null, 'Review Title Test') === 'review-title-test'
);

test_assert(
    'explicit slug in form wins',
    birkly_resolve_entry_slug('update', ['slug' => 'custom-slug', 'title' => 'Any'], $existing, 'Any') === 'custom-slug'
);

test_assert(
    'empty slug on create still generates',
    birkly_resolve_entry_slug('create', ['slug' => ''], null, 'Hello World') === 'hello-world'
);

exit(test_finish('entry_slug_test'));
