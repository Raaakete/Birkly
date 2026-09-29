#!/usr/bin/env php
<?php
/**
 * Rebuild lang/fr.json plugin catalog from lang/en.json + flat French patch map(s).
 * Patch JSON: { "dot.key": "French value", ... } — must cover every en key.
 *
 * Usage: php scripts/i18n-rebuild-plugin-fr.php <plugin-root> <plugin-id> patch.json [...]
 */
declare(strict_types=1);

fwrite(STDERR, "i18n-rebuild-plugin-fr.php is deprecated. Translate from lang/en.json and run scripts/i18n-audit.php --selectable.\n");
exit(1);

$argv = $_SERVER['argv'] ?? [];
array_shift($argv);
$pluginRoot = rtrim($argv[0] ?? '', '/');
$pluginId = $argv[1] ?? '';
$patchFiles = array_slice($argv, 2);
if ($pluginRoot === '' || $pluginId === '' || $patchFiles === []) {
    fwrite(STDERR, "Usage: php scripts/i18n-rebuild-plugin-fr.php <plugin-root> <plugin-id> patch.json [...]\n");
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

$enFile = $pluginRoot . '/lang/en.json';
$frFile = $pluginRoot . '/lang/fr.json';
$enData = json_decode((string) file_get_contents($enFile), true);
$enFlat = flatten_plugin($enData['plugins'][$pluginId] ?? []);
if ($enFlat === []) {
    fwrite(STDERR, "No plugins.{$pluginId} in en.json\n");
    exit(1);
}

$patches = [];
foreach ($patchFiles as $file) {
    $chunk = json_decode((string) file_get_contents($file), true);
    if (!is_array($chunk)) {
        fwrite(STDERR, "Invalid patch file: {$file}\n");
        exit(1);
    }
    foreach ($chunk as $k => $v) {
        if (is_string($k) && is_string($v)) {
            $patches[$k] = $v;
        }
    }
}

$frFlat = [];
$missing = [];
foreach ($enFlat as $key => $enVal) {
    if (!array_key_exists($key, $patches)) {
        $missing[] = $key;
        $frFlat[$key] = $enVal;
    } else {
        $frFlat[$key] = $patches[$key];
    }
}

if ($missing !== []) {
    fwrite(STDERR, 'Missing ' . count($missing) . " patch keys (left as English):\n");
    foreach (array_slice($missing, 0, 20) as $m) {
        fwrite(STDERR, "  {$m}\n");
    }
    if (count($missing) > 20) {
        fwrite(STDERR, '  … and ' . (count($missing) - 20) . " more\n");
    }
    exit(1);
}

$frData = [
    '_meta' => [
        'name' => 'French',
        'native_name' => 'Français',
        'flag' => '🇫🇷',
    ],
    'plugins' => [
        $pluginId => unflatten_plugin($frFlat),
    ],
];

file_put_contents($frFile, json_encode($frData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
fwrite(STDOUT, 'Wrote ' . count($frFlat) . " keys to {$frFile}\n");
