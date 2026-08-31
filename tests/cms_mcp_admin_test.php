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
assert_true('media.list in meta', isset($meta['media.list']));
assert_true('agent_guide.publish in meta', isset($meta['agent_guide.publish']));
assert_true('entries.bulk is write', ($meta['entries.bulk']['write'] ?? false) === true);

$aiUser = ['id' => 'test', 'role' => 'admin', 'email' => 'admin@test.local', 'name' => 'Admin'];

$listResult = cms_mcp_dispatch_admin('connections.list', [], $aiUser);
assert_true('connections.list ok or graceful', in_array($listResult['status'] ?? '', ['ok', 'error'], true));

$unknown = cms_mcp_dispatch_admin('not.real', [], $aiUser);
assert_true('unknown action errors', ($unknown['status'] ?? '') === 'error');

exit($failures > 0 ? 1 : 0);
