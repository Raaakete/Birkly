<?php
/**
 * Copy Birkly-Docs markdown topics and build help-offline-manifest.json (P27-M12).
 *
 * CLI: php scripts/sync-birkly-docs-manifest.php <docs-root> <snapshots-dir> <manifest-out>
 */

declare(strict_types=1);

if ($argc < 4) {
    fwrite(STDERR, "Usage: php sync-birkly-docs-manifest.php <docs-root> <snapshots-dir> <manifest-out>\n");
    exit(1);
}

$docsRoot = rtrim((string) $argv[1], '/');
$snapDir = rtrim((string) $argv[2], '/');
$manifestOut = (string) $argv[3];
$manifestSrc = $docsRoot . '/help-manifest.json';

if (!is_file($manifestSrc)) {
    fwrite(STDERR, "Missing help-manifest.json at $manifestSrc\n");
    exit(1);
}

$live = json_decode((string) file_get_contents($manifestSrc), true);
if (!is_array($live) || !isset($live['topics']) || !is_array($live['topics'])) {
    fwrite(STDERR, "Invalid help-manifest.json\n");
    exit(1);
}

/** @var array<string, string> */
$snapshotNames = [
    'ai' => 'ai-overview.md',
    'automation' => 'automation-overview.md',
    'closed-alpha' => 'closed-alpha.md',
];

/**
 * @return list<string>
 */
function sync_birkly_docs_source_candidates(string $docsRoot, string $docsPath): array
{
    $rel = preg_replace('/\.html$/i', '.md', $docsPath) ?? $docsPath;
    return [
        $docsRoot . '/' . $rel,
        $docsRoot . '/content/' . $rel,
    ];
}

/**
 * @param array<string, mixed> $topic
 */
function sync_birkly_docs_snapshot_name(array $topic, array $snapshotNames): string
{
    $id = (string) ($topic['id'] ?? '');
    if ($id !== '' && isset($snapshotNames[$id])) {
        return $snapshotNames[$id];
    }
    if ($id !== '') {
        return $id . '.md';
    }

    $docsPath = (string) ($topic['docs_path'] ?? 'topic.md');
    return basename(preg_replace('/\.html$/i', '.md', $docsPath) ?? 'topic.md');
}

$offlineTopics = [];
$copied = 0;
$skipped = 0;

foreach ($live['topics'] as $topic) {
    if (!is_array($topic) || empty($topic['id'])) {
        continue;
    }

    $id = (string) $topic['id'];
    $docsPath = (string) ($topic['docs_path'] ?? '');
    $snapshotFile = sync_birkly_docs_snapshot_name($topic, $snapshotNames);
    $dest = $snapDir . '/' . $snapshotFile;

    $source = null;
    if ($docsPath !== '') {
        foreach (sync_birkly_docs_source_candidates($docsRoot, $docsPath) as $candidate) {
            if (is_file($candidate)) {
                $source = $candidate;
                break;
            }
        }
    }

    if ($source !== null) {
        if (!@copy($source, $dest)) {
            fwrite(STDERR, "WARN: failed to copy $source -> $dest\n");
        } else {
            $copied++;
        }
    } elseif (!is_file($dest)) {
        fwrite(STDERR, "WARN: no source for topic $id ($docsPath); snapshot missing\n");
        $skipped++;
        continue;
    }

    if (!is_file($dest)) {
        $skipped++;
        continue;
    }

    $entry = [
        'id' => $id,
        'title' => (string) ($topic['title'] ?? $id),
        'keywords' => $topic['keywords'] ?? [],
        'snapshot' => '/admin/docs/snapshots/' . $snapshotFile,
        'docs_path' => $docsPath,
    ];

    if (isset($topic['version'])) {
        $entry['version'] = $topic['version'];
    }

    $offlineTopics[] = $entry;
}

$offline = [
    'version' => (int) ($live['version'] ?? 2),
    'docs_base_url' => (string) ($live['docs_base_url'] ?? 'https://docs.birkly.com'),
    'topics' => $offlineTopics,
    'manifest_source' => 'offline',
    'offline' => true,
    'synced_at' => date('c'),
    'synced_from' => $docsRoot,
];

if (!is_dir(dirname($manifestOut))) {
    mkdir(dirname($manifestOut), 0755, true);
}

$json = json_encode($offline, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    fwrite(STDERR, "Failed to encode offline manifest\n");
    exit(1);
}

file_put_contents($manifestOut, $json . "\n");

echo "Copied $copied topic(s); skipped $skipped; wrote " . count($offlineTopics) . " manifest entries\n";
exit(0);
