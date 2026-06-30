<?php
/**
 * Admin content page helpers (P5.4-M0 — file-level, no nested functions in get*Data).
 */

require_once __DIR__ . '/../core/sub_entries.php';
require_once __DIR__ . '/../core/storage/StorageFactory.php';

/**
 * Recursively count all sub-entries for a collection (matches entry-detail logic).
 */
function countAllSubEntriesForCollection(string $collectionSlug, array $collectionData): int
{
    $subCollections = $collectionData['sub_collections'] ?? [];
    if (empty($subCollections)) {
        return 0;
    }

    $entriesDir = __DIR__ . "/../content/collections/{$collectionSlug}/entries";
    if (!is_dir($entriesDir)) {
        return 0;
    }

    $entryFiles = glob($entriesDir . '/*.json');
    if (!$entryFiles) {
        return 0;
    }

    $storage = get_storage();
    $totalSubEntries = 0;

    foreach ($entryFiles as $entryFile) {
        $entryId = basename($entryFile, '.json');
        foreach ($subCollections as $subCollection) {
            $totalSubEntries += countAllSubEntriesRecursive($collectionSlug, $entryId, $subCollection, $storage);
        }
    }

    return $totalSubEntries;
}

/**
 * @param array<string, mixed> $subCollection
 */
function countAllSubEntriesRecursive(
    string $collectionSlug,
    string $entryId,
    array $subCollection,
    $storage,
    ?string $parentSubCollectionSlug = null,
    ?string $parentSubEntryId = null
): int {
    $subCollectionSlug = $subCollection['slug'] ?? '';
    if ($subCollectionSlug === '') {
        return 0;
    }

    $count = 0;
    try {
        if ($parentSubCollectionSlug && $parentSubEntryId) {
            $nestedSubEntryPath = "collections/{$collectionSlug}/entries/{$entryId}/sub_entries/{$parentSubCollectionSlug}/{$parentSubEntryId}/sub_entries/{$subCollectionSlug}";
            try {
                $files = $storage->list($nestedSubEntryPath);
                $count = count(array_filter($files, static function ($file) {
                    return pathinfo($file, PATHINFO_EXTENSION) === 'json';
                }));
            } catch (Exception $e) {
                $count = 0;
            }
        } else {
            $count = countSubEntries($collectionSlug, $entryId, $subCollectionSlug);
        }

        $nestedSubCollections = $subCollection['sub_collections'] ?? [];
        if (!empty($nestedSubCollections)) {
            $subEntryPath = $parentSubCollectionSlug && $parentSubEntryId
                ? "collections/{$collectionSlug}/entries/{$entryId}/sub_entries/{$parentSubCollectionSlug}/{$parentSubEntryId}/sub_entries/{$subCollectionSlug}"
                : "collections/{$collectionSlug}/entries/{$entryId}/sub_entries/{$subCollectionSlug}";

            try {
                $files = $storage->list($subEntryPath);
                $subEntries = [];
                foreach ($files as $file) {
                    if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                        $subEntries[] = basename($file, '.json');
                    }
                }

                foreach ($subEntries as $subEntryId) {
                    foreach ($nestedSubCollections as $nestedSubCollection) {
                        $count += countAllSubEntriesRecursive(
                            $collectionSlug,
                            $entryId,
                            $nestedSubCollection,
                            $storage,
                            $subCollectionSlug,
                            $subEntryId
                        );
                    }
                }
            } catch (Exception $e) {
                // Path doesn't exist
            }
        }
    } catch (Exception $e) {
        error_log('Error counting sub-entries: ' . $e->getMessage());
    }

    return $count;
}
