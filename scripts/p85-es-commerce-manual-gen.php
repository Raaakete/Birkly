#!/usr/bin/env php
<?php
/**
 * Generate data/p85-commerce-es-values.json from EN + DE hints + manual EN overrides.
 */
declare(strict_types=1);

require __DIR__ . '/p85-es-commerce-from-en.php';
require __DIR__ . '/p85-es-commerce-de-bridge.php';

$root = '/agent/repos/Birkly-Plugin-Commerce';
$enData = json_decode((string) file_get_contents($root . '/lang/en.json'), true);
$deData = json_decode((string) file_get_contents($root . '/lang/de.json'), true);
$frData = json_decode((string) file_get_contents($root . '/lang/fr.json'), true);
$enFlat = flatten_plugin($enData['plugins']['commerce'] ?? []);
$deFlat = flatten_plugin($deData['plugins']['commerce'] ?? []);

$manualEn = p85_es_commerce_manual_en_table();

$out = [];
foreach ($enFlat as $path => $en) {
    if (isset($manualEn[$en])) {
        $out[$en] = $manualEn[$en];
        continue;
    }
    $de = $deFlat[$path] ?? $en;
    if ($de !== $en) {
        $fromDe = p85_es_commerce_de_to_es($de);
        if ($fromDe !== $de && !p85_es_commerce_looks_german($fromDe)) {
            $out[$en] = $fromDe;
            continue;
        }
    }
    $auto = translate_commerce_en_to_es($en, false);
    if ($auto !== $en) {
        $out[$en] = $auto;
    }
}

$frFlat = flatten_plugin($frData['plugins']['commerce'] ?? []);
foreach ($out as $en => $es) {
    foreach ($enFlat as $path => $enVal) {
        if ($enVal !== $en) {
            continue;
        }
        $fr = $frFlat[$path] ?? '';
        if ($fr !== '' && $es === $fr && $fr !== $en) {
            unset($out[$en]);
            break;
        }
    }
}

$dir = __DIR__ . '/data';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
file_put_contents(
    $dir . '/p85-commerce-es-values.json',
    json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
);

$esFlat = [];
foreach ($enFlat as $k => $v) {
    $esFlat[$k] = $out[$v] ?? translate_commerce_en_to_es($v);
}
$id = 0;
foreach ($enFlat as $k => $v) {
    if ($esFlat[$k] === $v && strlen($v) > 2 && preg_match('/[A-Za-z]{3,}/', $v)) {
        $id++;
    }
}
fwrite(STDOUT, "Wrote " . count($out) . " value overrides; identical keys: {$id}\n");

function p85_es_commerce_manual_en_table(): array
{
    $file = __DIR__ . '/data/p85-commerce-es-manual-en.php';
    if (!is_file($file)) {
        return [];
    }
    $data = require $file;

    return is_array($data) ? $data : [];
}

function p85_es_commerce_looks_french(string $s): bool
{
    return (bool) preg_match(
        '/\b(Exécution|Autoliquidation|Sélectionnez|Saisissez|Rapprochement|échoué|ligne article|Solde initial|utilisations|pour les|des |les |une |Veuillez|é|è|ê|à\s|ç)\b/ui',
        $s
    );
}

function p85_es_commerce_looks_german(string $s): bool
{
    return (bool) preg_match(
        '/\b(und|oder|für|mit|wird|werden|keine|kein|Bitte|Fehler beim|Lager|Bestellung|Versand|Zahlung|Abonnement|Rabatt|Steuer|Kunde|Produkt|Sammlung|Eintrag|Speichern|Abbrechen|Löschen|Bearbeiten|Hinzufügen|Einstellungen|Verbindung|Dokumentation|Aktualisieren|Warenkorb|Gutschein|Geschenkkarte|Katalog|Inventar|ä|ö|ü|ß)\b/ui',
        $s
    );
}
