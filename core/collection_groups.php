<?php
/**
 * Collection groups persistence (content/collections/groups.json).
 */

function birkly_collection_groups_path(): string
{
    return dirname(__DIR__) . '/content/collections/groups.json';
}

function birkly_load_collection_groups(?string $path = null): array
{
    $path = $path ?? birkly_collection_groups_path();
    if (!is_file($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function birkly_save_collection_groups(array $groups, ?string $path = null): bool
{
    $path = $path ?? birkly_collection_groups_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    return file_put_contents($path, json_encode($groups, JSON_PRETTY_PRINT)) !== false;
}
