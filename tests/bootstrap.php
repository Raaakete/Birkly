<?php
/**
 * Shared test helpers (M5) — dependency-free, no PHPUnit required.
 */

function test_assert(string $name, bool $ok, string $detail = ''): void
{
    global $test_failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $test_failures++;
        if ($detail !== '') {
            echo '  ' . $detail . "\n";
        }
    }
}

function test_failures_count(): int
{
    global $test_failures;
    return (int) ($test_failures ?? 0);
}

function test_reset_failures(): void
{
    global $test_failures;
    $test_failures = 0;
}

function test_finish(string $suite): int
{
    $failures = test_failures_count();
    echo "\n" . $suite . ': ' . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
    return $failures === 0 ? 0 : 1;
}
