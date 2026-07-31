<?php
/**
 * Copy Birkly-Docs markdown topics and build help manifests (P27-M12, P50-M2).
 *
 * CLI: php scripts/sync-birkly-docs-manifest.php <docs-root> <snapshots-dir> <offline-manifest-out> [live-manifest-out]
 *
 * When live-manifest-out is provided, copies help-manifest.json from Birkly-Docs verbatim.
 */

declare(strict_types=1);

if ($argc < 4) {
    fwrite(STDERR, "Usage: php sync-birkly-docs-manifest.php <docs-root> <snapshots-dir> <offline-manifest-out> [live-manifest-out]\n");
    exit(1);
}

$docsRoot = rtrim((string) $argv[1], '/');
$snapDir = rtrim((string) $argv[2], '/');
$manifestOut = (string) $argv[3];
$liveManifestOut = isset($argv[4]) ? (string) $argv[4] : '';
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

/** @var array<string, string> Legacy id → snapshot filename overrides */
$snapshotNames = [
    'ai' => 'ai-overview.md',
    'automation' => 'automation-overview.md',
    'closed-alpha' => 'closed-alpha.md',
];

/** @var array<string, array{id: string, label: string}> docs_path prefix → nav section */
$navSectionFromPrefix = [
    '00-overview' => ['id' => 'overview', 'label' => 'Overview'],
    '01-admin' => ['id' => 'admin', 'label' => 'Admin'],
    '02-content' => ['id' => 'content', 'label' => 'Content model'],
    '03-templating' => ['id' => 'templating', 'label' => 'Templating'],
    '04-integration' => ['id' => 'integration', 'label' => 'Integration'],
    '05-automation' => ['id' => 'automation', 'label' => 'Automation'],
    '06-extensibility' => ['id' => 'extensibility', 'label' => 'Extensibility'],
    '06-plugin-economy' => ['id' => 'plugin-economy', 'label' => 'Plugin economy'],
    '07-operations' => ['id' => 'operations', 'label' => 'Operations'],
];

function sync_birkly_docs_nav_section(string $docsPath, array $navSectionFromPrefix): array
{
    foreach ($navSectionFromPrefix as $prefix => $section) {
        if (str_starts_with($docsPath, $prefix . '/')) {
            return $section;
        }
    }

    return ['id' => 'platform', 'label' => 'Platform'];
}

function sync_birkly_docs_has_further_reading(string $markdown): bool
{
    return (bool) preg_match('/^##\s+(Further reading|Related)\s*$/mi', $markdown);
}

/**
 * Append a Further reading section when the source doc has none.
 *
 * @param list<array<string, mixed>> $peers
 */
function sync_birkly_docs_append_further_reading(string $markdown, array $peers, string $currentId): string
{
    if (sync_birkly_docs_has_further_reading($markdown)) {
        return $markdown;
    }

    $links = [];
    foreach ($peers as $peer) {
        if (!is_array($peer) || ($peer['id'] ?? '') === $currentId) {
            continue;
        }
        $title = (string) ($peer['title'] ?? $peer['id'] ?? '');
        $peerId = (string) ($peer['id'] ?? '');
        if ($title === '' || $peerId === '') {
            continue;
        }
        $links[] = '- [' . $title . '](' . $peerId . '.md)';
        if (count($links) >= 4) {
            break;
        }
    }

    if ($links === []) {
        return $markdown;
    }

    $suffix = "\n\n## Further reading\n\n" . implode("\n", $links) . "\n";
    return rtrim($markdown) . $suffix;
}

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
 * One snapshot file per Help topic id (P50: no shared entries.md reuse).
 *
 * @param array<string, mixed> $topic
 * @param array<string, string> $snapshotNames
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
$topicsByNavSection = [];

foreach ($live['topics'] as $topic) {
    if (!is_array($topic) || empty($topic['id'])) {
        continue;
    }
    $docsPath = (string) ($topic['docs_path'] ?? '');
    $nav = sync_birkly_docs_nav_section($docsPath, $navSectionFromPrefix);
    $sectionId = (string) ($topic['nav_section'] ?? $nav['id']);
    if (!isset($topicsByNavSection[$sectionId])) {
        $topicsByNavSection[$sectionId] = [];
    }
    $topicsByNavSection[$sectionId][] = $topic;
}

foreach ($live['topics'] as $topic) {
    if (!is_array($topic) || empty($topic['id'])) {
        continue;
    }

    $id = (string) $topic['id'];
    $docsPath = (string) ($topic['docs_path'] ?? '');
    $nav = sync_birkly_docs_nav_section($docsPath, $navSectionFromPrefix);
    $sectionId = (string) ($topic['nav_section'] ?? $nav['id']);
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
        $markdown = (string) file_get_contents($source);
        $peers = $topicsByNavSection[$sectionId] ?? [];
        $markdown = sync_birkly_docs_append_further_reading($markdown, $peers, $id);
        if (!@file_put_contents($dest, $markdown)) {
            fwrite(STDERR, "WARN: failed to write $dest\n");
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
        'nav_section' => $sectionId,
        'nav_section_label' => (string) ($topic['nav_section_label'] ?? $nav['label']),
    ];

    if (isset($topic['version'])) {
        $entry['version'] = $topic['version'];
    }
    if (isset($topic['ai_topic']) && $topic['ai_topic'] !== '') {
        $entry['ai_topic'] = (string) $topic['ai_topic'];
    }
    if (isset($topic['section']) && $topic['section'] !== '') {
        $entry['section'] = (string) $topic['section'];
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

if ($liveManifestOut !== '') {
    if (!is_dir(dirname($liveManifestOut))) {
        mkdir(dirname($liveManifestOut), 0755, true);
    }
    $liveJson = json_encode($live, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($liveJson === false) {
        fwrite(STDERR, "Failed to encode live manifest copy\n");
        exit(1);
    }
    file_put_contents($liveManifestOut, $liveJson . "\n");
}

echo "Copied $copied topic(s); skipped $skipped; wrote " . count($offlineTopics) . " offline manifest entries\n";
if ($liveManifestOut !== '') {
    echo "Live manifest copied to $liveManifestOut\n";
}
exit(0);
