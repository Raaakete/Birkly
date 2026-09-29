<?php
/**
 * Copy the Birkly-Docs help manifest. Does not copy article bodies (P104).
 *
 * CLI: php scripts/sync-birkly-docs-manifest.php <docs-root> <live-manifest-out>
 */

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php sync-birkly-docs-manifest.php <docs-root> <live-manifest-out>\n");
    fwrite(STDERR, "Article bodies are fetched from the docs origin. This command only copies help-manifest.json.\n");
    exit(1);
}

$docsRoot = rtrim((string) $argv[1], '/');
$liveManifestOut = (string) $argv[2];
$manifestSrc = $docsRoot . '/help-manifest.json';

if (!is_file($manifestSrc)) {
    fwrite(STDERR, "Missing help-manifest.json at $manifestSrc\n");
    exit(1);
}

$raw = (string) file_get_contents($manifestSrc);
$live = json_decode($raw, true);
if (!is_array($live) || !isset($live['topics']) || !is_array($live['topics'])) {
    fwrite(STDERR, "Invalid help-manifest.json\n");
    exit(1);
}

$dir = dirname($liveManifestOut);
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

if (file_put_contents($liveManifestOut, $raw) === false) {
    fwrite(STDERR, "Failed to write $liveManifestOut\n");
    exit(1);
}

fwrite(STDOUT, "Copied help manifest to $liveManifestOut (" . count($live['topics']) . " topics). No article bodies.\n");
