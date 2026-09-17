<?php
/**
 * P88.1 — deploy diagnostics core + public endpoint boundary.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/birkly_paths.php';
require_once $root . '/core/deploy_health.php';
require_once $root . '/core/deploy_diagnostics.php';

$failures = 0;

function assert_true(bool $cond, string $label): void
{
    global $failures;
    if ($cond) {
        echo "OK  $label\n";
        return;
    }
    echo "FAIL $label\n";
    $failures++;
}

echo "==> Public health unchanged\n";
$public = birkly_public_deploy_health_payload();
assert_true(count($public) === 4, 'public payload has four keys');

echo "==> Verbose payload is admin-scoped structure\n";
$verbose = birkly_deploy_verbose_payload();
assert_true(isset($verbose['hosting']['cms_base_url']), 'verbose includes hosting');
assert_true(isset($verbose['volume']['installation_id']), 'verbose includes installation_id key');
assert_true(!str_contains(json_encode($verbose), 'password'), 'verbose has no password field');

echo "==> Plugin diagnostics shape\n";
$plugins = birkly_deploy_plugin_diagnostics();
assert_true(isset($plugins['plugins']) && is_array($plugins['plugins']), 'plugins key');

echo "==> admin_deploy_diagnostics.php exists\n";
assert_true(is_file($root . '/api/admin_deploy_diagnostics.php'), 'admin API file present');

exit($failures > 0 ? 1 : 0);
