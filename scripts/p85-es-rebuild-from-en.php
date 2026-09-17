#!/usr/bin/env php
<?php
/**
 * P85 tier-1 — Rebuild lang/es.json from lang/en.json using agent EN→ES phrase maps (no external MT).
 *
 * Usage:
 *   php scripts/p85-es-rebuild-from-en.php core
 *   php scripts/p85-es-rebuild-from-en.php plugin <plugin-root> <plugin-id>
 */
declare(strict_types=1);

require __DIR__ . '/p85-es-phrase-pairs.php';

$argv = $_SERVER['argv'] ?? [];
array_shift($argv);
$mode = $argv[0] ?? '';

function protect_tokens(string $s, array &$store): string
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

function restore_tokens(string $s, array $store): string
{
    return strtr($s, $store);
}

function translate_admin_en(string $en): string
{
    if ($en === '' || preg_match('/^[\d\s\W]+$/u', $en)) {
        return $en;
    }
    $store = [];
    $s = protect_tokens($en, $store);
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
    return restore_tokens($s, $store);
}

function flatten_plugin(array $data, string $prefix = ''): array
{
    $out = [];
    foreach ($data as $key => $value) {
        if ($key === '_meta') {
            continue;
        }
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        if (is_array($value)) {
            $out += flatten_plugin($value, $path);
        } else {
            $out[$path] = (string) $value;
        }
    }
    return $out;
}

function unflatten_plugin(array $flat): array
{
    $tree = [];
    foreach ($flat as $path => $value) {
        $parts = explode('.', $path);
        $cur = &$tree;
        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $cur[$part] = $value;
            } else {
                if (!isset($cur[$part]) || !is_array($cur[$part])) {
                    $cur[$part] = [];
                }
                $cur = &$cur[$part];
            }
        }
    }
    return $tree;
}

function merge_curated_admin(array $enAdmin, array $curated): array
{
    $out = [];
    foreach ($enAdmin as $key => $enVal) {
        if (!is_string($enVal)) {
            continue;
        }
        if (isset($curated[$key]) && is_string($curated[$key]) && $curated[$key] !== $enVal) {
            $out[$key] = $curated[$key];
            continue;
        }
        $out[$key] = translate_admin_en($enVal);
    }
    return $out;
}

$root = dirname(__DIR__);

if ($mode === 'core') {
    $enData = json_decode((string) file_get_contents($root . '/lang/en.json'), true);
    $curated = [];
    $curFile = $root . '/lang/.es-curated-admin.json';
    if (is_file($curFile)) {
        $curated = json_decode((string) file_get_contents($curFile), true) ?: [];
    }
    $esAdmin = merge_curated_admin($enData['admin'] ?? [], $curated);
    ksort($esAdmin);
    $esData = [
        '_meta' => ['name' => 'Spanish', 'native_name' => 'Español', 'flag' => '🇪🇸'],
        'admin' => $esAdmin,
    ];
    file_put_contents($root . '/lang/es.json', json_encode($esData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    fwrite(STDOUT, 'Wrote core lang/es.json admin keys: ' . count($esAdmin) . "\n");
    exit(0);
}

if ($mode === 'plugin') {
    $pluginRoot = $argv[1] ?? '';
    $pluginId = $argv[2] ?? '';
    if ($pluginRoot === '' || $pluginId === '') {
        fwrite(STDERR, "Usage: php scripts/p85-es-rebuild-from-en.php plugin <root> <id>\n");
        exit(1);
    }
    if ($pluginId === 'commerce') {
        passthru('php ' . escapeshellarg($root . '/scripts/p85-es-commerce-build-es.php'), $code);
        exit($code);
    }
    $enFile = rtrim($pluginRoot, '/') . '/lang/en.json';
    $enData = json_decode((string) file_get_contents($enFile), true);
    $enFlat = flatten_plugin($enData['plugins'][$pluginId] ?? []);
    $esFlat = [];
    foreach ($enFlat as $k => $v) {
        $esFlat[$k] = translate_admin_en($v);
    }
    $esData = [
        '_meta' => ['name' => 'Spanish', 'native_name' => 'Español', 'flag' => '🇪🇸'],
        'plugins' => [$pluginId => unflatten_plugin($esFlat)],
    ];
    file_put_contents(rtrim($pluginRoot, '/') . '/lang/es.json', json_encode($esData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    fwrite(STDOUT, "Wrote plugin {$pluginId} keys: " . count($esFlat) . "\n");
    exit(0);
}

fwrite(STDERR, "Usage: php scripts/p85-es-rebuild-from-en.php core|plugin ...\n");
exit(1);
