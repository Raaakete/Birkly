#!/usr/bin/env php
<?php
/**
 * Detect French / mixed contamination in lang/es.json (core or plugin).
 *
 * Usage:
 *   php scripts/i18n-audit-es-quality.php
 *   php scripts/i18n-audit-es-quality.php --plugin=/path/to/plugin <plugin_id>
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

function birkly_es_flatten(array $data, string $prefix = ''): array
{
    $out = [];
    foreach ($data as $key => $value) {
        if ($key === '_meta') {
            continue;
        }
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        if (is_array($value)) {
            $out += birkly_es_flatten($value, $path);
        } else {
            $out[$path] = (string) $value;
        }
    }
    return $out;
}

/** @return list<string> */
function birkly_es_contamination_reasons(string $en, string $es, string $fr): array
{
    $reasons = [];
    if ($fr !== '' && $es === $fr && $es !== $en && mb_strlen($en) > 12) {
        $reasons[] = 'same_as_fr';
    }
    if (preg_match('/\b(Scannez|Faites|Saisissez|Veuillez|vous |du chargement|désactiv|vérifi|évalu|authentification à deux)\b/ui', $es)) {
        $reasons[] = 'french_phrase';
    }
    if (preg_match('/\b(Introduzca the| for [a-z]+ activity| for RSVPs| for automation| for Studio)\b/ui', $es)) {
        $reasons[] = 'mixed_en_es';
    }
    if (preg_match('/\b(Desactiver|Désactivation|Hérité|Non vérifié|Cancelarado)\b/u', $es)) {
        $reasons[] = 'fr_es_mix';
    }
    return $reasons;
}

$root = dirname(__DIR__);
if ($pluginRoot !== null && $pluginId !== null) {
    $enFile = rtrim($pluginRoot, '/') . '/lang/en.json';
    $frFile = rtrim($pluginRoot, '/') . '/lang/fr.json';
    $esFile = rtrim($pluginRoot, '/') . '/lang/es.json';
    $enData = json_decode((string) file_get_contents($enFile), true);
    $frData = is_file($frFile) ? json_decode((string) file_get_contents($frFile), true) : [];
    $esData = json_decode((string) file_get_contents($esFile), true);
    $enFlat = birkly_es_flatten($enData['plugins'][$pluginId] ?? []);
    $frFlat = birkly_es_flatten($frData['plugins'][$pluginId] ?? []);
    $esFlat = birkly_es_flatten($esData['plugins'][$pluginId] ?? []);
    $label = "plugin {$pluginId}";
} else {
    $enFlat = birkly_es_flatten(json_decode((string) file_get_contents($root . '/lang/en.json'), true));
    $frFlat = birkly_es_flatten(json_decode((string) file_get_contents($root . '/lang/fr.json'), true));
    $esFlat = birkly_es_flatten(json_decode((string) file_get_contents($root . '/lang/es.json'), true));
    $label = 'core';
}

$bad = [];
foreach ($enFlat as $key => $enVal) {
    if (!isset($esFlat[$key])) {
        continue;
    }
    $esVal = $esFlat[$key];
    $frVal = $frFlat[$key] ?? '';
    $reasons = birkly_es_contamination_reasons($enVal, $esVal, $frVal);
    if ($reasons !== []) {
        $bad[$key] = ['reasons' => $reasons, 'es' => $esVal];
    }
}

fwrite(STDOUT, "es quality audit — {$label}\n");
fwrite(STDOUT, '  keys: ' . count($enFlat) . "\n");
fwrite(STDOUT, '  contaminated: ' . count($bad) . "\n");

if ($bad !== []) {
    $sample = array_slice($bad, 0, 12, true);
    foreach ($sample as $key => $info) {
        fwrite(STDERR, "  {$key} [" . implode(',', $info['reasons']) . "]: " . mb_substr($info['es'], 0, 100) . "\n");
    }
    if (count($bad) > 12) {
        fwrite(STDERR, '  … and ' . (count($bad) - 12) . " more\n");
    }
    exit(1);
}

fwrite(STDOUT, "OK\n");
exit(0);
