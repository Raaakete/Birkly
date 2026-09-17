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

echo "==> Plugin probe in-process\n";
$probe = birkly_probe_plugin_load('not_a_real_plugin_id_xxx');
assert_true($probe['ok'] === false, 'invalid plugin id fails smoke load');

echo "==> Legacy public ?action= gate (P88.1)\n";
$legacyOut = shell_exec(
    'cd ' . escapeshellarg($root)
    . ' && php -d variables_order=EGPCS -r '
    . escapeshellarg('$_GET["action"]="plugins"; include "api/deploy_revision.php";')
    . ' 2>/dev/null'
);
$legacyOut = trim((string) $legacyOut);
$legacy = json_decode($legacyOut, true);
assert_true(is_array($legacy) && ($legacy['status'] ?? '') === 'error', 'legacy action returns error JSON');
assert_true(str_contains($legacyOut, 'admin_deploy_diagnostics'), 'legacy action points to admin API');

exit($failures > 0 ? 1 : 0);
