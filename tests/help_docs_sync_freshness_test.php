<?php
/**
 * P104 — Help reads the origin. The install keeps a manifest mirror, not article snapshots.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/help_docs.php';

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

$root = dirname(__DIR__);
$liveMirrorPath = $root . '/docs/help-manifest.json';
$snapshotDir = $root . '/admin/docs/snapshots';
$offlinePath = $root . '/admin/docs/help-offline-manifest.json';

assert_true('live manifest mirror exists', is_file($liveMirrorPath));
assert_true('snapshot directory is gone', !is_dir($snapshotDir));
assert_true('offline manifest is gone', !is_file($offlinePath));

$live = json_decode((string) file_get_contents($liveMirrorPath), true);
$liveTopics = is_array($live['topics'] ?? null) ? $live['topics'] : [];
$requiredIds = ['discovery', 'connections', 'activity', 'notifications'];
$liveIds = array_column($liveTopics, 'id');
foreach ($requiredIds as $id) {
    assert_true("live manifest includes $id", in_array($id, $liveIds, true));
}

$helpJs = (string) file_get_contents($root . '/admin/js/help.js');
assert_true('help.js links to docs.birkly.io', str_contains($helpJs, 'docs.birkly.io'));
assert_true('help.js does not fetch the offline manifest', !str_contains($helpJs, 'help_offline_manifest.php'));

$sync = (string) file_get_contents($root . '/scripts/sync-birkly-docs.sh');
assert_true('sync script does not write snapshots', !str_contains($sync, 'admin/docs/snapshots'));

exit($failures > 0 ? 1 : 0);
