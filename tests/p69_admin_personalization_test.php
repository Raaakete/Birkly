<?php
/**
 * P69 — admin personalization & access control tests.
 */

require_once __DIR__ . '/../core/ui_density.php';
require_once __DIR__ . '/../core/security_policy.php';
require_once __DIR__ . '/../core/roles.php';
require_once __DIR__ . '/../core/ai_tool_registry.php';

function p69_assert(string $label, bool $ok): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "OK: {$label}\n";
}

p69_assert('density normalize large', birkly_ui_density_normalize('LARGE') === 'large');
p69_assert('density normalize invalid', birkly_ui_density_normalize('huge') === 'default');

p69_assert('ai tools mode normalize', birkly_ai_tools_mode_normalize('read_only_chat') === 'read_only_chat');
p69_assert('ai tools restricted when not normal', birkly_ai_tools_mode_normalize('disabled') === 'disabled');

$role = [
    'id' => 'editor',
    'plugin_access' => ['mode' => 'all_except', 'deny' => ['commerce']],
];
p69_assert('plugin denied', !birkly_role_plugin_allowed('editor', 'commerce'));
p69_assert('plugin allowed', birkly_role_plugin_allowed('editor', 'automation'));
p69_assert('plugin all mode', birkly_role_plugin_allowed('editor', 'commerce'));

$GLOBALS['__birkly_security_settings_cache'] = ['ai_tools_mode' => 'read_only_chat'];
p69_assert('tools empty when restricted', birkly_ai_tools_for_user(['role' => 'admin']) === []);

echo "P69 tests passed.\n";
