<?php
/**
 * P70 — MCP verification matrix (automated subset V3, V7–V9, V11, V13 partial).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/mcp_protocol.php';
require_once dirname(__DIR__) . '/core/cms_mcp_admin.php';
require_once dirname(__DIR__) . '/core/ai_tool_registry.php';
require_once dirname(__DIR__) . '/api/storage.php';

$failures = 0;

function p70_assert(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '  ' . $detail . "\n";
        }
    }
}

// V3 — capabilities_version on initialize
$init = birkly_mcp_handle_initialize(['role' => 'admin']);
p70_assert(
    'V3 initialize capabilitiesVersion',
    ($init['serverInfo']['capabilitiesVersion'] ?? '') === BIRKLY_MCP_CAPABILITIES_VERSION
);

// V7 — built-in assistant approval copy (no personal connector toggle reference)
$adminUser = [
    'id' => 'ai_chat_1',
    'role' => 'admin',
    'require_approval' => true,
];
$result = birkly_ai_dispatch_tool_call('connections_configure', [
    'operation' => 'create',
    'connection' => [
        'type' => 'email',
        'provider' => 'log',
        'alias' => 'p70-gated',
        'name' => 'Gated',
        'from_email' => 'test@example.com',
    ],
], $adminUser, 'chat');
$approvalText = $result['content'][0]['text'] ?? '';
p70_assert('V7 approval mentions built-in assistant', str_contains($approvalText, 'built-in assistant'));
p70_assert('V7 approval no personal connector toggle', !str_contains(strtolower($approvalText), 'personal connector'));
p70_assert('V7 approval no ask before changes on connector', !str_contains(strtolower($approvalText), 'ask before changes'));

// MCP source ignores require_approval (M3)
$mcpUser = $adminUser;
$mcpUser['require_approval'] = true;
$testRoot = sys_get_temp_dir() . '/birkly-p70-mcp-' . uniqid();
mkdir($testRoot . '/settings', 0755, true);
mkdir($testRoot . '/logs', 0755, true);
$GLOBALS['__birkly_project_root'] = $testRoot;
putenv('BIRKLY_SECRETS_KEY=' . bin2hex(random_bytes(32)));

$mcpResult = birkly_ai_dispatch_tool_call('connections_health', [], $mcpUser, 'mcp');
$mcpText = $mcpResult['content'][0]['text'] ?? '';
$mcpData = json_decode($mcpText, true);
p70_assert('V13 MCP require_approval does not queue', !str_contains($mcpText, 'approval ID'));
p70_assert('V13 connections_health ok for admin MCP', is_array($mcpData) && ($mcpData['status'] ?? '') === 'ok');

// V8 — editor: media write yes, settings.save no
$editor = ['id' => 'ed1', 'role' => 'editor', 'email' => 'ed@test.local', 'name' => 'Editor'];
$mediaUpdate = cms_mcp_dispatch_admin('media.update', ['id' => 'noop', 'title' => 'x'], $editor);
p70_assert(
    'V8 editor media.update not permission denied',
    ($mediaUpdate['status'] ?? '') !== 'error' || !str_contains((string) ($mediaUpdate['message'] ?? ''), 'Permission denied')
);
$settingsSave = cms_mcp_dispatch_admin('settings.save', ['category' => 'site', 'settings' => []], $editor);
p70_assert('V8 editor settings.save denied', ($settingsSave['status'] ?? '') === 'error');
p70_assert(
    'V8 editor settings.save permission message',
    str_contains((string) ($settingsSave['message'] ?? ''), 'Permission denied')
);

// V9 — editor with commerce denied: no commerce_* tools (P69 plugin_access)
file_put_contents(
    $testRoot . '/settings/roles.json',
    json_encode([
        [
            'id' => 'editor',
            'name' => 'Editor',
            'plugin_access' => ['mode' => 'all_except', 'deny' => ['commerce']],
        ],
    ], JSON_THROW_ON_ERROR)
);
$tools = birkly_ai_tools_for_user(['role' => 'editor']);
$commerceTools = array_filter(array_keys($tools), static fn (string $name): bool => str_starts_with($name, 'commerce_'));
p70_assert('V9 editor no commerce_* tools', $commerceTools === []);

// V11 — invitations.list for admin
$admin = ['id' => 'a1', 'role' => 'admin', 'email' => 'admin@test.local', 'name' => 'Admin'];
$invitations = cms_mcp_dispatch_admin('invitations.list', [], $admin);
p70_assert('V11 invitations.list ok', ($invitations['status'] ?? '') === 'ok');

// M6 hosting/discovery smoke
p70_assert('hosting.get in catalog', isset(cms_mcp_admin_action_meta()['hosting.get']));
p70_assert('discovery.get in catalog', isset(cms_mcp_admin_action_meta()['discovery.get']));
$hostingGet = cms_mcp_dispatch_admin('hosting.get', [], $admin);
p70_assert('hosting.get dispatch ok', ($hostingGet['status'] ?? '') === 'ok');
$discoveryGet = cms_mcp_dispatch_admin('discovery.get', [], $admin);
p70_assert('discovery.get dispatch ok', ($discoveryGet['status'] ?? '') === 'ok');

// cleanup
$it = new RecursiveDirectoryIterator($testRoot, RecursiveDirectoryIterator::SKIP_DOTS);
$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
foreach ($files as $file) {
    $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
}
rmdir($testRoot);

echo "\nmcp_p70_verification_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
