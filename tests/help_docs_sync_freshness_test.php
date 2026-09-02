<?php
/**
 * P72 — Help offline bundle freshness vs bundled live manifest mirror.
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
$offlinePath = $root . '/admin/docs/help-offline-manifest.json';
$liveMirrorPath = $root . '/docs/help-manifest.json';

assert_true('offline manifest exists', is_file($offlinePath));
assert_true('live manifest mirror exists', is_file($liveMirrorPath));

$offline = json_decode((string) file_get_contents($offlinePath), true);
$live = json_decode((string) file_get_contents($liveMirrorPath), true);

$offlineTopics = is_array($offline['topics'] ?? null) ? $offline['topics'] : [];
$liveTopics = is_array($live['topics'] ?? null) ? $live['topics'] : [];

assert_true(
    'offline topic count matches live mirror',
    count($offlineTopics) === count($liveTopics),
    'offline=' . count($offlineTopics) . ' live=' . count($liveTopics)
);

$requiredIds = ['discovery', 'connections', 'activity', 'notifications'];
$offlineIds = array_column($offlineTopics, 'id');
foreach ($requiredIds as $id) {
    assert_true("offline manifest includes $id", in_array($id, $offlineIds, true));
}

foreach ($offlineTopics as $topic) {
    $id = (string) ($topic['id'] ?? '');
    $snapshot = (string) ($topic['snapshot'] ?? '');
    if ($snapshot === '') {
        assert_true("topic $id has snapshot path", false);
        continue;
    }
    $abs = $root . $snapshot;
    assert_true("snapshot file exists for $id", is_file($abs), $abs);
}

$helpJs = (string) file_get_contents($root . '/admin/js/help.js');
assert_true('help.js links to docs.birkly.com', str_contains($helpJs, 'docs.birkly.com'));

exit($failures > 0 ? 1 : 0);
