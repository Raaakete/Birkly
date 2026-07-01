<?php
/**
 * Admin content page helpers (P5.4 — no admin_templates.php dependency).
 */

/**
 * Minimal content page data — no sub_entries.php, no storage factory.
 * Used as fallback when full loader fails on live installs.
 *
 * @return array<string, mixed>
 */
function birkly_get_content_page_data_minimal(): array
{
    $collectionsPath = __DIR__ . '/../content/collections';
    $collections = [];

    if (is_dir($collectionsPath)) {
        foreach (glob($collectionsPath . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $collectionName = basename($dir);
            $collectionFile = $dir . '/collection.json';
            if (!is_file($collectionFile)) {
                continue;
            }

            $collectionData = json_decode((string) file_get_contents($collectionFile), true);
            if (!is_array($collectionData)) {
                $collectionData = [];
            }

            $entriesDir = $dir . '/entries';
            $entriesCount = is_dir($entriesDir) ? count(glob($entriesDir . '/*.json') ?: []) : 0;

            $collectionId = $collectionData['id'] ?? ('col-' . $collectionName);

            $collections[] = [
                'id' => $collectionId,
                'name' => $collectionData['name'] ?? $collectionData['title'] ?? $collectionName,
                'slug' => $collectionName,
                'description' => $collectionData['description'] ?? '',
                'entries_count' => $entriesCount,
                'sub_entries_count' => 0,
            ];
        }
    }

    $groups = [];
    $groupsFile = $collectionsPath . '/groups.json';
    if (is_file($groupsFile)) {
        $groupsData = json_decode((string) file_get_contents($groupsFile), true);
        if (is_array($groupsData)) {
            $groups = $groupsData;
        }
    }

    return [
        'collections' => $collections,
        'groups' => $groups,
        'content_bootstrap' => [
            'collections' => $collections,
            'groups' => $groups,
        ],
        'content_loader' => 'minimal',
    ];
}

/**
 * Count sub-entries when sub_collections exist (lazy-loads sub_entries.php).
 */
function birkly_count_sub_entries_for_collection(string $collectionSlug, array $collectionData): int
{
    $subCollections = $collectionData['sub_collections'] ?? [];
    if ($subCollections === []) {
        return 0;
    }

    static $helpersLoaded = false;
    if (!$helpersLoaded) {
        require_once __DIR__ . '/../core/sub_entries.php';
        require_once __DIR__ . '/../core/storage/StorageFactory.php';
        $helpersLoaded = true;
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
            if (!is_array($subCollection)) {
                continue;
            }
            $totalSubEntries += birkly_count_sub_entries_recursive(
                $collectionSlug,
                $entryId,
                $subCollection,
                $storage
            );
        }
    }

    return $totalSubEntries;
}

/**
 * @param array<string, mixed> $subCollection
 */
function birkly_count_sub_entries_recursive(
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
        if ($nestedSubCollections !== []) {
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
                        if (!is_array($nestedSubCollection)) {
                            continue;
                        }
                        $count += birkly_count_sub_entries_recursive(
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
    } catch (Throwable $e) {
        error_log('birkly_count_sub_entries_recursive: ' . $e->getMessage());
    }

    return $count;
}

/**
 * Full content page data with sub-entry counts when possible.
 *
 * @return array<string, mixed>
 */
function birkly_get_content_page_data(): array
{
    $data = birkly_get_content_page_data_minimal();
    $collectionsPath = __DIR__ . '/../content/collections';

    foreach ($data['collections'] as $i => $collection) {
        $slug = $collection['slug'] ?? '';
        if ($slug === '') {
            continue;
        }

        $collectionFile = $collectionsPath . '/' . $slug . '/collection.json';
        if (!is_file($collectionFile)) {
            continue;
        }

        $collectionData = json_decode((string) file_get_contents($collectionFile), true);
        if (!is_array($collectionData) || empty($collectionData['sub_collections'])) {
            continue;
        }

        try {
            $data['collections'][$i]['sub_entries_count'] = birkly_count_sub_entries_for_collection($slug, $collectionData);
        } catch (Throwable $e) {
            error_log('Sub-entry count skipped for ' . $slug . ': ' . $e->getMessage());
            $data['collections'][$i]['sub_entries_count'] = 0;
        }
    }

    $data['content_bootstrap']['collections'] = $data['collections'];
    $data['content_loader'] = 'full';

    return $data;
}

/**
 * Safe loader for admin router — never throws; returns error details for diagnostics.
 *
 * @return array<string, mixed>
 */
function birkly_load_content_page_data_safe(): array
{
    $helpersPath = __DIR__ . '/admin_content_helpers.php';
    if (!is_file($helpersPath)) {
        return [
            'error' => 'Content helpers missing (api/admin_content_helpers.php).',
            'error_step' => 'helpers_missing',
        ];
    }

    try {
        require_once $helpersPath;
    } catch (Throwable $e) {
        return birkly_content_page_error_from_exception($e, 'require_helpers');
    }

    try {
        return birkly_get_content_page_data();
    } catch (Throwable $e) {
        error_log('birkly_get_content_page_data failed, using minimal loader: ' . $e->getMessage());
    }

    try {
        $minimal = birkly_get_content_page_data_minimal();
        $minimal['content_loader_fallback'] = true;
        return $minimal;
    } catch (Throwable $e) {
        return birkly_content_page_error_from_exception($e, 'minimal_loader');
    }
}

/**
 * @return array<string, mixed>
 */
function birkly_content_page_error_from_exception(Throwable $e, string $step): array
{
    return [
        'error' => $e->getMessage(),
        'error_step' => $step,
        'error_class' => get_class($e),
        'error_file' => $e->getFile(),
        'error_line' => $e->getLine(),
    ];
}
