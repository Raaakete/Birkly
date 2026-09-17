#!/usr/bin/env php
<?php
/**
 * Build Commerce lang/es.json from en + de (primary) + phrase fallback. Writes value map + es.json.
 */
declare(strict_types=1);

require __DIR__ . '/p85-es-commerce-from-en.php';
require __DIR__ . '/p85-es-commerce-de-bridge.php';

$root = getenv('BIRKLY_COMMERCE_PLUGIN_ROOT') ?: '/agent/repos/Birkly-Plugin-Commerce';
$enData = json_decode((string) file_get_contents($root . '/lang/en.json'), true);
$deData = json_decode((string) file_get_contents($root . '/lang/de.json'), true);
$frData = json_decode((string) file_get_contents($root . '/lang/fr.json'), true);
$enFlat = flatten_plugin($enData['plugins']['commerce'] ?? []);
$deFlat = flatten_plugin($deData['plugins']['commerce'] ?? []);
$frFlat = flatten_plugin($frData['plugins']['commerce'] ?? []);
$manual = p85_es_commerce_manual_en_table();

$valueMap = [];
$esFlat = [];

foreach ($enFlat as $path => $en) {
    $es = null;
    if (isset($manual[$en])) {
        $es = $manual[$en];
    } else {
        $de = $deFlat[$path] ?? $en;
        if ($de !== $en) {
            $fromDe = p85_es_commerce_de_to_es($de);
            if ($fromDe !== $de && !p85_es_commerce_looks_german($fromDe)) {
                $es = $fromDe;
            }
        }
        if ($es === null) {
            $es = translate_commerce_en_to_es($en, false);
        }
    }
    $fr = $frFlat[$path] ?? '';
    if ($fr !== '' && $es === $fr && $fr !== $en && !isset($manual[$en])) {
        $es = translate_commerce_en_to_es($en, false);
    }
    if (str_contains($es, 'Cancelarado')) {
        $es = str_replace('Cancelarado', 'Cancelado', $es);
    }
    if ($es !== $en) {
        $valueMap[$en] = $es;
    }
    $esFlat[$path] = $es;
}

$commerceEsCurated = [
    'settings_emails_help' => 'Plantillas de recibo, envío, reembolso, fallo de pago de suscripción (dunning) y seguimiento retrasado. Active o desactive cada disparador en la lista. Pedido pagado es el recibo enviado tras el pago para todos los pedidos (incluidas donaciones). El agradecimiento de donación opcional es un disparador aparte para automatización o reenvío manual. Activar una plantilla solo prepara el mensaje; no envía correo. Elija una conexión predeterminada abajo y sobrescriba por plantilla si necesita remitentes distintos.',
];
foreach ($commerceEsCurated as $path => $esVal) {
    if (isset($esFlat[$path])) {
        $esFlat[$path] = $esVal;
    }
}

$dir = __DIR__ . '/data';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
file_put_contents(
    $dir . '/p85-commerce-es-values.json',
    json_encode($valueMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
);

$esData = [
    '_meta' => ['name' => 'Spanish', 'native_name' => 'Español', 'flag' => '🇪🇸'],
    'plugins' => ['commerce' => unflatten_plugin($esFlat)],
];
file_put_contents($root . '/lang/es.json', json_encode($esData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

$id = 0;
foreach ($enFlat as $k => $v) {
    if ($esFlat[$k] === $v && strlen($v) > 2 && preg_match('/[A-Za-z]{3,}/', $v)) {
        $id++;
    }
}
fwrite(STDOUT, "Wrote es.json + " . count($valueMap) . " value overrides; identical keys: {$id}\n");

function p85_es_commerce_manual_en_table(): array
{
    $file = __DIR__ . '/data/p85-commerce-es-manual-en.php';
    if (!is_file($file)) {
        return [];
    }
    $data = require $file;

    return is_array($data) ? $data : [];
}

function p85_es_commerce_looks_german(string $s): bool
{
    return (bool) preg_match(
        '/\b(und|oder|für|mit|wird|werden|keine|kein|Bitte|Fehler beim|ä|ö|ü|ß)\b/ui',
        $s
    );
}
