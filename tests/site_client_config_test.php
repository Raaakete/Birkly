<?php
/**
 * P82-M2 — site_client_config filter aggregation tests.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/birkly_data_paths.php';

$tmpSettings = sys_get_temp_dir() . '/birkly-site-client-config-' . getmypid();
@mkdir($tmpSettings, 0775, true);
$GLOBALS['__birkly_plugin_state_settings_dir'] = $tmpSettings;
$GLOBALS['__birkly_data_root'] = null;

require_once $root . '/core/plugins.php';
require_once $root . '/core/plugins/storefront_shelf.php';

$failures = [];

function assert_true(string $label, bool $condition): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $label;
        fwrite(STDERR, "FAIL: $label\n");
        return;
    }
    echo "OK: $label\n";
}

add_filter('birkly_site_client_config', static function (array $config): array {
    return birkly_merge_site_client_config($config, [
        'auth' => [
            'check' => ['route' => 'user_auth', 'action' => 'check'],
            'login' => ['route' => 'user_auth', 'action' => 'login'],
        ],
    ]);
}, 10, 'test_plugin');

add_filter('birkly_site_client_config', static function (array $config): array {
    return birkly_merge_site_client_config($config, [
        'auth' => [
            'logout' => ['route' => 'user_auth', 'action' => 'logout'],
            'csrf' => '/api/csrf.php',
        ],
        'commerce' => ['currency' => 'eur'],
    ]);
}, 20, 'test_plugin_2');

$config = birkly_get_site_client_config();

assert_true('filter contributions merged', is_array($config));
assert_true('auth.check route present', ($config['auth']['check']['route'] ?? '') === 'user_auth');
assert_true('auth.check action present', ($config['auth']['check']['action'] ?? '') === 'check');
assert_true('auth.logout merged from second filter', ($config['auth']['logout']['action'] ?? '') === 'logout');
assert_true('csrf path preserved', ($config['auth']['csrf'] ?? '') === '/api/csrf.php');
assert_true('nested plugin keys merged', ($config['commerce']['currency'] ?? '') === 'eur');

@unlink($tmpSettings . '/plugins.json');
@rmdir($tmpSettings);

if ($failures !== []) {
    exit(1);
}

echo "site_client_config_test: OK\n";
