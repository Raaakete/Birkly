<?php
/**
 * Reset site content and onboarding state while keeping CMS users and settings.
 */

declare(strict_types=1);

require_once __DIR__ . '/demo_starter_templates.php';

/**
 * Remove all collection directories under content/collections (schemas and entries).
 *
 * @return array{removed: list<string>, entries_removed: int}
 */
function birkly_remove_all_collections(string $collectionsPath): array
{
    $removed = [];
    $entriesRemoved = 0;

    if (!is_dir($collectionsPath)) {
        return ['removed' => [], 'entries_removed' => 0];
    }

    foreach (glob($collectionsPath . '/*', GLOB_ONLYDIR) ?: [] as $collectionDir) {
        $slug = basename($collectionDir);
        if ($slug === '' || str_starts_with($slug, '.')) {
            continue;
        }

        $entriesDir = $collectionDir . '/entries';
        if (is_dir($entriesDir)) {
            foreach (glob($entriesDir . '/*.json') ?: [] as $entryFile) {
                if (is_file($entryFile)) {
                    $entriesRemoved++;
                }
            }
        }

        if (birkly_demo_remove_tree($collectionDir)) {
            $removed[] = $slug;
        }
    }

    return ['removed' => $removed, 'entries_removed' => $entriesRemoved];
}

/**
 * Remove all entry JSON files (and version history) under content/collections.
 */
function birkly_remove_all_collection_entries(string $collectionsPath): int
{
    $entryCount = 0;
    if (!is_dir($collectionsPath)) {
        return 0;
    }

    $collectionDirs = glob($collectionsPath . '/*', GLOB_ONLYDIR) ?: [];
    foreach ($collectionDirs as $collectionDir) {
        $entriesDir = $collectionDir . '/entries';
        if (!is_dir($entriesDir)) {
            continue;
        }

        $versionDirs = glob($entriesDir . '/*/versions', GLOB_ONLYDIR) ?: [];
        foreach ($versionDirs as $versionDir) {
            foreach (glob($versionDir . '/*.json') ?: [] as $versionFile) {
                @unlink($versionFile);
            }
            @rmdir($versionDir);
            $entryDir = dirname($versionDir);
            if (is_dir($entryDir)) {
                @rmdir($entryDir);
            }
        }

        foreach (glob($entriesDir . '/*.json') ?: [] as $entryFile) {
            if (@unlink($entryFile)) {
                $entryCount++;
            }
        }
    }

    return $entryCount;
}

/**
 * Remove per-user onboarding progress files.
 */
function birkly_remove_onboarding_progress_files(string $usersPath): int
{
    $removed = 0;
    if (!is_dir($usersPath)) {
        return 0;
    }

    foreach (glob($usersPath . '/*_onboarding.json') ?: [] as $onboardingFile) {
        if (@unlink($onboardingFile)) {
            $removed++;
        }
    }

    return $removed;
}

/**
 * Clear starter collections, public site, entries, and onboarding state.
 * User accounts and most settings are preserved.
 */
function birkly_rebuild_system(string $projectRoot): array
{
    $projectRoot = rtrim($projectRoot, '/\\');
    $collectionsPath = $projectRoot . '/content/collections';
    $usersPath = $projectRoot . '/users';
    $settingsPath = $projectRoot . '/settings/onboarding.json';
    $cacheSnapshots = $projectRoot . '/cache/page-snapshots';

    $starterResult = birkly_demo_install_starter_template('blank', $projectRoot);
    if (empty($starterResult['success'])) {
        return [
            'success' => false,
            'message' => $starterResult['message'] ?? 'Failed to clear starter content',
        ];
    }

    $collectionReset = birkly_remove_all_collections($collectionsPath);
    $entriesRemoved = (int) ($collectionReset['entries_removed'] ?? 0);
    $collectionsRemoved = $collectionReset['removed'] ?? [];
    $onboardingRemoved = birkly_remove_onboarding_progress_files($usersPath);

    if (is_file($settingsPath)) {
        @unlink($settingsPath);
    }

    if (is_dir($cacheSnapshots)) {
        birkly_demo_remove_tree($cacheSnapshots);
    }

    return [
        'success' => true,
        'message' => 'System rebuilt successfully',
        'entries_removed' => $entriesRemoved,
        'collections_removed' => $collectionsRemoved,
        'onboarding_files_removed' => $onboardingRemoved,
        'starter_collections_removed' => $starterResult['removed'] ?? [],
    ];
}
