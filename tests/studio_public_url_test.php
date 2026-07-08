<?php
/**
 * Studio public URL helper tests.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/plugins/studio/init.php';

test_reset_failures();
echo "Studio public URL tests\n\n";

$_SERVER['HTTPS'] = 'on';
$_SERVER['HTTP_HOST'] = 'example.com';

$url = studio_public_site_url('my-site', false);
test_assert('published url has host', str_contains($url, 'https://example.com'));
test_assert('published url has site path', str_contains($url, '/site/my-site'));

$view = studio_view_url('my-site', false);
test_assert('view url uses studio_view endpoint', str_contains($view, '/api/studio_view.php'));
test_assert('view url includes slug', str_contains($view, 'slug=my-site'));

$preview = studio_public_site_url('my-site', true);
test_assert('preview has query flag', str_contains($preview, 'birkly_studio_preview=1'));

$previewView = studio_view_url('my-site', true);
test_assert('view preview has query flag', str_contains($previewView, 'birkly_studio_preview=1'));

exit(test_finish('studio_public_url_test'));
