#!/usr/bin/env php
<?php
/**
 * Generate lang/.es-curated-admin.json entries for admin keys still identical to EN (quality).
 * Uses agent EN→ES rules in p85-es-translate-string.php (no external MT, no FR copy).
 *
 * Usage: php scripts/p85-es-generate-curated.php [--write]
 */
declare(strict_types=1);

require __DIR__ . '/p85-es-translate-string.php';

$root = dirname(__DIR__);
$write = in_array('--write', $argv, true);

$enAdmin = json_decode((string) file_get_contents($root . '/lang/en.json'), true)['admin'] ?? [];
$esAdmin = json_decode((string) file_get_contents($root . '/lang/es.json'), true)['admin'] ?? [];
$curPath = $root . '/lang/.es-curated-admin.json';
$curated = is_file($curPath) ? (json_decode((string) file_get_contents($curPath), true) ?: []) : [];

$keepLatin = ['api', 'csv', 'json', 'html', 'css', 'js', 'url', 'stripe', 'github', 'mcp', 'birkly', 'hub'];

$new = 0;
foreach ($enAdmin as $key => $enVal) {
    if (!is_string($enVal)) {
        continue;
    }
    $esVal = $esAdmin[$key] ?? $enVal;
    if ($esVal !== $enVal) {
        continue;
    }
    if (strlen($enVal) <= 2) {
        continue;
    }
    $vl = strtolower(trim($enVal));
    if (in_array($vl, $keepLatin, true)) {
        continue;
    }
    if (strlen($enVal) <= 5 && $enVal === strtoupper($enVal)) {
        continue;
    }
    $translated = p85_es_translate_string($enVal);
    if ($translated === $enVal) {
        continue;
    }
    if (!isset($curated[$key]) || $curated[$key] === $enVal) {
        $curated[$key] = $translated;
        $new++;
    }
}

ksort($curated);
if ($write) {
    file_put_contents($curPath, json_encode($curated, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    fwrite(STDOUT, "Wrote {$curPath} (+{$new} new), total " . count($curated) . "\n");
} else {
    fwrite(STDOUT, "Would add {$new} curated entries (total " . count($curated) . "). Pass --write\n");
}
