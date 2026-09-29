#!/usr/bin/env php
<?php
/**
 * @deprecated Do not use for production French catalogs — DE substring heuristics produced DE/FR mixes.
 * Prefer translation from lang/en.json; validate with scripts/i18n-audit.php --selectable.
 *
 * Generate fr-batch-N.json from en-batch-N.json using core/de heuristics + supplement map.
 *
 * Usage: php scripts/i18n-generate-plugin-fr-batches.php <plugin-id> [supplement.json]
 */
declare(strict_types=1);

const PLACEHOLDER_RE = '/(\{\{[a-zA-Z0-9_]+\}\}|\{[a-zA-Z0-9_.]+\}|%[sd]|@[a-zA-Z0-9_.:-]+|<[^>]+>|000000|email@example\.com)/';

$argv = $_SERVER['argv'] ?? [];
array_shift($argv);
$pluginId = $argv[0] ?? '';
$supplementFile = $argv[1] ?? '/opt/cursor/artifacts/fr-plugins/fr-supplement.json';
if ($pluginId === '') {
    fwrite(STDERR, "Usage: php scripts/i18n-generate-plugin-fr-batches.php <plugin-id> [supplement.json]\n");
    exit(1);
}

$artifactDir = '/opt/cursor/artifacts/fr-plugins/' . $pluginId;
if (!is_dir($artifactDir)) {
    fwrite(STDERR, "Missing artifact dir {$artifactDir}\n");
    exit(1);
}

function flatten(array $data, string $prefix = ''): array
{
    $out = [];
    foreach ($data as $key => $value) {
        if ($key === '_meta') {
            continue;
        }
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        if (is_array($value)) {
            $out += flatten($value, $path);
        } else {
            $out[$path] = (string) $value;
        }
    }
    return $out;
}

function protect(string $text): array
{
    $tokens = [];
    $protected = preg_replace_callback(PLACEHOLDER_RE, function ($m) use (&$tokens) {
        $tokens[] = $m[0];
        return '__PH' . (count($tokens) - 1) . '__';
    }, $text);
    return [$protected, $tokens];
}

function restore(string $text, array $tokens): string
{
    foreach ($tokens as $i => $tok) {
        $text = str_replace('__PH' . $i . '__', $tok, $text);
    }
    return $text;
}

function buildCoreValueMap(): array
{
    $root = dirname(__DIR__);
    $en = flatten(json_decode((string) file_get_contents($root . '/lang/en.json'), true));
    $fr = flatten(json_decode((string) file_get_contents($root . '/lang/fr.json'), true));
    $map = [];
    foreach ($en as $k => $enVal) {
        if (isset($fr[$k]) && $fr[$k] !== $enVal) {
            if (!isset($map[$enVal]) || strlen($fr[$k]) > strlen($map[$enVal])) {
                $map[$enVal] = $fr[$k];
            }
        }
    }
    uksort($map, static fn ($a, $b) => strlen($b) <=> strlen($a));
    return $map;
}

function buildDeFrCoreMap(): array
{
    $root = dirname(__DIR__);
    $de = flatten(json_decode((string) file_get_contents($root . '/lang/de.json'), true));
    $fr = flatten(json_decode((string) file_get_contents($root . '/lang/fr.json'), true));
    $map = [];
    foreach ($de as $k => $deVal) {
        if (isset($fr[$k]) && $fr[$k] !== $deVal) {
            if (!isset($map[$deVal]) || strlen($fr[$k]) > strlen($map[$deVal])) {
                $map[$deVal] = $fr[$k];
            }
        }
    }
    uksort($map, static fn ($a, $b) => strlen($b) <=> strlen($a));
    return $map;
}

function buildPluginDeHints(): array
{
    $pairs = [
        ['/agent/repos/Birkly-Plugin-Commerce', 'commerce'],
        ['/agent/repos/Birkly-Plugin-User', 'user_management'],
        ['/agent/repos/Birkly-Plugin-Automation', 'automation'],
        ['/agent/repos/Birkly-Plugin-Events', 'events'],
        ['/agent/repos/Birkly-Plugin-Studio', 'studio'],
    ];
    $enToDe = [];
    foreach ($pairs as [$root, $id]) {
        if (!is_file("$root/lang/en.json") || !is_file("$root/lang/de.json")) {
            continue;
        }
        $enFlat = flatten(json_decode((string) file_get_contents("$root/lang/en.json"), true)['plugins'][$id] ?? []);
        $deFlat = flatten(json_decode((string) file_get_contents("$root/lang/de.json"), true)['plugins'][$id] ?? []);
        foreach ($enFlat as $k => $enVal) {
            if (isset($deFlat[$k]) && $deFlat[$k] !== $enVal) {
                $enToDe[$enVal] = $deFlat[$k];
            }
        }
    }
    return $enToDe;
}

function translateHeuristic(string $en, array $coreMap, array $deFrMap, array $enDe): string
{
    if (isset($coreMap[$en])) {
        return $coreMap[$en];
    }
    if (isset($enDe[$en])) {
        $de = $enDe[$en];
        if (isset($deFrMap[$de])) {
            return $deFrMap[$de];
        }
        [$p, $tok] = protect($de);
        if (isset($deFrMap[$p])) {
            return restore($deFrMap[$p], $tok);
        }
    }
    [$protected, $tokens] = protect($en);
    if (isset($coreMap[$protected])) {
        return restore($coreMap[$protected], $tokens);
    }
    $out = $protected;
    foreach ($coreMap as $from => $to) {
        if (strlen($from) < 4) {
            continue;
        }
        if (str_contains($out, $from)) {
            $out = str_replace($from, $to, $out);
        }
    }
    if (isset($enDe[$en])) {
        $de = $enDe[$en];
        [$dp, $dtok] = protect($de);
        foreach ($deFrMap as $from => $to) {
            if (strlen($from) < 4) {
                continue;
            }
            if (str_contains($dp, $from)) {
                $dp = str_replace($from, $to, $dp);
            }
        }
        if ($dp !== protect($de)[0]) {
            return restore($dp, $dtok);
        }
    }
    return restore($out, $tokens);
}

$coreMap = buildCoreValueMap();
$deFrMap = buildDeFrCoreMap();
$enDe = buildPluginDeHints();
$supplement = [];
if (is_file($supplementFile)) {
    $supplement = json_decode((string) file_get_contents($supplementFile), true) ?: [];
}

$written = 0;
$same = 0;
foreach (glob($artifactDir . '/en-batch-*.json') as $enFile) {
    if (!preg_match('/en-batch-(\d+)\.json$/', $enFile, $m)) {
        continue;
    }
    $batchNum = $m[1];
    $enBatch = json_decode((string) file_get_contents($enFile), true);
    if (!is_array($enBatch)) {
        continue;
    }
    $frBatch = [];
    foreach ($enBatch as $key => $enVal) {
        $fr = $supplement[$enVal] ?? translateHeuristic($enVal, $coreMap, $deFrMap, $enDe);
        $frBatch[$key] = $fr;
        if ($fr === $enVal) {
            $same++;
        }
    }
    $outFile = $artifactDir . '/fr-batch-' . $batchNum . '.json';
    file_put_contents($outFile, json_encode($frBatch, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    $written++;
}

fwrite(STDOUT, "plugin={$pluginId} fr-batches={$written} unchanged={$same}\n");
