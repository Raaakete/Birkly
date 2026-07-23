<?php
/**
 * P34-M18 — Ops client URL helpers.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/platform.php';
require_once dirname(__DIR__) . '/core/platform_ops.php';

test_reset_failures();
echo "Platform Ops client tests\n\n";

$platformFile = dirname(__DIR__) . '/settings/platform.json';
$backup = sys_get_temp_dir() . '/birkly-platform-ops-test-' . getmypid() . '.json';
$hadFile = is_file($platformFile);
if ($hadFile) {
    copy($platformFile, $backup);
}

@unlink($platformFile);
birkly_platform_config_reset();
test_assert('install register derived from default relay url', birkly_ops_installations_register_url() === 'https://ops.birkly.cloud/api/ops/v1/installations/register');

file_put_contents($platformFile, json_encode([
    'feedback_relay_url' => 'https://ops.birkly.cloud/api/ops/v1/feedback',
    'feedback_relay_secret' => 'secret',
    'updates_url' => 'https://ops.birkly.cloud/api/ops/v1/releases/check',
    'release_channel' => 'beta',
], JSON_PRETTY_PRINT));
birkly_platform_config_reset();

test_assert('updates url from platform.json', birkly_ops_updates_check_url() === 'https://ops.birkly.cloud/api/ops/v1/releases/check');
test_assert('install register derived from feedback url', birkly_ops_installations_register_url() === 'https://ops.birkly.cloud/api/ops/v1/installations/register');
test_assert('release channel beta', birkly_ops_release_channel() === 'beta');
test_assert('hmac signature non-empty', birkly_ops_request_signature('{"a":1}') !== '');

putenv('BIRKLY_OPS_UPDATES_URL=https://ops.example.test/releases/check');
birkly_platform_config_reset();
file_put_contents($platformFile, json_encode(['feedback_relay_url' => ''], JSON_PRETTY_PRINT));
birkly_platform_config_reset();
test_assert('updates url from env when config empty', birkly_ops_updates_check_url() === 'https://ops.example.test/releases/check');
putenv('BIRKLY_OPS_UPDATES_URL');

if ($hadFile) {
    copy($backup, $platformFile);
} else {
    @unlink($platformFile);
}
@unlink($backup);
birkly_platform_config_reset();

exit(test_finish('platform_ops_test'));
