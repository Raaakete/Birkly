<?php
/**
 * Storage-backed entry read/write helpers (flat-file and SQL drivers).
 */

require_once __DIR__ . '/storage/StorageFactory.php';
require_once __DIR__ . '/birkly_paths.php';

function birkly_entry_content_storage(): StorageInterface
{
    require_once __DIR__ . '/storage/StorageFactory.php';
    $config = StorageFactory::loadStorageConfig(true);
    $driver = StorageFactory::normalizeDriver((string) ($config['driver'] ?? 'flatfile'));

    if (in_array($driver, ['mysql', 'postgresql'], true)) {
        return StorageFactory::createDriver($driver, $config, null);
    }

    return StorageFactory::createStorage(birkly_project_root() . '/content');
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
    $traceId = substr($collectionSlug, 0, 24) . '/' . substr($entryId, 0, 16);
    $started = microtime(true);

    error_log('birkly_entry_write_record[' . $traceId . ']: opening content storage');
    try {
        $storage = birkly_entry_content_storage();
    } catch (Throwable $e) {
        error_log('birkly_entry_write_record[' . $traceId . ']: storage open failed after '
            . (int) round((microtime(true) - $started) * 1000) . 'ms — ' . $e->getMessage());
        throw $e;
    }

    $driver = method_exists($storage, 'getDriverName') ? $storage->getDriverName() : 'unknown';
    error_log('birkly_entry_write_record[' . $traceId . ']: storage ready driver=' . $driver
        . ' dt=' . (int) round((microtime(true) - $started) * 1000) . 'ms');

    $writeStarted = microtime(true);
    $ok = $storage->write(
        birkly_entry_record_storage_key($collectionSlug, $entryId),
        $entryData
    );
    error_log('birkly_entry_write_record[' . $traceId . ']: write dt='
        . (int) round((microtime(true) - $writeStarted) * 1000) . 'ms ok=' . ($ok ? '1' : '0'));

    if ($ok) {
        $invalidateStarted = microtime(true);
        birkly_entry_invalidate_caches($collectionSlug);
        error_log('birkly_entry_write_record[' . $traceId . ']: invalidate dt='
            . (int) round((microtime(true) - $invalidateStarted) * 1000) . 'ms');
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
        if (!is_string($item) || $item === '' || !str_ends_with($item, '.json')) {
            continue;
        }
        $ids[] = basename($item, '.json');
    }

    return array_values(array_unique($ids));
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
