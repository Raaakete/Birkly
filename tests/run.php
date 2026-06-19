<?php
/**
 * M5 test harness — single entry point for CI and qa-verifier.
 * Run: php tests/run.php
 * Exit 0 = all pass, 1 = one or more failures.
 */

$suites = [
    'template_parser_test.php',
    'auth_boundary_test.php',
    'select_options_test.php',
];

$root = dirname(__DIR__);
$failures = 0;

echo "Birkly test harness (M5)\n";
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
