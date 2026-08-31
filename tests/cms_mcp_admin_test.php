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

assert_true('notifications.list_recent in meta', isset($meta['notifications.list_recent']));
assert_true('notifications.mark_seen in meta', isset($meta['notifications.mark_seen']));
assert_true('agent_guide.discovery_status in meta', isset($meta['agent_guide.discovery_status']));
assert_true('agent_guide.wizard_apply in meta', isset($meta['agent_guide.wizard_apply']));
assert_true('dashboard.pending_review in meta', isset($meta['dashboard.pending_review']));
assert_true('dashboard.activity in meta', isset($meta['dashboard.activity']));
assert_true('marketplace.upgrade in meta', isset($meta['marketplace.upgrade']));

$notificationsList = cms_mcp_dispatch_admin('notifications.list_recent', ['limit' => 5], $aiUser);
assert_true('notifications.list_recent ok', ($notificationsList['status'] ?? '') === 'ok');

$unread = cms_mcp_dispatch_admin('notifications.unread_count', [], $aiUser);
assert_true('notifications.unread_count ok', ($unread['status'] ?? '') === 'ok');

$discovery = cms_mcp_dispatch_admin('agent_guide.discovery_status', [], $aiUser);
assert_true('agent_guide.discovery_status ok or forbidden', in_array($discovery['status'] ?? '', ['ok', 'error'], true));

$dashboardActivity = cms_mcp_dispatch_admin('dashboard.activity', ['limit' => 5], $aiUser);
assert_true('dashboard.activity ok', ($dashboardActivity['status'] ?? '') === 'ok');

assert_true('hosting.get in meta', isset($meta['hosting.get']));
assert_true('hosting.probe in meta', isset($meta['hosting.probe']));
assert_true('hosting.set_domain_role in meta', isset($meta['hosting.set_domain_role']));
assert_true('discovery.get in meta', isset($meta['discovery.get']));
assert_true('discovery.save_seo in meta', isset($meta['discovery.save_seo']));
assert_true('hosting.set_domain_role is write', ($meta['hosting.set_domain_role']['write'] ?? false) === true);

$hostingGet = cms_mcp_dispatch_admin('hosting.get', [], $aiUser);
assert_true('hosting.get ok', ($hostingGet['status'] ?? '') === 'ok');
assert_true('hosting.get has hosting key', is_array($hostingGet['data']['hosting'] ?? null));

$hostingProbe = cms_mcp_dispatch_admin('hosting.probe', [], $aiUser);
assert_true('hosting.probe ok', ($hostingProbe['status'] ?? '') === 'ok');
assert_true('hosting.probe has delivery', is_array($hostingProbe['data']['delivery'] ?? null));

$discoveryGet = cms_mcp_dispatch_admin('discovery.get', [], $aiUser);
assert_true('discovery.get ok', ($discoveryGet['status'] ?? '') === 'ok');

$listResult = cms_mcp_dispatch_admin('connections.list', [], $aiUser);
assert_true('connections.list ok or graceful', in_array($listResult['status'] ?? '', ['ok', 'error'], true));

$unknown = cms_mcp_dispatch_admin('not.real', [], $aiUser);
assert_true('unknown action errors', ($unknown['status'] ?? '') === 'error');

exit($failures > 0 ? 1 : 0);
