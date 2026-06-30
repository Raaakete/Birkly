<?php
/**
 * P5.4-M2 — Platform install flags (feedback model).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/platform.php';

test_reset_failures();
echo "Platform config tests\n\n";

$platformFile = dirname(__DIR__) . '/settings/platform.json';
$backup = sys_get_temp_dir() . '/birkly-platform-test-' . getmypid() . '.json';
$hadFile = is_file($platformFile);
if ($hadFile) {
    copy($platformFile, $backup);
}

@unlink($platformFile);
birkly_platform_config_reset();
test_assert('alpha enabled by default without platform.json', birkly_alpha_program_enabled() === true);
test_assert('not owner without platform.json', birkly_is_platform_owner_install() === false);

file_put_contents($platformFile, json_encode([
    'owner_mode' => true,
    'alpha_program' => false,
    'install_id' => 'test-install',
    'feedback_relay_url' => 'https://example.com/feedback',
], JSON_PRETTY_PRINT));
birkly_platform_config_reset();

test_assert('owner_mode detected', birkly_is_platform_owner_install() === true);
test_assert('alpha off when owner_mode and alpha_program false', birkly_alpha_program_enabled() === false);
test_assert('install id from config', birkly_platform_install_id() === 'test-install');
test_assert('relay url from config', birkly_feedback_relay_url() === 'https://example.com/feedback');

if ($hadFile) {
    copy($backup, $platformFile);
} else {
    @unlink($platformFile);
}
@unlink($backup);
birkly_platform_config_reset();

exit(test_finish('platform_test'));
