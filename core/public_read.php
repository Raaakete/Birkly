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

/**
 * Whitelisted client origin check for public API (no cmsHost fallback).
 */
function birkly_public_api_whitelisted_client_authorized(string $operation = 'read', ?string $collection = null): bool
{
    require_once __DIR__ . '/site_domain.php';
    if (birkly_request_is_same_cms_origin()) {
        return true;
    }

    $clientHost = birkly_client_request_host();
    if ($clientHost === '') {
        return false;
    }

    require_once __DIR__ . '/../api/domain_auth.php';
    $domainAuth = new DomainAuth();
    $validation = $domainAuth->validateDomain($clientHost);
    if (!$validation['valid']) {
        return false;
    }

    $permissionCheck = $domainAuth->hasPermission($clientHost, $operation, $collection);
    return !empty($permissionCheck['allowed']);
}

function birkly_collection_allows_public_read(array $collectionMeta): bool
{
    return !empty($collectionMeta['allow_public_read']);
}

function birkly_public_api_deny_message(string $reason, ?string $collectionSlug = null): string
{
    if ($reason === 'public_read_disabled' && $collectionSlug !== null && $collectionSlug !== '') {
        return 'Public read access is disabled for collection "' . $collectionSlug
            . '". Enable "Allow public read access" in the collection settings to allow API reads.';
    }
    return 'Authentication required';
}

/**
 * @param array<string, mixed>|null $collectionMeta
 * @return array{allowed:bool,reason:string}
 */
function birkly_public_api_auth_check(?array $collectionMeta, ?string $collectionSlug, string $mode = 'read'): array
{
    if ($mode === 'read' && is_array($collectionMeta)) {
        if (!birkly_collection_allows_public_read($collectionMeta)) {
            return ['allowed' => false, 'reason' => 'public_read_disabled'];
        }
    }

    $apiSettings = is_array($collectionMeta['api_settings'] ?? null) ? $collectionMeta['api_settings'] : [];
    if ($mode === 'write' && !empty($apiSettings['allow_anonymous_submit'])) {
        return ['allowed' => true, 'reason' => 'allow_anonymous_submit'];
    }

    if (is_array($collectionMeta)) {
        $apiKey = (string) ($_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '');
        $expectedKey = (string) ($collectionMeta['api_key'] ?? '');
        if ($apiKey !== '' && $expectedKey !== '' && hash_equals($expectedKey, $apiKey)) {
            return ['allowed' => true, 'reason' => 'collection_api_key'];
        }
    }

    $domainOperation = $mode === 'write' ? 'write' : 'read';
    if (birkly_public_api_whitelisted_client_authorized($domainOperation, $collectionSlug)) {
        return ['allowed' => true, 'reason' => 'domain_whitelist'];
    }

    return ['allowed' => false, 'reason' => $domainOperation . '_auth_required'];
}

/**
 * @param array<string, mixed> $collectionMeta
 * @return array{allowed:bool,reason:string}
 */
function birkly_public_read_auth_check(array $collectionMeta, string $collectionSlug): array
{
    return birkly_public_api_auth_check($collectionMeta, $collectionSlug, 'read');
}

/**
 * Drop deprecated api_settings keys from stored collection metadata (lazy persist).
 *
 * @param array<string, mixed> $collectionMeta
 * @return array<string, mixed>
 */
function birkly_public_maybe_migrate_collection_meta($storage, string $dir, array $collectionMeta): array
{
    require_once __DIR__ . '/ai_collection_config.php';

    if (isset($collectionMeta['api_settings']) && is_array($collectionMeta['api_settings'])) {
        if (!array_key_exists('allow_public_read', $collectionMeta)
            && !empty($collectionMeta['api_settings']['allow_anonymous_read'])) {
            $collectionMeta['allow_public_read'] = true;
        }
        $migrated = birkly_migrate_collection_api_settings($collectionMeta['api_settings']);
        if ($migrated !== $collectionMeta['api_settings']) {
            $collectionMeta['api_settings'] = $migrated;
            $storage->write("collections/$dir/collection.json", $collectionMeta);
        }
    }

    return $collectionMeta;
}

function birkly_public_collection_is_public_api(array $collectionMeta): bool
{
    return ($collectionMeta['entry_method'] ?? 'admin_only') === 'public_api';
}

function birkly_public_entry_field_truthy(mixed $entry, string $field): bool
{
    if (!is_array($entry) || $field === '' || !array_key_exists($field, $entry)) {
        return false;
    }
    $value = $entry[$field];
    if (is_bool($value)) {
        return $value;
    }
    if ($value === 1 || $value === '1') {
        return true;
    }
    require_once __DIR__ . '/birkly_template_engine.php';
    return birkly_template_engine_filter_literal_bool(trim((string) $value)) === true;
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
