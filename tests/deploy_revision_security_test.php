<?php
/**
 * P88 — deploy_revision public vs diagnostic authorization.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/birkly_paths.php';
require_once $root . '/core/birkly_data_paths.php';
require_once $root . '/core/deploy_health.php';

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

echo "==> Public deploy health payload\n";
$public = birkly_public_deploy_health_payload();
$allowedKeys = ['status', 'service', 'build_revision', 'time'];
sort($allowedKeys);
$keys = array_keys($public);
sort($keys);
assert_true($keys === $allowedKeys, 'public payload keys are allowlisted');
$encoded = json_encode($public);
assert_true(!str_contains($encoded, 'installation_id'), 'public payload has no installation_id');
assert_true(!str_contains($encoded, 'data_root'), 'public payload has no data_root');
assert_true(!str_contains(strtolower($encoded), 'secret'), 'public payload has no secret substring');
assert_true(!str_contains(strtolower($encoded), 'password'), 'public payload has no password substring');

echo "==> Diagnostic auth without secret configured\n";
$prevSecret = getenv('BIRKLY_DEPLOY_DIAG_SECRET');
if ($prevSecret !== false) {
    putenv('BIRKLY_DEPLOY_DIAG_SECRET');
}
unset($_SERVER['HTTP_X_BIRKLY_DEPLOY_SECRET'], $_SERVER['HTTP_X_BIRKLY_DEPLOY_DIAG_SECRET']);
assert_true(birkly_deploy_diag_request_authorized() === false, 'diag denied when secret unset and not local dev');

putenv('BIRKLY_DEPLOY_DIAG_SECRET=test_diag_secret_for_ci_only');
$_SERVER['HTTP_X_BIRKLY_DEPLOY_SECRET'] = 'wrong';
assert_true(birkly_deploy_diag_request_authorized() === false, 'diag denied with wrong header');
$_SERVER['HTTP_X_BIRKLY_DEPLOY_SECRET'] = 'test_diag_secret_for_ci_only';
assert_true(birkly_deploy_diag_request_authorized() === true, 'diag allowed with matching header');

if ($prevSecret !== false) {
    putenv('BIRKLY_DEPLOY_DIAG_SECRET=' . $prevSecret);
} else {
    putenv('BIRKLY_DEPLOY_DIAG_SECRET');
}

echo "==> Plugin probe in-process\n";
$probe = birkly_probe_plugin_load('not_a_real_plugin_id_xxx');
assert_true($probe['ok'] === false, 'invalid plugin id fails smoke load');

exit($failures > 0 ? 1 : 0);
