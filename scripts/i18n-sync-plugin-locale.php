#!/usr/bin/env php
<?php
/**
 * Sync missing plugin catalog keys from lang/en.json into lang/{locale}.json.
 *
 * Usage: php scripts/i18n-sync-plugin-locale.php <plugin-root> <plugin-id> <locale>
 */
declare(strict_types=1);

$argv = $_SERVER['argv'] ?? [];
array_shift($argv);
$pluginRoot = $argv[0] ?? '';
$pluginId = $argv[1] ?? '';
$locale = preg_replace('/[^a-z0-9_-]/', '', strtolower($argv[2] ?? ''));
if ($pluginRoot === '' || $pluginId === '' || $locale === '' || $locale === 'en') {
    fwrite(STDERR, "Usage: php scripts/i18n-sync-plugin-locale.php <plugin-root> <plugin-id> <locale>\n");
    exit(1);
}

$enFile = rtrim($pluginRoot, '/') . '/lang/en.json';
$locFile = rtrim($pluginRoot, '/') . '/lang/' . $locale . '.json';
$enData = json_decode((string) file_get_contents($enFile), true);
$enPlugin = $enData['plugins'][$pluginId] ?? null;
if (!is_array($enPlugin)) {
    fwrite(STDERR, "No plugins.{$pluginId} in en.json\n");
    exit(1);
}

$locData = is_file($locFile) ? json_decode((string) file_get_contents($locFile), true) : [];
if (!is_array($locData)) {
    $locData = [];
}

$metaDefaults = [
    'fr' => ['name' => 'French', 'native_name' => 'Français', 'flag' => '🇫🇷'],
    'de' => ['name' => 'German', 'native_name' => 'Deutsch', 'flag' => '🇩🇪'],
    'ja' => ['name' => 'Japanese', 'native_name' => '日本語', 'flag' => '🇯🇵'],
];
$locData['_meta'] = array_merge($metaDefaults[$locale] ?? ['name' => $locale], $locData['_meta'] ?? []);
if (!isset($locData['plugins'][$pluginId]) || !is_array($locData['plugins'][$pluginId])) {
    $locData['plugins'][$pluginId] = [];
}

function deep_merge_missing(array $en, array $loc): array
{
    foreach ($en as $key => $value) {
        if ($key === '_meta') {
            continue;
        }
        if (is_array($value)) {
            $loc[$key] = deep_merge_missing($value, is_array($loc[$key] ?? null) ? $loc[$key] : []);
        } elseif (!array_key_exists($key, $loc)) {
            $loc[$key] = $value;
        }
    }
    return $loc;
}

$before = json_encode($locData['plugins'][$pluginId]);
$locData['plugins'][$pluginId] = deep_merge_missing($enPlugin, $locData['plugins'][$pluginId]);
$after = json_encode($locData['plugins'][$pluginId]);
$added = strlen($after) - strlen($before);

file_put_contents($locFile, json_encode($locData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
fwrite(STDOUT, "Wrote {$locFile} (approx merge delta bytes: {$added})\n");
