<?php
/**
 * Shared read helpers for public API + page_bundle.php (M4).
 */

require_once __DIR__ . '/storage/StorageFactory.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/entry_slug.php';

/**
 * Match collection slug to on-disk directory (exact slug, then normalized hyphen form).
 */
function birkly_public_collection_slug_matches(array $meta, string $collectionSlug): bool
{
    $needle = birkly_normalize_slug($collectionSlug);
    if ($needle === '') {
        return false;
    }
    $candidates = [
        (string) ($meta['slug'] ?? ''),
        (string) ($meta['name'] ?? ''),
        (string) ($meta['title'] ?? ''),
    ];
    foreach ($candidates as $candidate) {
        if ($candidate !== '' && birkly_normalize_slug($candidate) === $needle) {
            return true;
        }
    }
    return false;
}

function birkly_public_resolve_collection_dir($storage, $collectionSlug, $collectionId = '') {
    static $cache = [];
    $cacheKey = $collectionId !== '' ? ('id:' . $collectionId) : ('slug:' . $collectionSlug);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $collections = $storage->list('collections');
    if ($collectionId) {
        foreach ($collections as $d) {
            $m = $storage->read("collections/$d/collection.json");
            if ($m && ($m['id'] ?? '') === $collectionId) {
                $cache[$cacheKey] = $d;
                return $d;
            }
        }
        $cache[$cacheKey] = null;
        return null;
    }
    if (!$collectionSlug) {
        $cache[$cacheKey] = null;
        return null;
    }
    foreach ($collections as $d) {
        $m = $storage->read("collections/$d/collection.json");
        if ($m && birkly_public_collection_slug_matches($m, $collectionSlug)) {
            $cache[$cacheKey] = $d;
            return $d;
        }
    }
    $cache[$cacheKey] = null;
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

/** @return list<string> */
function birkly_public_system_read_fields(): array
{
    return ['id', 'slug', 'status', 'created_at', 'updated_at', 'published_at'];
}

function birkly_public_entry_field_truthy(mixed $entry, string $field): bool
{
    if (!is_array($entry) || $field === '') {
        return false;
    }
    $value = $entry[$field] ?? null;
    if (is_bool($value)) {
        return $value;
    }
    $s = strtolower(trim((string) $value));
    return in_array($s, ['1', 'true', 'yes', 'on', 'featured'], true);
}

/**
 * @param array<string, mixed> $entry
 * @param list<string> $allowedFields
 * @return array<string, mixed>
 */
function birkly_public_sanitize_entry_fields(array $entry, array $allowedFields): array
{
    $system = birkly_public_system_read_fields();
    $out = [];
    foreach (array_unique(array_merge($system, $allowedFields)) as $key) {
        if (array_key_exists($key, $entry)) {
            $out[$key] = $entry[$key];
        }
    }
    return $out;
}

/**
 * @param list<mixed> $entries
 * @param array<string, mixed> $collectionMeta
 * @return list<array<string, mixed>>
 */
function birkly_public_apply_collection_read_settings(array $entries, array $collectionMeta): array
{
    $apiSettings = is_array($collectionMeta['api_settings'] ?? null) ? $collectionMeta['api_settings'] : [];
    $requires = trim((string) ($apiSettings['public_read_requires'] ?? ''));
    $fields = $apiSettings['public_read_fields'] ?? null;
    $allowedFields = [];
    if (is_string($fields)) {
        $fields = array_values(array_filter(array_map('trim', explode(',', $fields))));
    }
    if (is_array($fields)) {
        foreach ($fields as $field) {
            if (is_string($field) && trim($field) !== '') {
                $allowedFields[] = trim($field);
            }
        }
    }
    if ($requires === '' && $allowedFields === []) {
        return $entries;
    }

    $out = [];
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if ($requires !== '' && !birkly_public_entry_field_truthy($entry, $requires)) {
            continue;
        }
        $out[] = $allowedFields === [] ? $entry : birkly_public_sanitize_entry_fields($entry, $allowedFields);
    }
    return $out;
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
            return birkly_public_entry_with_effective_slug(
                birkly_public_transform_entry_for_language($entry, $language, $multilanguageEnabled, $multilanguageLanguages)
            );
        }, $publishedEntries);
    } else {
        $publishedEntries = array_map('birkly_public_entry_with_effective_slug', $publishedEntries);
    }

    $publishedEntries = birkly_public_apply_collection_read_settings($publishedEntries, is_array($collectionMeta) ? $collectionMeta : []);

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
            if ($entryData && birkly_entry_matches_slug($entryData, $slug)) {
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

    $data = birkly_public_entry_with_effective_slug($data);

    return ['status' => 'ok', 'data' => ['entry' => $data]];
}

function birkly_public_list_sub_entries($parentCollection, $parentEntryId, $subCollection) {
    require_once __DIR__ . '/sub_entries.php';
    $entries = getSubEntries($parentCollection, $parentEntryId, $subCollection);
    return ['status' => 'ok', 'data' => ['entries' => array_values($entries)]];
}
