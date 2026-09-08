<?php
/**
 * P83 — Admin coupling guard (dashboard mounts must live in plugins).
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function guard_fail(string $label): void
{
    global $failures;
    $failures[] = $label;
    fwrite(STDERR, "FAIL: $label\n");
}

function guard_file(string $relativePath, array $forbiddenPatterns): void
{
    global $root;
    $path = $root . '/' . ltrim($relativePath, '/');
    if (!is_file($path)) {
        guard_fail("missing file $relativePath");
        return;
    }
    $contents = (string) file_get_contents($path);
    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $contents)) {
            guard_fail("$relativePath must not match $pattern");
        }
    }
}

guard_file('admin/js/dashboard.js', [
    '/commerceStats/',
    '/eventsUpcoming/',
    '/studioPublishing/',
    '/automationRuns/',
    '/function renderCommerce/',
    '/function renderEvents/',
    '/function renderStudio/',
    '/function renderAutomation/',
]);

if ($failures !== []) {
    exit(1);
}

echo "admin_coupling_guard_test: OK\n";
