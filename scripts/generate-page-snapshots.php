#!/usr/bin/env php
<?php
/**
 * Generate dev/demo page bundle snapshots (P2-M6-T2).
 *
 * Usage:
 *   php scripts/generate-page-snapshots.php
 *   php scripts/generate-page-snapshots.php --manifest=path/to/manifest.json
 */

$root = dirname(__DIR__);
require_once $root . '/core/storage/StorageFactory.php';
require_once $root . '/core/page_bundle_runner.php';

$manifestPath = $root . '/scripts/fixtures/demo-page-snapshots.json';
foreach (array_slice($argv, 1) as $arg) {
    if (strpos($arg, '--manifest=') === 0) {
        $manifestPath = substr($arg, strlen('--manifest='));
        if ($manifestPath[0] !== '/') {
            $manifestPath = $root . '/' . $manifestPath;
        }
    }
}

if (!is_file($manifestPath)) {
    fwrite(STDERR, "Manifest not found: $manifestPath\n");
    exit(1);
}

$manifest = json_decode(file_get_contents($manifestPath), true);
if (!is_array($manifest) || empty($manifest['pages']) || !is_array($manifest['pages'])) {
    fwrite(STDERR, "Invalid manifest: $manifestPath\n");
    exit(1);
}

$language = $manifest['language'] ?? 'en';
$outDir = $root . '/cache/page-snapshots';
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

$storage = get_storage($root . '/content');
$built = [];

foreach ($manifest['pages'] as $pageDef) {
    $page = trim((string)($pageDef['page'] ?? ''));
    $requests = $pageDef['requests'] ?? [];
    if ($page === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $page) || !is_array($requests)) {
        fwrite(STDERR, "Skipping invalid page definition\n");
        continue;
    }

    $result = birkly_run_page_bundle_requests($storage, $requests, $language);
    $snapshot = [
        'page' => $page,
        'slug' => null,
        'generated_at' => gmdate('c'),
        'requests' => $requests,
        'data' => $result['data'] ?? [],
        'errors' => $result['errors'] ?? [],
    ];

    $path = $outDir . '/' . $page . '.json';
    file_put_contents($path, json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    $built[] = $page;
}

echo 'Generated page snapshots (' . count($built) . "):\n";
echo implode("\n", $built) . "\n";
exit(0);
