<?php
/**
 * P9-M0 test harness — single entry point for CI.
 * Run: php tests/run.php
 * Exit 0 = all pass, 1 = one or more failures.
 */

$suites = [
    'smoke_test.php',
];

$failures = 0;

echo "Birkly Marketplace test harness (P9-M0)\n";
echo str_repeat('=', 40) . "\n\n";

foreach ($suites as $suite) {
    $path = __DIR__ . '/' . $suite;
    if (!is_file($path)) {
        echo "MISSING: $suite\n";
        $failures++;
        continue;
    }

    echo "==> $suite\n";
    passthru('php ' . escapeshellarg($path), $code);
    echo "\n";
    if ($code !== 0) {
        $failures++;
    }
}

echo str_repeat('=', 40) . "\n";
if ($failures === 0) {
    echo "RESULT: PASS (" . count($suites) . " suites)\n";
    exit(0);
}

echo "RESULT: FAIL ($failures suite(s) failed)\n";
exit(1);
