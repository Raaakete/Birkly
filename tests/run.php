<?php
/**
 * Birkly test runner.
 *
 * By default runs the Phase 65 connection/mail tests. Pass --all to run every
 * *_test.php file in this directory (some legacy tests require network or a
 * running server and may fail in local sandbox runs).
 */

declare(strict_types=1);

$dir = __DIR__;
$runAll = in_array('--all', $argv, true);

if ($runAll) {
    $files = glob($dir . '/*_test.php');
} else {
    $files = array_merge(
        glob($dir . '/connections_*_test.php') ?: [],
        glob($dir . '/mail_*_test.php') ?: [],
        glob($dir . '/notify_*_test.php') ?: [],
        glob($dir . '/http_*_test.php') ?: [],
        glob($dir . '/webhooks_*_test.php') ?: [],
        glob($dir . '/esp_*_test.php') ?: []
    );
}

$totalFailures = 0;
$ran = 0;

foreach ($files as $file) {
    if (basename($file) === 'run.php') {
        continue;
    }
    echo "\n=== Running " . basename($file) . " ===\n";
    $ran++;
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file);
    $output = [];
    $exitCode = 0;
    exec($cmd . ' 2>&1', $output, $exitCode);
    echo implode("\n", $output) . "\n";
    if ($exitCode !== 0) {
        $totalFailures++;
    }
}

echo "\n================================\n";
echo "Ran $ran test file(s).\n";
if ($totalFailures === 0) {
    echo "All tests PASSED.\n";
    exit(0);
}
echo "$totalFailures test file(s) FAILED.\n";
exit(1);
