<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/cms_mcp_admin.php';

$failures = 0;

function assert_true(string $name, bool $ok, string $detail = ''): void
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

$catalog = cms_mcp_admin_action_catalog();
assert_true('catalog has groups', count($catalog) >= 10);

$meta = cms_mcp_admin_action_meta();
assert_true('entries.bulk in meta', isset($meta['entries.bulk']));
assert_true('entries.save in meta', isset($meta['entries.save']));
assert_true('media.list in meta', isset($meta['media.list']));
assert_true('roles.list in meta', isset($meta['roles.list']));
assert_true('roles.list_plugins in meta', isset($meta['roles.list_plugins']));
assert_true('settings.get in meta', isset($meta['settings.get']));
assert_true('settings.save in meta', isset($meta['settings.save']));
assert_true('invitations.list in meta', isset($meta['invitations.list']));
assert_true('components.find_entries in meta', isset($meta['components.find_entries']));
assert_true('users.list in meta', isset($meta['users.list']));
assert_true('users.delete in meta', isset($meta['users.delete']));
assert_true('agent_guide.publish in meta', isset($meta['agent_guide.publish']));
assert_true('entries.bulk is write', ($meta['entries.bulk']['write'] ?? false) === true);
assert_true('entries.save is write', ($meta['entries.save']['write'] ?? false) === true);
assert_true('users.delete permission', ($meta['users.delete']['permission'] ?? '') === 'delete_user');

$aiUser = ['id' => 'test', 'role' => 'admin', 'email' => 'admin@test.local', 'name' => 'Admin'];

$rolesList = cms_mcp_dispatch_admin('roles.list', [], $aiUser);
assert_true('roles.list ok', ($rolesList['status'] ?? '') === 'ok');
assert_true('roles.list has roles array', is_array($rolesList['data']['roles'] ?? null));

$settingsGet = cms_mcp_dispatch_admin('settings.get', ['category' => 'site'], $aiUser);
assert_true('settings.get ok', ($settingsGet['status'] ?? '') === 'ok');
assert_true('settings.get has settings', is_array($settingsGet['data']['settings'] ?? null));

$componentsList = cms_mcp_dispatch_admin('components.list', [], $aiUser);
assert_true('components.list ok', ($componentsList['status'] ?? '') === 'ok');

$invitationsList = cms_mcp_dispatch_admin('invitations.list', [], $aiUser);
assert_true('invitations.list ok', ($invitationsList['status'] ?? '') === 'ok');
assert_true('invitations.list has invitations array', is_array($invitationsList['data']['invitations'] ?? null));

$listResult = cms_mcp_dispatch_admin('connections.list', [], $aiUser);
assert_true('connections.list ok or graceful', in_array($listResult['status'] ?? '', ['ok', 'error'], true));

$unknown = cms_mcp_dispatch_admin('not.real', [], $aiUser);
assert_true('unknown action errors', ($unknown['status'] ?? '') === 'error');

exit($failures > 0 ? 1 : 0);
