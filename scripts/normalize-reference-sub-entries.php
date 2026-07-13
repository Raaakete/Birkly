#!/usr/bin/env php
<?php
/**
 * Move flat reference-project variant entries into Birkly sub_entries paths.
 *
 * Reference seeds store variants as products/entries/var-*.json with parent_id;
 * runtime expects products/entries/{parent_id}/sub_entries/variants/{id}.json
 */
$root = dirname(__DIR__);
$content = $root . '/content/collections';

if (!is_dir($content)) {
    exit(0);
}

$moved = 0;
foreach (glob($content . '/*/entries/*.json') ?: [] as $file) {
    $basename = basename($file);
    if ($basename === '.order.json') {
        continue;
    }
    $raw = file_get_contents($file);
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        continue;
    }
    $parentId = trim((string) ($data['parent_id'] ?? ''));
    $subCollection = trim((string) ($data['sub_collection'] ?? ''));
    if ($parentId === '' || $subCollection === '') {
        continue;
    }

    $collectionSlug = basename(dirname(dirname($file)));
    $parentCollection = trim((string) ($data['parent_collection'] ?? $collectionSlug));
    $entryId = trim((string) ($data['id'] ?? pathinfo($basename, PATHINFO_FILENAME)));

    $destDir = $content . '/' . $parentCollection . '/entries/' . $parentId . '/sub_entries/' . $subCollection;
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }
    $destFile = $destDir . '/' . $entryId . '.json';
    if ($file !== $destFile) {
        file_put_contents($destFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        unlink($file);
        $moved++;
        fwrite(STDOUT, "[normalize-sub-entries] $basename → $parentCollection/$parentId/sub_entries/$subCollection/$entryId.json\n");
    }
}

if ($moved > 0) {
    fwrite(STDOUT, "[normalize-sub-entries] OK: moved $moved sub-entry file(s)\n");
}

exit(0);
