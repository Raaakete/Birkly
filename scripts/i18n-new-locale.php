#!/usr/bin/env php
<?php
/**
 * Scaffold an admin locale from English. The new catalog is not selectable.
 *
 *   php scripts/i18n-new-locale.php nl --name=Dutch --native=Nederlands
 *   php scripts/i18n-new-locale.php nl --name=Dutch --native=Nederlands --plugins
 *
 * Does not edit PHP, tests, or CI. Copy from lang/en.json only.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$argv = $_SERVER['argv'] ?? [];
array_shift($argv);

$code = '';
$name = '';
$native = '';
$flag = '';
$date = 'Y-m-d';
$datetime = 'Y-m-d H:i';
$time = 'H:i';
$bcp47 = '';
$plugins = false;

foreach ($argv as $arg) {
    if ($arg === '--plugins') {
        $plugins = true;
        continue;
    }
    if (str_starts_with($arg, '--name=')) {
        $name = substr($arg, 7);
        continue;
    }
    if (str_starts_with($arg, '--native=')) {
        $native = substr($arg, 9);
        continue;
    }
    if (str_starts_with($arg, '--flag=')) {
        $flag = substr($arg, 7);
        continue;
    }
    if (str_starts_with($arg, '--date=')) {
        $date = substr($arg, 7);
        continue;
    }
    if (str_starts_with($arg, '--bcp47=')) {
        $bcp47 = substr($arg, 8);
        continue;
    }
    if ($code === '' && !str_starts_with($arg, '--')) {
        $code = strtolower($arg);
    }
}

if (!preg_match('/^[a-z]{2,3}(?:-[a-z0-9]+)?$/', $code) || $name === '' || $native === '') {
    fwrite(STDERR, "Usage: php scripts/i18n-new-locale.php <code> --name=English name --native=Native name [--flag=] [--date=Y-m-d] [--bcp47=] [--plugins]\n");
    exit(1);
}

$enPath = $root . '/lang/en.json';
$en = json_decode((string) file_get_contents($enPath), true);
if (!is_array($en['admin'] ?? null)) {
    fwrite(STDERR, "Invalid lang/en.json\n");
    exit(1);
}

$target = $root . '/lang/' . $code . '.json';
if (is_file($target)) {
    fwrite(STDERR, "Refusing to overwrite {$target}\n");
    exit(1);
}

$en['_meta'] = [
    'code' => $code,
    'name' => $name,
    'native_name' => $native,
    'flag' => $flag,
    'dir' => 'ltr',
    'bcp47' => $bcp47 !== '' ? $bcp47 : $code,
    'selectable' => false,
    'date_format' => $date,
    'datetime_format' => $datetime,
    'time_format' => $time,
];

file_put_contents(
    $target,
    json_encode($en, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
);
fwrite(STDOUT, "Wrote {$target} with selectable=false (" . count($en['admin']) . " admin keys copied from English).\n");

$siblings = [
    'Birkly-Plugin-User' => 'user_management',
    'Birkly-Plugin-Commerce' => 'commerce',
    'Birkly-Plugin-Events' => 'events',
    'Birkly-Plugin-Automation' => 'automation',
    'Birkly-Plugin-Studio' => 'studio',
];
foreach ($siblings as $dir => $id) {
    $pluginRoot = dirname($root) . '/' . $dir;
    $pluginEn = $pluginRoot . '/lang/en.json';
    $pluginTarget = $pluginRoot . '/lang/' . $code . '.json';
    if (!is_file($pluginEn)) {
        continue;
    }
    if (is_file($pluginTarget)) {
        fwrite(STDOUT, "{$dir} already has lang/{$code}.json\n");
        continue;
    }
    if (!$plugins) {
        fwrite(STDOUT, "{$dir} has no lang/{$code}.json (pass --plugins to copy English).\n");
        continue;
    }
    $pluginData = json_decode((string) file_get_contents($pluginEn), true);
    if (!is_array($pluginData)) {
        fwrite(STDERR, "Invalid {$pluginEn}\n");
        exit(1);
    }
    $pluginData['_meta'] = $en['_meta'];
    file_put_contents(
        $pluginTarget,
        json_encode($pluginData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );
    fwrite(STDOUT, "Wrote {$pluginTarget}\n");
}

fwrite(STDOUT, "Picker is unchanged until _meta.selectable is true and php scripts/i18n-audit.php --selectable --strict-quality passes.\n");
exit(0);
