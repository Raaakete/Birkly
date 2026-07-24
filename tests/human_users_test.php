<?php
/**
 * Human user listing filters onboarding metadata and AI connection files.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/birkly_users.php';

$tmpdir = sys_get_temp_dir() . '/birkly_human_users_' . bin2hex(random_bytes(4));
@mkdir($tmpdir, 0755, true);

$failures = 0;
function assert_true(string $name, bool $ok): void
{
    global $failures;
    echo $name . ': ' . ($ok ? 'PASS' : 'FAIL') . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

$humanId = md5('admin@example.com');
file_put_contents($tmpdir . '/' . $humanId . '.json', json_encode([
    'id' => $humanId,
    'email' => 'admin@example.com',
    'name' => 'Admin',
    'role' => 'admin',
]));
file_put_contents($tmpdir . '/' . $humanId . '_onboarding.json', json_encode([
    'step' => 'done',
]));
file_put_contents($tmpdir . '/ai_deadbeef.json', json_encode([
    'id' => 'ai_deadbeef',
    'type' => 'ai',
    'name' => 'OpenAI',
]));

// Monkey-patch search dirs for test via reflection is heavy; test helpers directly.
assert_true('human id pattern matches md5 file', birkly_is_human_user_id($humanId));
assert_true('onboarding suffix rejected', !birkly_is_human_user_id($humanId . '_onboarding'));
assert_true('ai id rejected', !birkly_is_human_user_id('ai_deadbeef'));
assert_true('onboarding filename rejected', !birkly_is_user_account_filename($humanId . '_onboarding.json'));
assert_true('ai connection filename rejected', !birkly_is_user_account_filename('ai_deadbeef.json'));
assert_true('ai record rejected', !birkly_is_human_user_record([
    'id' => 'ai_deadbeef',
    'email' => 'openai@ai.birkly',
    'type' => 'ai',
    'name' => 'OpenAI',
    'role' => 'admin',
]));
assert_true('human record accepted', birkly_is_human_user_record([
    'id' => $humanId,
    'email' => 'admin@example.com',
    'password' => 'hashed',
    'role' => 'admin',
]));
assert_true('onboarding-like record rejected without password', !birkly_is_human_user_record([
    'step' => 'done',
]));

@unlink($tmpdir . '/' . $humanId . '.json');
@unlink($tmpdir . '/' . $humanId . '_onboarding.json');
@unlink($tmpdir . '/ai_deadbeef.json');
@rmdir($tmpdir);

echo PHP_EOL . 'human_users_test: ' . ($failures === 0 ? 'PASS' : "FAIL ($failures)") . PHP_EOL;
exit($failures === 0 ? 0 : 1);
