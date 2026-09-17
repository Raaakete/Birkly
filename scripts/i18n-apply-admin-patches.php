#!/usr/bin/env php
<?php
/**
 * Merge admin.* string patches into lang/{locale}.json (no network).
 *
 * Usage: php scripts/i18n-apply-admin-patches.php fr path/to/patch.json [patch2.json ...]
 */
declare(strict_types=1);

$argv = $_SERVER['argv'] ?? [];
array_shift($argv);
$locale = $argv[0] ?? '';
if ($locale === '' || count($argv) < 2) {
    fwrite(STDERR, "Usage: php scripts/i18n-apply-admin-patches.php <locale> <patch.json> [...]\n");
    exit(1);
}

$root = dirname(__DIR__);
$path = $root . '/lang/' . preg_replace('/[^a-z0-9_-]/', '', $locale) . '.json';
if (!is_file($path)) {
    fwrite(STDERR, "Missing {$path}\n");
    exit(1);
}

$data = json_decode((string) file_get_contents($path), true);
if (!is_array($data['admin'] ?? null)) {
    fwrite(STDERR, "Invalid locale file\n");
    exit(1);
}

$applied = 0;
foreach (array_slice($argv, 1) as $patchFile) {
    if (!is_file($patchFile)) {
        fwrite(STDERR, "Skip missing patch: {$patchFile}\n");
        continue;
    }
    $patch = json_decode((string) file_get_contents($patchFile), true);
    if (!is_array($patch)) {
        fwrite(STDERR, "Invalid patch JSON: {$patchFile}\n");
        exit(1);
    }
    foreach ($patch as $key => $value) {
        if (!is_string($key) || !is_string($value)) {
            continue;
        }
        $data['admin'][$key] = $value;
        $applied++;
    }
}

ksort($data['admin']);
file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
fwrite(STDOUT, "Applied {$applied} patches to {$path}\n");
