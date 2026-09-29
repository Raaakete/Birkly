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

/**
 * Bootstrap active plugins once before public-read filter hooks run.
 */
function birkly_public_bootstrap_plugins(): void
{
    if (!function_exists('birkly_public_load_active_plugins')) {
        require_once __DIR__ . '/public_bootstrap.php';
    }
    birkly_public_load_active_plugins();
}

/**
 * Bundle data key for sub-entry lists (supports nested levels).
 */
function birkly_public_sub_entries_bundle_key(
    string $parentCollection,
    string $parentEntryId,
    string $subCollection,
    ?string $parentSubCollection = null,
    ?string $parentSubEntryId = null
): string {
    if ($parentSubCollection !== null && $parentSubCollection !== ''
        && $parentSubEntryId !== null && $parentSubEntryId !== '') {
        return '__sub__:' . $parentCollection . ':' . $parentEntryId . ':'
            . $parentSubCollection . ':' . $parentSubEntryId . ':' . $subCollection;
    }
    return '__sub__:' . $parentCollection . ':' . $parentEntryId . ':' . $subCollection;
}

/**
 * Resolve user_current for SSR/page_bundle render data when a site session exists.
 *
 * @return array<string, mixed>|null
 */
function birkly_public_resolve_user_current(): ?array
{
    birkly_public_bootstrap_plugins();

    if (function_exists('get_template_function')) {
        $callback = get_template_function('user_current');
        if (is_callable($callback)) {
            try {
                $user = call_user_func($callback);
                return is_array($user) ? $user : null;
            } catch (Throwable $e) {
                error_log('birkly_public_resolve_user_current: ' . $e->getMessage());
            }
        }
    }

    return null;
}

/**
 * @param list<array<string, mixed>> $subCollections
 * @return list<array<string, mixed>>
 */
function birkly_public_flatten_sub_collection_configs(array $subCollections): array
{
    $flat = [];
    foreach ($subCollections as $sub) {
        if (!is_array($sub)) {
            continue;
        }
        $flat[] = $sub;
        $nested = $sub['sub_collections'] ?? [];
        if (is_array($nested)) {
            foreach ($nested as $child) {
                if (!is_array($child)) {
                    continue;
                }
                if (empty($child['parent_sub_collection'])) {
                    $child['parent_sub_collection'] = $sub['slug'] ?? '';
                }
                $flat[] = $child;
            }
        }
    }
    return $flat;
}

/**
 * Load sub-entries using an explicit storage instance (page_bundle / public_read path).
 *
 * @return list<array<string,mixed>>
 */
function birkly_public_load_sub_entries_from_storage(
    $storage,
    string $parentCollection,
    string $parentEntryId,
    string $subCollection,
    ?string $parentSubCollection = null,
    ?string $parentSubEntryId = null
): array {
    $dir = birkly_public_resolve_collection_dir($storage, $parentCollection);
    if (!$dir) {
        return [];
    }

    if ($parentSubCollection !== null && $parentSubCollection !== ''
        && $parentSubEntryId !== null && $parentSubEntryId !== '') {
        $subPath = "collections/$dir/entries/$parentEntryId/sub_entries/$parentSubCollection/$parentSubEntryId/sub_entries/$subCollection";
    } else {
        $subPath = "collections/$dir/entries/$parentEntryId/sub_entries/$subCollection";
    }

    $files = $storage->list($subPath);
    $entries = [];
    foreach ($files as $file) {
        if (!is_string($file) || !str_ends_with($file, '.json') || str_starts_with($file, '.')) {
            continue;
        }
        $data = $storage->read("$subPath/$file");
        if (!is_array($data)) {
            continue;
        }
        if (($data['status'] ?? 'published') !== 'published') {
            continue;
        }
        $data['id'] = $data['id'] ?? basename($file, '.json');
        $entries[] = $data;
    }

    return $entries;
}

/**
 * Compute schema-driven aggregate counts from sub-collections (aggregate_on_parent.count_as).
 *
 * @param array<string, mixed> $entry
 * @return array<string, mixed>
 */
function birkly_compute_parent_entry_aggregates($storage, string $collectionSlug, array $entry): array
{
    if (empty($entry['id'])) {
        return $entry;
    }

    require_once __DIR__ . '/sub_entries.php';

    $dir = birkly_public_resolve_collection_dir($storage, $collectionSlug);
    if (!$dir) {
        return $entry;
    }

    $collectionMeta = $storage->read("collections/$dir/collection.json");
    $subCollections = is_array($collectionMeta['sub_collections'] ?? null)
        ? $collectionMeta['sub_collections']
        : [];

    foreach (birkly_public_flatten_sub_collection_configs($subCollections) as $subConfig) {
        $countAs = $subConfig['aggregate_on_parent']['count_as'] ?? null;
        if (!is_string($countAs) || $countAs === '') {
            continue;
        }

        $subSlug = (string) ($subConfig['slug'] ?? '');
        if ($subSlug === '') {
            continue;
        }

        $parentSubCollection = (string) ($subConfig['parent_sub_collection'] ?? '');
        if ($parentSubCollection !== '') {
            continue;
        }

        $subs = birkly_public_load_sub_entries_from_storage(
            $storage,
            $collectionSlug,
            (string) $entry['id'],
            $subSlug
        );
        $entry[$countAs] = count($subs);
    }

    return $entry;
}

/**
 * Apply optional ?scope=mine filter using schema ownership field.
 *
 * @param list<array<string, mixed>> $entries
 * @return list<array<string, mixed>>
 */
function birkly_public_apply_scope_mine_filter($storage, string $collectionSlug, array $entries, ?string $scope = null): array
{
    $scope = $scope ?? (string) ($_GET['scope'] ?? $_POST['scope'] ?? '');
    if ($scope !== 'mine') {
        return $entries;
    }

    require_once __DIR__ . '/entry_ownership.php';

    $dir = birkly_public_resolve_collection_dir($storage, $collectionSlug);
    if (!$dir) {
        return [];
    }

    $schema = $storage->read("collections/$dir/schema.json");
    $ownerField = birkly_get_owner_field_config(is_array($schema) ? $schema : []);
    if ($ownerField === null) {
        return $entries;
    }

    $fieldName = (string) ($ownerField['name'] ?? 'owner');
    $user = birkly_public_resolve_user_current();
    $userId = is_array($user) ? trim((string) ($user['id'] ?? '')) : '';
    if ($userId === '') {
        return [];
    }

    return array_values(array_filter($entries, static function ($entry) use ($fieldName, $userId) {
        if (!is_array($entry)) {
            return false;
        }
        $ownerId = birkly_get_entry_owner_id($entry, ['fields' => [[
            'name' => $fieldName,
            'type' => 'user',
            'ownership' => true,
        ]]]);
        return $ownerId !== '' && $ownerId === $userId;
    }));
}

function birkly_public_list_entries($storage, $collectionSlug, $language = 'en', array $options = []) {
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

    $publishedEntries = array_map(
        static function ($entry) use ($storage, $collectionSlugResolved) {
            return birkly_compute_parent_entry_aggregates($storage, $collectionSlugResolved, $entry);
        },
        $publishedEntries
    );

    $scope = $options['scope'] ?? null;
    $publishedEntries = birkly_public_apply_scope_mine_filter($storage, $collectionSlugResolved, $publishedEntries, $scope);

    birkly_public_bootstrap_plugins();
    $publishedEntries = birkly_public_filter_entries($publishedEntries, $collectionSlugResolved, 'list');

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
    $collectionSlugResolved = $collectionMeta['slug'] ?? $collectionSlug;
    $data = birkly_compute_parent_entry_aggregates($storage, $collectionSlugResolved, $data);

    birkly_public_bootstrap_plugins();
    $filteredEntry = birkly_public_filter_entry($data, $collectionSlugResolved, 'get');
    if ($filteredEntry === null) {
        return ['status' => 'error', 'message' => 'Entry not found or not published'];
    }

    return ['status' => 'ok', 'data' => ['entry' => $filteredEntry]];
}

function birkly_public_list_sub_entries(
    $parentCollection,
    $parentEntryId,
    $subCollection,
    ?string $parentSubCollection = null,
    ?string $parentSubEntryId = null,
    $storage = null
) {
    if ($storage === null) {
        require_once __DIR__ . '/sub_entries.php';
        if ($parentSubCollection !== null && $parentSubCollection !== ''
            && $parentSubEntryId !== null && $parentSubEntryId !== '') {
            $entries = getNestedSubEntries(
                $parentCollection,
                $parentEntryId,
                $parentSubCollection,
                $parentSubEntryId,
                $subCollection
            );
        } else {
            $entries = getSubEntries($parentCollection, $parentEntryId, $subCollection);
        }
    } else {
        $entries = birkly_public_load_sub_entries_from_storage(
            $storage,
            $parentCollection,
            $parentEntryId,
            $subCollection,
            $parentSubCollection,
            $parentSubEntryId
        );
    }

    $entries = array_values($entries);

    birkly_public_bootstrap_plugins();
    if (!function_exists('apply_filters')) {
        require_once __DIR__ . '/plugins.php';
    }
    $filtered = apply_filters(
        'birkly_public_sub_entries_filter',
        $entries,
        $parentCollection,
        $parentEntryId,
        $subCollection,
        $parentSubCollection,
        $parentSubEntryId
    );
    if (!is_array($filtered)) {
        $filtered = [];
    }
    $entries = array_values(array_filter($filtered, static function ($entry) {
        return is_array($entry);
    }));

    return ['status' => 'ok', 'data' => ['entries' => $entries]];
}

/**
 * Apply public-read filters to a list of entries (plugins may remove items).
 *
 * @param list<array<string, mixed>> $entries
 * @param string $collectionSlug
 * @param string $context
 * @return list<array<string, mixed>>
 */
function birkly_public_filter_entries(array $entries, string $collectionSlug, string $context = 'list'): array
{
    birkly_public_bootstrap_plugins();
    if (!function_exists('apply_filters')) {
        require_once __DIR__ . '/plugins.php';
    }

    $filtered = apply_filters('birkly_public_entries_filter', $entries, $collectionSlug, $context);
    if (!is_array($filtered)) {
        return [];
    }

    return array_values(array_filter($filtered, static function ($entry) {
        return is_array($entry);
    }));
}

/**
 * Apply public-read filter to a single entry. Returns null when filtered out.
 *
 * @param array<string, mixed>|null $entry
 * @param string $collectionSlug
 * @param string $context
 * @return array<string, mixed>|null
 */
function birkly_public_filter_entry($entry, string $collectionSlug, string $context = 'get'): ?array
{
    if (!is_array($entry)) {
        return null;
    }

    birkly_public_bootstrap_plugins();
    if (!function_exists('apply_filters')) {
        require_once __DIR__ . '/plugins.php';
    }

    $filtered = apply_filters('birkly_public_entry_filter', $entry, $collectionSlug, $context);
    if ($filtered === false || $filtered === null || !is_array($filtered)) {
        return null;
    }

    return $filtered;
}

/**
 * A sub-entry with no status is treated as published. Explicit drafts are not.
 *
 * @param array<string, mixed> $entry
 */
function birkly_sub_entry_status_is_public(array $entry): bool
{
    return (string) ($entry['status'] ?? 'published') === 'published';
}

/**
 * @param list<mixed> $entries
 * @return list<array<string, mixed>>
 */
function birkly_filter_public_sub_entries(array $entries): array
{
    $visible = [];
    foreach ($entries as $entry) {
        if (!is_array($entry) || !birkly_sub_entry_status_is_public($entry)) {
            continue;
        }
        $visible[] = $entry;
    }

    return $visible;
}

/**
 * @param list<mixed> $entries
 * @return list<array<string, mixed>>
 */
function birkly_sub_entries_for_reader(array $entries, bool $adminSession): array
{
    $rows = [];
    foreach ($entries as $entry) {
        if (is_array($entry)) {
            $rows[] = $entry;
        }
    }
    if ($adminSession) {
        return $rows;
    }

    return birkly_filter_public_sub_entries($rows);
}

/**
 * Anonymous sub-entry reads use the page-bundle collection gate and require a published parent.
 *
 * @return array{ok:bool,http:int,message:string}
 */
function birkly_sub_entries_anonymous_parent_visible($storage, string $parentCollection, string $parentEntryId): array
{
    require_once __DIR__ . '/page_bundle_runner.php';

    if (!birkly_page_bundle_collection_allowed($parentCollection, [], $storage)) {
        return ['ok' => false, 'http' => 403, 'message' => 'Public read access is disabled'];
    }

    $dir = birkly_public_resolve_collection_dir($storage, $parentCollection);
    if (!is_string($dir) || $dir === '') {
        $dir = $parentCollection;
    }
    $entry = $storage->read("collections/$dir/entries/$parentEntryId.json");
    if (!is_array($entry) || (string) ($entry['status'] ?? 'draft') !== 'published') {
        return ['ok' => false, 'http' => 404, 'message' => 'Not found'];
    }

    return ['ok' => true, 'http' => 200, 'message' => ''];
}
