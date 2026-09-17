#!/usr/bin/env php
<?php
/**
 * P85 — Plugin admin i18n locale audit (lang/en.json vs lang/{locale}.json).
 *
 * Usage:
 *   php scripts/i18n-audit-plugin.php <plugin-root> <plugin_id> [--locale=de] [--strict]
 */
declare(strict_types=1);

$argv = $_SERVER['argv'] ?? [];
array_shift($argv);
$options = [];
$positional = [];
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--')) {
        if (str_contains($arg, '=')) {
            [$k, $v] = explode('=', substr($arg, 2), 2);
            $options[$k] = $v;
        } else {
            $options[substr($arg, 2)] = true;
        }
    } else {
        $positional[] = $arg;
    }
}

$pluginRoot = $positional[0] ?? '';
$pluginId = $positional[1] ?? '';
$locale = (string) ($options['locale'] ?? 'de');
$strict = isset($options['strict']);

if ($pluginRoot === '' || $pluginId === '') {
    fwrite(STDERR, "Usage: php scripts/i18n-audit-plugin.php <plugin-root> <plugin_id> [--locale=de] [--strict]\n");
    exit(1);
}

$enFile = rtrim($pluginRoot, '/') . '/lang/en.json';
$localeFile = rtrim($pluginRoot, '/') . '/lang/' . $locale . '.json';

if (!is_file($enFile)) {
    fwrite(STDERR, "Missing {$enFile}\n");
    exit($strict ? 1 : 0);
}

$enData = json_decode((string) file_get_contents($enFile), true);
$enPlugin = $enData['plugins'][$pluginId] ?? null;
if (!is_array($enPlugin)) {
    fwrite(STDERR, "No plugins.{$pluginId} in en.json\n");
    exit(1);
}

function birkly_i18n_flatten_plugin(array $data, string $prefix = ''): array
{
    $out = [];
    foreach ($data as $key => $value) {
        if ($key === '_meta') {
            continue;
        }
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        if (is_array($value)) {
            $out += birkly_i18n_flatten_plugin($value, $path);
        } else {
            $out[$path] = (string) $value;
        }
    }
    return $out;
}

$enFlat = birkly_i18n_flatten_plugin($enPlugin);
$localeFlat = [];
if (is_file($localeFile)) {
    $locData = json_decode((string) file_get_contents($localeFile), true);
    if (is_array($locData['plugins'][$pluginId] ?? null)) {
        $localeFlat = birkly_i18n_flatten_plugin($locData['plugins'][$pluginId]);
    }
}

$missing = array_diff_key($enFlat, $localeFlat);
$identical = [];
foreach ($enFlat as $key => $enVal) {
    if (!isset($localeFlat[$key]) || $localeFlat[$key] !== $enVal) {
        continue;
    }
    if (strlen($enVal) <= 2 || !preg_match('/[A-Za-z]{3,}/', $enVal)) {
        continue;
    }
    $identical[] = $key;
}

fwrite(STDOUT, "plugin i18n audit — {$pluginId} locale={$locale}\n");
fwrite(STDOUT, '  en keys: ' . count($enFlat) . "\n");
fwrite(STDOUT, '  missing: ' . count($missing) . "\n");
fwrite(STDOUT, '  identical-to-en: ' . count($identical) . "\n");

if ($missing !== [] && $strict) {
    fwrite(STDERR, "ERROR: missing keys in lang/{$locale}.json\n");
    exit(1);
}

fwrite(STDOUT, "OK\n");
exit(0);
