#!/usr/bin/env php
<?php
/**
 * Sync missing admin.* keys from lang/en.json into another locale file (P85-M6).
 *
 *   php scripts/i18n-sync-locale.php de
 *   php scripts/i18n-sync-locale.php --all
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$all = in_array('--all', $argv, true);
$discovered = [];
if ($all) {
    foreach (glob($root . '/lang/*.json') ?: [] as $file) {
        $code = strtolower(basename($file, '.json'));
        if ($code !== 'en' && preg_match('/^[a-z]{2,3}(?:-[a-z0-9]+)?$/', $code)) {
            $discovered[] = $code;
        }
    }
    sort($discovered);
}
$locales = $all
    ? $discovered
    : array_values(array_filter(array_slice($argv, 1), static fn($a) => $a !== '--all' && !str_starts_with($a, '-')));

if ($locales === []) {
    fwrite(STDERR, "Usage: php scripts/i18n-sync-locale.php de [--all]\n");
    exit(1);
}

$enFile = $root . '/lang/en.json';
$enData = json_decode((string) file_get_contents($enFile), true);
if (!is_array($enData['admin'] ?? null)) {
    fwrite(STDERR, "Invalid lang/en.json\n");
    exit(1);
}
$enAdmin = $enData['admin'];

foreach ($locales as $locale) {
    $locale = preg_replace('/[^a-z0-9_-]/', '', strtolower($locale));
    if ($locale === '' || $locale === 'en') {
        continue;
    }
    $path = $root . '/lang/' . $locale . '.json';
    $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
    if (!is_array($data)) {
        $data = [];
    }
    if (!isset($data['_meta']) && isset($enData['_meta'])) {
        $data['_meta'] = $enData['_meta'];
    }
    if (!isset($data['admin']) || !is_array($data['admin'])) {
        $data['admin'] = [];
    }
    $added = 0;
    foreach ($enAdmin as $key => $value) {
        if (!array_key_exists($key, $data['admin'])) {
            $data['admin'][$key] = $value;
            $added++;
        }
    }
    ksort($data['admin']);
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    fwrite(STDOUT, "lang/{$locale}.json: added {$added} keys (total admin: " . count($data['admin']) . ")\n");
}
