<?php
/**
 * page_bundle helpers — revision, lean payloads (P21-M4, M6).
 * P58-M1: cheap content fingerprint (mtime/size + persisted revision) for SSR cache keys.
 */

require_once __DIR__ . '/public_read.php';
require_once __DIR__ . '/birkly_data_paths.php';

function birkly_page_bundle_collect_collections(array $requests): array {
    $collections = [];
    foreach ($requests as $req) {
        if (!is_array($req)) {
            continue;
        }
        $type = $req['type'] ?? '';
        if ($type === 'list_entries' || $type === 'get_entry') {
            $slug = $req['collection'] ?? '';
            if ($slug !== '') {
                $collections[] = $slug;
            }
        } elseif ($type === 'sub_entries') {
            $parent = $req['parent_collection'] ?? '';
            if ($parent !== '') {
                $collections[] = $parent;
            }
        }
    }
    $collections[] = 'site_elements';
    return array_values(array_unique($collections));
}

/**
 * Persisted monotonic revision bumped on content writes / SSR invalidation.
 */
function birkly_public_content_revision_path(): string
{
    return birkly_data_disk_path() . '/cache/public_content_revision.txt';
}

function birkly_public_content_revision_read(): string
{
    $path = birkly_public_content_revision_path();
    if (!is_file($path)) {
        return '0';
    }
    $raw = trim((string) @file_get_contents($path));
    return $raw !== '' ? $raw : '0';
}

/**
 * Bump the persisted public content revision (invalidates SSR/page_bundle cache keys).
 */
function birkly_public_content_revision_bump(): void
{
    $path = birkly_public_content_revision_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $next = (string) ((int) birkly_public_content_revision_read() + 1);
    @file_put_contents($path, $next);
}

/**
 * @return string|null Absolute filesystem base for flat-file storage drivers.
 */
function birkly_storage_filesystem_base($storage): ?string
{
    if (is_object($storage) && property_exists($storage, 'basePath')) {
        return rtrim((string) $storage->basePath, '/');
    }
    return null;
}

/**
 * Cheap collection fingerprint from file mtimes/sizes (no entry JSON reads).
 */
function birkly_collection_dir_fingerprint($storage, string $collectionDir): string
{
    $base = birkly_storage_filesystem_base($storage);
    if ($base === null) {
        return birkly_collection_dir_fingerprint_legacy($storage, $collectionDir);
    }

    $metaFull = $base . '/collections/' . $collectionDir . '/collection.json';
    $parts = [];
    if (is_file($metaFull)) {
        $parts[] = 'm:' . filemtime($metaFull) . ':' . filesize($metaFull);
    } else {
        $parts[] = 'm:missing';
    }

    $entriesDir = $base . '/collections/' . $collectionDir . '/entries';
    $entryParts = [];
    if (is_dir($entriesDir)) {
        foreach (scandir($entriesDir) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $full = $entriesDir . '/' . $file;
            if (is_file($full)) {
                $entryParts[] = $file . ':' . filemtime($full) . ':' . filesize($full);
            }
        }
    }
    sort($entryParts, SORT_STRING);
    $parts[] = 'e:' . count($entryParts) . ':' . implode(',', $entryParts);

    return implode('|', $parts);
}

/**
 * Legacy fingerprint for non–flat-file storage (reads meta + entry timestamps).
 */
function birkly_collection_dir_fingerprint_legacy($storage, string $collectionDir): string
{
    $metaPath = "collections/$collectionDir/collection.json";
    $meta = $storage->read($metaPath);
    $parts = ['meta:' . ($meta['updated_at'] ?? $meta['created_at'] ?? '0')];
    $entries = $storage->list("collections/$collectionDir/entries");
    $maxUpdated = '0';
    $count = 0;
    foreach ($entries as $entryFile) {
        $data = $storage->read("collections/$collectionDir/entries/$entryFile");
        if (!$data) {
            continue;
        }
        $count++;
        $updated = $data['updated_at'] ?? $data['published_at'] ?? $data['created_at'] ?? '';
        if ($updated > $maxUpdated) {
            $maxUpdated = $updated;
        }
    }
    $parts[] = 'n:' . $count . ':u:' . $maxUpdated;
    return implode('|', $parts);
}

/**
 * Stable revision for cache keys — stat-based, no full entry body reads on HIT path.
 *
 * @param list<string> $extraPaths Optional absolute paths (e.g. project HTML template).
 */
function birkly_page_bundle_compute_revision($storage, array $requests, string $language = 'en', array $extraPaths = []): string {
    $parts = ['lang:' . $language, 'crev:' . birkly_public_content_revision_read()];
    foreach (birkly_page_bundle_collect_collections($requests) as $collectionSlug) {
        $dir = birkly_public_resolve_collection_dir($storage, $collectionSlug);
        if (!$dir) {
            $parts[] = $collectionSlug . ':missing';
            continue;
        }
        $parts[] = $collectionSlug . ':' . birkly_collection_dir_fingerprint($storage, $dir);
    }
    foreach ($extraPaths as $path) {
        $path = trim((string) $path);
        if ($path !== '' && is_file($path)) {
            $parts[] = 'file:' . $path . ':' . filemtime($path) . ':' . filesize($path);
        }
    }
    return substr(hash('sha256', implode('|', $parts)), 0, 16);
}

function birkly_page_bundle_prune_entry($entry, bool $lean = true) {
    if (!$lean || !is_array($entry)) {
        return $entry;
    }
    $keep = ['id', 'slug', 'status', 'title', 'created_at', 'updated_at', 'published_at', 'excerpt', 'content', 'description', 'price', 'icon', 'featured', 'tags', 'category', 'author', 'date', 'start', 'venue', 'commerce'];
    $pruned = [];
    foreach ($keep as $key) {
        if (array_key_exists($key, $entry)) {
            $pruned[$key] = $entry[$key];
        }
    }
    foreach ($entry as $key => $value) {
        if (is_string($key) && strlen($key) === 2 && is_array($value)) {
            $pruned[$key] = $value;
        }
    }
    foreach ($entry as $key => $value) {
        if (!array_key_exists($key, $pruned) && is_string($key) && $key[0] !== '_' && strpos($key, '__') !== 0) {
            if (is_scalar($value) || is_array($value)) {
                $pruned[$key] = $value;
            }
        }
    }
    return $pruned;
}

function birkly_page_bundle_prune_list(array $entries, bool $lean = true): array {
    if (!$lean) {
        return $entries;
    }
    return array_map(function ($entry) use ($lean) {
        return birkly_page_bundle_prune_entry($entry, $lean);
    }, $entries);
}
