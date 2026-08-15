<?php
/**
 * Storage-backed entry read/write helpers (flat-file and SQL drivers).
 */

require_once __DIR__ . '/storage/StorageFactory.php';
require_once __DIR__ . '/birkly_paths.php';

function birkly_entry_content_storage(): StorageInterface
{
    return get_storage(birkly_project_root() . '/content');
}

function birkly_entry_collection_storage_key(string $collectionSlug): string
{
    return 'collections/' . $collectionSlug;
}

function birkly_entry_record_storage_key(string $collectionSlug, string $entryId): string
{
    return birkly_entry_collection_storage_key($collectionSlug) . '/entries/' . $entryId . '.json';
}

function birkly_entry_version_storage_key(string $collectionSlug, string $entryId, int $version): string
{
    return birkly_entry_collection_storage_key($collectionSlug) . '/entries/' . $entryId . '/versions/v' . $version . '.json';
}

function birkly_entry_collection_exists(string $collectionSlug): bool
{
    return birkly_entry_content_storage()->exists(
        birkly_entry_collection_storage_key($collectionSlug) . '/collection.json'
    );
}

function birkly_entry_read_collection_meta(string $collectionSlug): ?array
{
    $data = birkly_entry_content_storage()->read(
        birkly_entry_collection_storage_key($collectionSlug) . '/collection.json'
    );

    return is_array($data) ? $data : null;
}

function birkly_entry_read_schema(string $collectionSlug): array
{
    $data = birkly_entry_content_storage()->read(
        birkly_entry_collection_storage_key($collectionSlug) . '/schema.json'
    );

    return is_array($data) ? $data : [];
}

function birkly_entry_read_record(string $collectionSlug, string $entryId): ?array
{
    $data = birkly_entry_content_storage()->read(
        birkly_entry_record_storage_key($collectionSlug, $entryId)
    );
    if (!is_array($data)) {
        return null;
    }

    $data['id'] = $data['id'] ?? $entryId;
    return $data;
}

function birkly_entry_record_exists(string $collectionSlug, string $entryId): bool
{
    return birkly_entry_content_storage()->exists(
        birkly_entry_record_storage_key($collectionSlug, $entryId)
    );
}

function birkly_entry_write_record(string $collectionSlug, string $entryId, array $entryData): bool
{
    $entryData['id'] = $entryId;
    $ok = birkly_entry_content_storage()->write(
        birkly_entry_record_storage_key($collectionSlug, $entryId),
        $entryData
    );
    if ($ok) {
        birkly_entry_invalidate_caches($collectionSlug);
    }

    return $ok;
}

function birkly_entry_delete_record(string $collectionSlug, string $entryId): bool
{
    $ok = birkly_entry_content_storage()->delete(
        birkly_entry_record_storage_key($collectionSlug, $entryId)
    );
    if ($ok) {
        birkly_entry_invalidate_caches($collectionSlug);
    }

    return $ok;
}

function birkly_entry_list_record_ids(string $collectionSlug): array
{
    $entriesPath = birkly_entry_collection_storage_key($collectionSlug) . '/entries';
    $items = birkly_entry_content_storage()->list($entriesPath);
    $ids = [];

    foreach ($items as $item) {
        if (!is_string($item) || $item === '') {
            continue;
        }
        if (str_ends_with($item, '.json')) {
            $ids[] = basename($item, '.json');
            continue;
        }
        if (strpos($item, '.') === false) {
            $ids[] = $item;
        }
    }

    return $ids;
}

function birkly_entry_list_records(string $collectionSlug): array
{
    $records = [];
    foreach (birkly_entry_list_record_ids($collectionSlug) as $entryId) {
        $record = birkly_entry_read_record($collectionSlug, $entryId);
        if ($record !== null) {
            $records[] = $record;
        }
    }

    return $records;
}

function birkly_entry_read_order(string $collectionSlug): array
{
    $order = birkly_entry_content_storage()->read(
        birkly_entry_collection_storage_key($collectionSlug) . '/order.json'
    );

    return is_array($order) ? $order : [];
}

function birkly_entry_write_version(string $collectionSlug, string $entryId, int $version, array $versionData): bool
{
    return birkly_entry_content_storage()->write(
        birkly_entry_version_storage_key($collectionSlug, $entryId, $version),
        $versionData
    );
}

function birkly_entry_list_version_numbers(string $collectionSlug, string $entryId): array
{
    $versionsPath = birkly_entry_collection_storage_key($collectionSlug) . '/entries/' . $entryId . '/versions';
    $items = birkly_entry_content_storage()->list($versionsPath);
    $versions = [];

    foreach ($items as $item) {
        if (!is_string($item) || !preg_match('/^v(\d+)\.json$/', $item, $matches)) {
            continue;
        }
        $versions[] = (int) $matches[1];
    }

    sort($versions);
    return $versions;
}

function birkly_entry_read_version(string $collectionSlug, string $entryId, int $version): ?array
{
    $data = birkly_entry_content_storage()->read(
        birkly_entry_version_storage_key($collectionSlug, $entryId, $version)
    );

    return is_array($data) ? $data : null;
}

function birkly_entry_invalidate_caches(string $collectionSlug): void
{
    require_once __DIR__ . '/cache.php';
    $cache = new Cache();
    $cache->invalidateCollection($collectionSlug);

    require_once __DIR__ . '/project_ssr.php';
    birkly_project_ssr_invalidate_all();
}

function birkly_entry_content_uses_flatfile(): bool
{
    $config = StorageFactory::loadStorageConfig(true);
    return ($config['driver'] ?? 'flatfile') === 'flatfile';
}
