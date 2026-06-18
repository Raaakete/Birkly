<?php
/**
 * Shared read helpers for public API + page_bundle.php (M4).
 */

require_once __DIR__ . '/storage/StorageFactory.php';
require_once __DIR__ . '/cache.php';

function birkly_public_resolve_collection_dir($storage, $collectionSlug, $collectionId = '') {
    $collections = $storage->list('collections');
    if ($collectionId) {
        foreach ($collections as $d) {
            $m = $storage->read("collections/$d/collection.json");
            if ($m && ($m['id'] ?? '') === $collectionId) {
                return $d;
            }
        }
        return null;
    }
    if (!$collectionSlug) {
        return null;
    }
    foreach ($collections as $d) {
        $m = $storage->read("collections/$d/collection.json");
        if ($m && ($m['slug'] ?? '') === $collectionSlug) {
            return $d;
        }
    }
    return null;
}

function birkly_public_transform_entry_for_language($entry, $language, $multilanguageEnabled, $multilanguageLanguages) {
    if (!$language || !$multilanguageEnabled) {
        return $entry;
    }
    $hasMultilang = false;
    foreach ($multilanguageLanguages as $lang) {
        if (isset($entry[$lang]) && is_array($entry[$lang])) {
            $hasMultilang = true;
            break;
        }
    }
    if ($hasMultilang && isset($entry[$language])) {
        $transformed = array_merge($entry, $entry[$language]);
        $transformed['id'] = $entry['id'] ?? '';
        $transformed['slug'] = $entry['slug'] ?? '';
        $transformed['status'] = $entry['status'] ?? 'draft';
        $transformed['created_at'] = $entry['created_at'] ?? '';
        $transformed['updated_at'] = $entry['updated_at'] ?? '';
        $transformed['published_at'] = $entry['published_at'] ?? '';
        return $transformed;
    }
    return $entry;
}

function birkly_public_list_entries($storage, $collectionSlug, $language = 'en') {
    $dir = birkly_public_resolve_collection_dir($storage, $collectionSlug);
    if (!$dir) {
        return ['status' => 'error', 'message' => 'Invalid request'];
    }

    $collectionMeta = $storage->read("collections/$dir/collection.json");
    $collectionSlugResolved = $collectionMeta['slug'] ?? '';
    $multilanguageEnabled = !empty($collectionMeta['multilanguage']['enabled']);
    $multilanguageLanguages = $collectionMeta['multilanguage']['languages'] ?? [];

    if ($multilanguageEnabled && !empty($multilanguageLanguages)) {
        if (!in_array($language, $multilanguageLanguages, true)) {
            $language = $multilanguageLanguages[0] ?? 'en';
        }
    } else {
        $language = null;
    }

    $cache = new Cache();
    $cachedEntries = $collectionSlugResolved ? $cache->getEntries($collectionSlugResolved) : null;

    if ($cachedEntries === null) {
        $entries = $storage->list("collections/$dir/entries");
        $entryData = [];
        foreach ($entries as $entryFile) {
            $data = $storage->read("collections/$dir/entries/$entryFile");
            if ($data) {
                $entryData[] = $data;
            }
        }
        if ($collectionSlugResolved) {
            $cache->setEntries($collectionSlugResolved, $entryData);
        }
        $cachedEntries = $entryData;
    }

    $publishedEntries = array_values(array_filter($cachedEntries, function ($entry) {
        return ($entry['status'] ?? 'draft') === 'published';
    }));

    if ($language && $multilanguageEnabled) {
        $publishedEntries = array_map(function ($entry) use ($language, $multilanguageEnabled, $multilanguageLanguages) {
            return birkly_public_transform_entry_for_language($entry, $language, $multilanguageEnabled, $multilanguageLanguages);
        }, $publishedEntries);
    }

    return ['status' => 'ok', 'data' => ['entries' => $publishedEntries]];
}

function birkly_public_get_entry($storage, $collectionSlug, $slug = '', $entryId = '', $language = 'en') {
    $dir = birkly_public_resolve_collection_dir($storage, $collectionSlug);
    if (!$dir) {
        return ['status' => 'error', 'message' => 'Invalid request'];
    }

    $collectionMeta = $storage->read("collections/$dir/collection.json");
    $multilanguageEnabled = !empty($collectionMeta['multilanguage']['enabled']);
    $multilanguageLanguages = $collectionMeta['multilanguage']['languages'] ?? [];

    if ($multilanguageEnabled && !empty($multilanguageLanguages)) {
        if (!in_array($language, $multilanguageLanguages, true)) {
            $language = $multilanguageLanguages[0] ?? 'en';
        }
    } else {
        $language = null;
    }

    $data = null;
    if ($slug) {
        $entries = $storage->list("collections/$dir/entries");
        foreach ($entries as $entryFile) {
            $entryData = $storage->read("collections/$dir/entries/$entryFile");
            if ($entryData && isset($entryData['slug']) && $entryData['slug'] === $slug) {
                $data = $entryData;
                break;
            }
        }
    } elseif ($entryId) {
        $data = $storage->read("collections/$dir/entries/$entryId.json");
    }

    if (!$data || ($data['status'] ?? 'draft') !== 'published') {
        return ['status' => 'error', 'message' => 'Entry not found or not published'];
    }

    if ($language && $multilanguageEnabled) {
        $data = birkly_public_transform_entry_for_language($data, $language, $multilanguageEnabled, $multilanguageLanguages);
    }

    return ['status' => 'ok', 'data' => ['entry' => $data]];
}

function birkly_public_list_sub_entries($parentCollection, $parentEntryId, $subCollection) {
    require_once __DIR__ . '/sub_entries.php';
    $entries = getSubEntries($parentCollection, $parentEntryId, $subCollection);
    return ['status' => 'ok', 'data' => ['entries' => array_values($entries)]];
}
