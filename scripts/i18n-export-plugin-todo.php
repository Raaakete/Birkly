#!/usr/bin/env php
<?php
/**
 * Export plugin keys where locale value still equals English (for translation batches).
 *
 * Usage: php scripts/i18n-export-plugin-todo.php <plugin-root> <plugin-id> <locale> <out-dir> [batch-size]
 */
declare(strict_types=1);

$argv = $_SERVER['argv'] ?? [];
array_shift($argv);
$pluginRoot = $argv[0] ?? '';
$pluginId = $argv[1] ?? '';
$locale = $argv[2] ?? '';
$outDir = $argv[3] ?? '';
$batchSize = (int) ($argv[4] ?? 100);
if ($pluginRoot === '' || $pluginId === '' || $locale === '' || $outDir === '') {
    fwrite(STDERR, "Usage: php scripts/i18n-export-plugin-todo.php <root> <id> <locale> <out-dir> [batch-size]\n");
    exit(1);
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

$enData = json_decode((string) file_get_contents(rtrim($pluginRoot, '/') . '/lang/en.json'), true);
$enFlat = flatten_plugin($enData['plugins'][$pluginId] ?? []);
$locFile = rtrim($pluginRoot, '/') . '/lang/' . $locale . '.json';
$locFlat = [];
if (is_file($locFile)) {
    $locData = json_decode((string) file_get_contents($locFile), true);
    $locFlat = flatten_plugin($locData['plugins'][$pluginId] ?? []);
}

$todo = [];
foreach ($enFlat as $key => $enVal) {
    $locVal = $locFlat[$key] ?? null;
    if ($locVal === null || $locVal === $enVal) {
        $todo[$key] = $enVal;
    }
}

if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

$keys = array_keys($todo);
$chunks = array_chunk($keys, max(1, $batchSize), true);
$i = 0;
foreach ($chunks as $chunk) {
    $part = [];
    foreach ($chunk as $k) {
        $part[$k] = $todo[$k];
    }
    file_put_contents($outDir . '/en-batch-' . $i . '.json', json_encode($part, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    $i++;
}

fwrite(STDOUT, "plugin={$pluginId} todo=" . count($todo) . " batches={$i} dir={$outDir}\n");
