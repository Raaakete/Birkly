#!/usr/bin/env php
<?php
/**
 * P85 — Detect German / mixed-locale contamination in lang/fr.json (core or plugin).
 *
 * CI uses key parity only; this script catches DE→FR heuristic leaks (substring swaps).
 *
 * Usage:
 *   php scripts/i18n-audit-fr-quality.php
 *   php scripts/i18n-audit-fr-quality.php --plugin=/path/to/plugin <plugin_id>
 */
declare(strict_types=1);

$argv = $_SERVER['argv'] ?? [];
array_shift($argv);
$pluginRoot = null;
$pluginId = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--plugin=')) {
        $pluginRoot = substr($arg, 9);
    } elseif ($pluginId === null && !str_starts_with($arg, '--')) {
        $pluginId = $arg;
    }
}

function birkly_fr_flatten(array $data, string $prefix = ''): array
{
    $out = [];
    foreach ($data as $key => $value) {
        if ($key === '_meta') {
            continue;
        }
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        if (is_array($value)) {
            $out += birkly_fr_flatten($value, $path);
        } else {
            $out[$path] = (string) $value;
        }
    }
    return $out;
}

/** @return list<string> */
function birkly_fr_contamination_reasons(string $en, string $fr, string $de): array
{
    $reasons = [];
    if ($de !== '' && $fr === $de && $fr !== $en) {
        $reasons[] = 'same_as_de';
    }
    if (preg_match('/[äöüÄÖÜß]/u', $fr)) {
        $reasons[] = 'german_umlaut';
    }
    if (preg_match('/\b(Übersicht|hinzufügen|auswählen|wird geladen|nicht gefunden|konnten nicht|'
        . 'Noch keine|Unbekannt|Betreff|Titel|exportieren|Speichern|Löschen|Benutzer|'
        . 'Pagenleiste|Automatisation gelöscht|Stäng|Okänt)\b/u', $fr)) {
        $reasons[] = 'german_phrase';
    }
    if (preg_match('/\b(und der|und als|in der Admin|für dieses|zu élémentsn|'
        . 'Recouvrement|wenn ein|bitte neu|wird geladen|gelöscht)\b/ui', $fr)) {
        $reasons[] = 'mixed_de_fr';
    }
    return $reasons;
}

$root = dirname(__DIR__);
if ($pluginRoot !== null && $pluginId !== null) {
    $enFile = rtrim($pluginRoot, '/') . '/lang/en.json';
    $deFile = rtrim($pluginRoot, '/') . '/lang/de.json';
    $frFile = rtrim($pluginRoot, '/') . '/lang/fr.json';
    $enData = json_decode((string) file_get_contents($enFile), true);
    $deData = is_file($deFile) ? json_decode((string) file_get_contents($deFile), true) : [];
    $frData = json_decode((string) file_get_contents($frFile), true);
    $enFlat = birkly_fr_flatten($enData['plugins'][$pluginId] ?? []);
    $deFlat = birkly_fr_flatten($deData['plugins'][$pluginId] ?? []);
    $frFlat = birkly_fr_flatten($frData['plugins'][$pluginId] ?? []);
    $label = "plugin {$pluginId}";
} else {
    $enFile = $root . '/lang/en.json';
    $deFile = $root . '/lang/de.json';
    $frFile = $root . '/lang/fr.json';
    $enFlat = birkly_fr_flatten(json_decode((string) file_get_contents($enFile), true));
    $deFlat = birkly_fr_flatten(json_decode((string) file_get_contents($deFile), true));
    $frFlat = birkly_fr_flatten(json_decode((string) file_get_contents($frFile), true));
    $label = 'core';
}

$bad = [];
foreach ($enFlat as $key => $enVal) {
    if (!isset($frFlat[$key])) {
        continue;
    }
    $frVal = $frFlat[$key];
    $deVal = $deFlat[$key] ?? '';
    $reasons = birkly_fr_contamination_reasons($enVal, $frVal, $deVal);
    if ($reasons !== []) {
        $bad[$key] = ['reasons' => $reasons, 'fr' => $frVal];
    }
}

fwrite(STDOUT, "fr quality audit — {$label}\n");
fwrite(STDOUT, '  keys: ' . count($enFlat) . "\n");
fwrite(STDOUT, '  contaminated: ' . count($bad) . "\n");

if ($bad !== []) {
    $sample = array_slice($bad, 0, 15, true);
    foreach ($sample as $key => $info) {
        fwrite(STDERR, "  {$key} [" . implode(',', $info['reasons']) . "]: " . mb_substr($info['fr'], 0, 100) . "\n");
    }
    if (count($bad) > 15) {
        fwrite(STDERR, '  … and ' . (count($bad) - 15) . " more\n");
    }
    exit(1);
}

fwrite(STDOUT, "OK\n");
exit(0);
