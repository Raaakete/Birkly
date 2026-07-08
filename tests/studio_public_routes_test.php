<?php
/**
 * Plugin public routes shelf tests (P22-M1).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/plugin_public_routes.php';

test_reset_failures();
echo "Plugin public routes tests\n\n";

$called = false;
register_public_route('/site/', function (string $uri) use (&$called) {
    if (str_starts_with($uri, '/site/')) {
        $called = true;
        return true;
    }
    return false;
}, ['plugin_id' => 'test', 'priority' => 10]);

test_assert('dispatch handles site uri', birkly_dispatch_public_routes('/site/demo/') === true);
test_assert('handler was called', $called === true);
test_assert('unknown uri not handled', birkly_dispatch_public_routes('/other/') === false);

exit(test_finish('studio_public_routes_test'));
