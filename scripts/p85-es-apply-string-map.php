#!/usr/bin/env php
<?php
/**
 * Apply lang/.es-string-map.json (+ overrides) to lang/es.json admin keys still identical to EN.
 */
declare(strict_types=1);

require __DIR__ . '/p85-es-translate-string.php';

$root = dirname(__DIR__);
$enAdmin = json_decode((string) file_get_contents($root . '/lang/en.json'), true)['admin'] ?? [];
$esPath = $root . '/lang/es.json';
$esData = json_decode((string) file_get_contents($esPath), true);
$esAdmin = $esData['admin'] ?? [];

$keepLatin = ['api', 'csv', 'json', 'html', 'css', 'js', 'url', 'stripe', 'github', 'mcp', 'birkly', 'hub'];
$changed = 0;

foreach ($enAdmin as $key => $enVal) {
    if (!is_string($enVal)) {
        continue;
    }
    $cur = $esAdmin[$key] ?? $enVal;
    if ($cur !== $enVal) {
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
    $esAdmin[$key] = $translated;
    $changed++;
}

ksort($esAdmin);
$esData['admin'] = $esAdmin;
file_put_contents($esPath, json_encode($esData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
fwrite(STDOUT, "Updated {$changed} admin strings in lang/es.json\n");
