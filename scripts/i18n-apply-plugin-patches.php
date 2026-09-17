#!/usr/bin/env php
<?php
/**
 * Merge flat plugin string patches into lang/{locale}.json under plugins.{id}.
 * Patch keys use dot paths (e.g. settings.selection_mode.label).
 *
 * Usage: php scripts/i18n-apply-plugin-patches.php <plugin-root> <plugin-id> <locale> patch.json [...]
 */
declare(strict_types=1);

$argv = $_SERVER['argv'] ?? [];
array_shift($argv);
$pluginRoot = $argv[0] ?? '';
$pluginId = $argv[1] ?? '';
$locale = preg_replace('/[^a-z0-9_-]/', '', strtolower($argv[2] ?? ''));
$patches = array_slice($argv, 3);
if ($pluginRoot === '' || $pluginId === '' || $locale === '' || $patches === []) {
    fwrite(STDERR, "Usage: php scripts/i18n-apply-plugin-patches.php <root> <plugin-id> <locale> patch.json [...]\n");
    exit(1);
}

$locFile = rtrim($pluginRoot, '/') . '/lang/' . $locale . '.json';
$data = json_decode((string) file_get_contents($locFile), true);
if (!is_array($data['plugins'][$pluginId] ?? null)) {
    fwrite(STDERR, "Missing plugins.{$pluginId} in {$locFile}\n");
    exit(1);
}

function set_plugin_path(array &$tree, string $path, string $value): void
{
    $parts = explode('.', $path);
    $cur = &$tree;
    foreach ($parts as $i => $part) {
        if ($i === count($parts) - 1) {
            $cur[$part] = $value;
            return;
        }
        if (!isset($cur[$part]) || !is_array($cur[$part])) {
            $cur[$part] = [];
        }
        $cur = &$cur[$part];
    }
}

$applied = 0;
foreach ($patches as $patchFile) {
    $patch = json_decode((string) file_get_contents($patchFile), true);
    if (!is_array($patch)) {
        fwrite(STDERR, "Invalid patch: {$patchFile}\n");
        exit(1);
    }
    foreach ($patch as $key => $value) {
        if (!is_string($key) || !is_string($value)) {
            continue;
        }
        set_plugin_path($data['plugins'][$pluginId], $key, $value);
        $applied++;
    }
}

file_put_contents($locFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
fwrite(STDOUT, "Applied {$applied} patches to plugins.{$pluginId} in {$locFile}\n");
