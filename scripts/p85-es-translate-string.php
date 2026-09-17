<?php
declare(strict_types=1);

require __DIR__ . '/p85-es-phrase-pairs.php';

function p85_es_protect_tokens(string $s, array &$store): string
{
    $i = 0;
    return preg_replace_callback(
        '/(\{\{[^{}]+\}\}|\{[a-zA-Z0-9_.]+\}|%[sd]|@[a-zA-Z0-9_.:-]+|<[^>]+>)/',
        static function ($m) use (&$store, &$i) {
            $key = "\x00T" . $i++ . "\x00";
            $store[$key] = $m[0];
            return $key;
        },
        $s
    ) ?? $s;
}

function p85_es_restore_tokens(string $s, array $store): string
{
    return strtr($s, $store);
}

function p85_es_translate_string(string $en): string
{
    static $map = null;
    static $overrides = null;
    $root = dirname(__DIR__);
    if ($map === null) {
        $mapFile = $root . '/lang/.es-string-map.json';
        $map = is_file($mapFile)
            ? (json_decode((string) file_get_contents($mapFile), true) ?: [])
            : [];
    }
    if ($overrides === null) {
        $ovFile = $root . '/lang/.es-string-overrides.json';
        $overrides = is_file($ovFile)
            ? (json_decode((string) file_get_contents($ovFile), true) ?: [])
            : [];
    }
    if (isset($overrides[$en]) && is_string($overrides[$en]) && $overrides[$en] !== '') {
        return $overrides[$en];
    }
    if (isset($map[$en]) && is_string($map[$en]) && $map[$en] !== $en) {
        return $map[$en];
    }
    if ($en === '' || preg_match('/^[\d\s\W]+$/u', $en)) {
        return $en;
    }
    $store = [];
    $s = p85_es_protect_tokens($en, $store);
    foreach (p85_es_phrase_pairs() as [$from, $to]) {
        if ($from !== '' && str_contains($s, $from)) {
            $s = str_replace($from, $to, $s);
        }
    }
    $s = preg_replace('/\bEnter\b/u', 'Introduzca', $s) ?? $s;
    $s = preg_replace('/\benter\b/u', 'introduzca', $s) ?? $s;
    $s = preg_replace('/\bLoading ([^…]+)…/u', 'Cargando $1…', $s) ?? $s;
    $s = preg_replace('/\bNo ([^.\n]+) yet\./u', 'Aún no hay $1.', $s) ?? $s;
    $s = preg_replace('/\bFailed to ([^.\n]+)\./u', 'Error al $1.', $s) ?? $s;
    $s = preg_replace('/\bUnable to ([^.\n]+)\./u', 'No se puede $1.', $s) ?? $s;
    $s = preg_replace('/\bAre you sure ([^?]+)\?/u', '¿Está seguro de que $1?', $s) ?? $s;
    $s = preg_replace('/\bPlease ([^.\n]+)\./u', 'Por favor, $1.', $s) ?? $s;
    $s = preg_replace('/\bClick to ([^.\n]+)/u', 'Haga clic para $1', $s) ?? $s;
    $s = preg_replace('/\bSave ([^.\n]+)/u', 'Guardar $1', $s) ?? $s;
    return p85_es_restore_tokens($s, $store);
}
