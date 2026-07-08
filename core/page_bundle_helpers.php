<?php
/**
 * page_bundle helpers — revision, lean payloads (P21-M4, M6).
 */

require_once __DIR__ . '/public_read.php';

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

function birkly_page_bundle_compute_revision($storage, array $requests, string $language = 'en'): string {
    $parts = ['lang:' . $language];
    foreach (birkly_page_bundle_collect_collections($requests) as $collectionSlug) {
        $dir = birkly_public_resolve_collection_dir($storage, $collectionSlug);
        if (!$dir) {
            $parts[] = $collectionSlug . ':missing';
            continue;
        }
        $metaPath = "collections/$dir/collection.json";
        $meta = $storage->read($metaPath);
        $parts[] = $collectionSlug . ':meta:' . ($meta['updated_at'] ?? $meta['created_at'] ?? '0');
        $entries = $storage->list("collections/$dir/entries");
        $maxUpdated = '0';
        $count = 0;
        foreach ($entries as $entryFile) {
            $data = $storage->read("collections/$dir/entries/$entryFile");
            if (!$data) {
                continue;
            }
            $count++;
            $updated = $data['updated_at'] ?? $data['published_at'] ?? $data['created_at'] ?? '';
            if ($updated > $maxUpdated) {
                $maxUpdated = $updated;
            }
        }
        $parts[] = $collectionSlug . ':n:' . $count . ':u:' . $maxUpdated;
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
