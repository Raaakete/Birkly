<?php
/**
 * P2-R11 — entry slug stays stable when title changes without slug in form.
 * P29 — ID fallback and auto_slug off behavior.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../core/entry_slug.php';

test_reset_failures();

$existing = ['slug' => 'welcome-to-birkly', 'title' => 'Welcome to Birkly', 'id' => 'entry-abc'];
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

test_assert(
    'ID fallback when title empty on create',
    birkly_resolve_entry_slug('create', ['slug' => ''], null, '', 'abc123def') === 'abc123def'
);

test_assert(
    'auto_slug off preserves existing slug on title-only update',
    birkly_resolve_entry_slug(
        'update',
        ['auto_slug' => '0', 'title' => 'New Title'],
        $existing,
        'New Title',
        'entry-abc'
    ) === 'welcome-to-birkly'
);

test_assert(
    'auto_slug on regenerates from title',
    birkly_resolve_entry_slug(
        'update',
        ['auto_slug' => '1', 'slug' => 'ignored', 'title' => 'New Title'],
        $existing,
        'New Title',
        'entry-abc'
    ) === 'new-title'
);

test_assert(
    'auto_slug off uses custom slug from POST',
    birkly_resolve_entry_slug(
        'update',
        ['auto_slug' => '0', 'slug' => 'my-custom-slug'],
        $existing,
        'Any',
        'entry-abc'
    ) === 'my-custom-slug'
);

test_assert(
    'reserved field names include slug and status',
    in_array('slug', birkly_entry_reserved_field_names(), true)
    && in_array('status', birkly_entry_reserved_field_names(), true)
);

test_assert(
    'collection slug ID fallback when name empty',
    birkly_resolve_collection_slug(['slug' => ''], null, '', 'col-blog-demo') === 'col-blog-demo'
);

test_assert(
    'collection auto_slug off preserves existing slug',
    birkly_resolve_collection_slug(
        ['auto_slug' => '0', 'slug' => ''],
        ['slug' => 'blog', 'id' => 'col-blog-demo'],
        'Renamed Blog'
    ) === 'blog'
);

test_assert(
    'auto_slug checked helper defaults on for new entries',
    birkly_auto_slug_checked('', '', null, true) === true
);

test_assert(
    'auto_slug checked helper off when slug differs from title',
    birkly_auto_slug_checked('custom-slug', 'Hello World', 'abc123', false) === false
);

exit(test_finish('entry_slug_test'));
