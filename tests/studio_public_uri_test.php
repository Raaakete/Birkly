<?php
/**
 * Studio public URI resolution (LiteSpeed rewrite compatibility).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/plugins/studio/includes/studio_public_uri.php';

test_reset_failures();
echo "Studio public URI tests\n\n";

$_GET = ['slug' => 'test-project'];
test_assert('slug query maps to site path', studio_resolve_public_uri() === '/site/test-project/');

$_GET = ['slug' => 'test-project', 'page' => 'about'];
test_assert('page query maps to inner path', studio_resolve_public_uri() === '/site/test-project/about/');

$_GET = ['slug' => 'test-project', 'page' => 'styles.css'];
test_assert('styles query maps to css path', studio_resolve_public_uri() === '/site/test-project/styles.css');

$_GET = [];
$_SERVER['REQUEST_URI'] = '/api/studio_public.php';
$_SERVER['REDIRECT_URL'] = '/site/demo/';
test_assert('redirect url wins over script path', studio_resolve_public_uri() === '/site/demo/');

unset($_SERVER['REDIRECT_URL']);
$_SERVER['REQUEST_URI'] = '/site/from-request/';
test_assert('request uri used when no slug query', studio_resolve_public_uri() === '/site/from-request/');

exit(test_finish('studio_public_uri_test'));
