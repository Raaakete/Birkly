<?php
/**
 * plugins.php registry must survive require from inside a function (http_routes dispatch).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/birkly_paths.php';
require_once dirname(__DIR__) . '/core/http_routes.php';

$failures = 0;

function assert_true(string $name, bool $ok): void
{
    global $failures;
    echo $name . ': ' . ($ok ? 'PASS' : 'FAIL') . "\n";
    if (!$ok) {
        $failures++;
    }
}

$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTP_HOST'] = 'cms.birkly.cloud';

$handled = birkly_dispatch_http_route('/');
assert_true('dispatch root without throwing', true);
assert_true('birkly_plugins is array in GLOBALS', is_array($GLOBALS['birkly_plugins'] ?? null));
assert_true('get_all_plugins returns array', is_array(get_all_plugins()));

echo "\nplugins_global_scope_test: " . ($failures === 0 ? 'PASS' : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
