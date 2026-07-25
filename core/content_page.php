<?php
/**
 * Content page data — core module (no admin_templates.php dependency).
 */

declare(strict_types=1);

/**
 * @return array<string, mixed>
 */
function birkly_content_page_data_minimal(): array
{
    $collectionsPath = birkly_project_root() . '/content/collections';
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

            $row = [
                'id' => $collectionData['id'] ?? ('col-' . $collectionName),
                'name' => $collectionData['name'] ?? $collectionData['title'] ?? $collectionName,
                'slug' => $collectionName,
                'description' => $collectionData['description'] ?? '',
                'entries_count' => $entriesCount,
                'sub_entries_count' => 0,
            ];
            if (!empty($collectionData['icon'])) {
                $row['icon'] = $collectionData['icon'];
            }
            if (!empty($collectionData['icon_key'])) {
                $row['icon_key'] = $collectionData['icon_key'];
            }
            $collections[] = $row;
        }
    }

    require_once __DIR__ . '/collection_groups.php';
    $groups = birkly_load_collection_groups($collectionsPath . '/groups.json');

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

/**
 * Safe loader for admin Content page — never throws.
 *
 * @return array<string, mixed>
 */
function birkly_load_content_page_data_safe(): array
{
    try {
        $helpersPath = birkly_project_root() . '/api/admin_content_helpers.php';
        if (is_file($helpersPath)) {
            require_once $helpersPath;
            if (function_exists('birkly_get_content_page_data')) {
                return birkly_get_content_page_data();
            }
        }
    } catch (Throwable $e) {
        error_log('birkly_load_content_page_data_safe (helpers): ' . $e->getMessage());
    }

    try {
        return birkly_content_page_data_minimal();
    } catch (Throwable $e) {
        return birkly_content_page_error_from_exception($e, 'minimal_loader');
    }
}
