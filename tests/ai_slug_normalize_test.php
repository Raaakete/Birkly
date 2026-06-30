<?php
/**
 * P5.2-M2: AI slug normalization (underscores → hyphens).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/entry_slug.php';

test_reset_failures();
echo "AI slug normalization tests\n\n";

test_assert(
    'blog_posts becomes blog-posts',
    birkly_normalize_slug('blog_posts') === 'blog-posts'
);
test_assert(
    'my_entry becomes my-entry',
    birkly_normalize_slug('my_entry') === 'my-entry'
);
test_assert(
    'mixed Blog_Posts normalizes',
    birkly_normalize_slug('Blog_Posts') === 'blog-posts'
);
test_assert(
    'generate_slug applies normalization',
    birkly_generate_slug('Blog_Posts') === 'blog-posts'
);
test_assert(
    'resolve_entry_slug normalizes explicit slug',
    birkly_resolve_entry_slug('create', ['slug' => 'my_entry'], null, 'Title') === 'my-entry'
);

exit(test_finish('ai_slug_normalize_test'));
