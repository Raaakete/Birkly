<?php
/**
 * P2-M6-T3: Public API collection metadata normalization tests.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/collection_meta.php';

test_reset_failures();
echo "Public collection metadata tests\n\n";

$meta = birkly_normalize_collection_meta(['name' => 'Blog', 'slug' => 'blog'], 'blog');
test_assert('title from name', $meta['title'] === 'Blog');
test_assert('id fallback', $meta['id'] === 'col-blog');
test_assert('slug preserved', $meta['slug'] === 'blog');

$meta = birkly_normalize_collection_meta([
    'id' => 'col-contact',
    'name' => 'Contact',
    'slug' => 'contact',
], 'contact');
test_assert('explicit id kept', $meta['id'] === 'col-contact');
test_assert('contact title', $meta['title'] === 'Contact');

$meta = birkly_normalize_collection_meta(['title' => 'Services', 'slug' => 'services', 'id' => 'col-services']);
test_assert('title field preferred', $meta['title'] === 'Services');

exit(test_finish('public_collection_meta_test'));
