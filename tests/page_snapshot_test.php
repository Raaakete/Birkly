<?php
/**
 * P2-M6-T2: Page snapshot generation smoke tests.
 */

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
$script = $root . '/scripts/generate-page-snapshots.php';
$outDir = $root . '/cache/page-snapshots';

test_reset_failures();
echo "Page snapshot generation tests\n\n";

passthru('php ' . escapeshellarg($script), $code);
test_assert('generate-page-snapshots exits 0', $code === 0, "exit code: $code");

$indexPath = $outDir . '/index.json';
test_assert('index.json exists', is_file($indexPath));

if (is_file($indexPath)) {
    $snapshot = json_decode(file_get_contents($indexPath), true);
    test_assert('index snapshot has data', is_array($snapshot['data'] ?? null));
    test_assert('index snapshot has blog key', isset($snapshot['data']['blog']));
    test_assert('index snapshot has services key', isset($snapshot['data']['services']));
}

exit(test_finish('page_snapshot_test'));
