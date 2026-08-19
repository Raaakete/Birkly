<?php

declare(strict_types=1);

$testRoot = sys_get_temp_dir() . '/birkly-mcp-sanitize-' . uniqid();
mkdir($testRoot . '/settings', 0755, true);
$GLOBALS['__birkly_project_root'] = $testRoot;

require_once dirname(__DIR__) . '/core/ai_mcp_sanitize.php';

$failures = 0;

function assert_true(string $name, bool $ok): void
{
    global $failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
    }
}

file_put_contents($testRoot . '/settings/security.json', json_encode(['encryption_key' => 'secret123', 'session_timeout' => 3600]));
$redacted = birkly_ai_mcp_redact_settings_response([
    'settings' => ['security' => ['encryption_key' => 'secret123', 'session_timeout' => 3600]],
]);
assert_true('encryption_key redacted', ($redacted['settings']['security']['encryption_key'] ?? '') === '[redacted]');
assert_true('session_timeout preserved', ($redacted['settings']['security']['session_timeout'] ?? null) === 3600);

assert_true('unknown category rejected', birkly_ai_validate_mcp_update_settings(['category' => 'general', 'data' => ['x' => 1]]) !== null);
assert_true('unknown key rejected', birkly_ai_validate_mcp_update_settings(['category' => 'security', 'data' => ['bogus' => 1]]) !== null);

file_put_contents($testRoot . '/settings/general.json', '{"bogus":1}');
birkly_ai_mcp_cleanup_stray_settings();
assert_true('general.json removed', !is_file($testRoot . '/settings/general.json'));

exit($failures > 0 ? 1 : 0);
