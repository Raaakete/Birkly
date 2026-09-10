#!/usr/bin/env php
<?php
/**
 * Merge lang/_p85_de_patch.json (or custom path) into lang/de.json admin section.
 *
 * Usage: php scripts/i18n-merge-de-patch.php [patch-file]
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$patchFile = $argv[1] ?? ($root . '/lang/_p85_de_patch.json');
$deFile = $root . '/lang/de.json';

if (!is_file($patchFile)) {
    fwrite(STDERR, "Missing patch: {$patchFile}\n");
    exit(1);
}

$patch = json_decode((string) file_get_contents($patchFile), true);
if (!is_array($patch)) {
    fwrite(STDERR, "Invalid patch JSON\n");
    exit(1);
}

$data = json_decode((string) file_get_contents($deFile), true);
if (!is_array($data) || !is_array($data['admin'] ?? null)) {
    fwrite(STDERR, "Invalid lang/de.json\n");
    exit(1);
}

$merged = 0;
foreach ($patch as $key => $value) {
    if (!is_string($key) || !is_string($value)) {
        continue;
    }
    $data['admin'][$key] = $value;
    $merged++;
}

$encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($encoded === false) {
    fwrite(STDERR, "JSON encode failed\n");
    exit(1);
}
$encoded .= "\n";
file_put_contents($deFile, $encoded);

fwrite(STDOUT, "Merged {$merged} keys into lang/de.json\n");
