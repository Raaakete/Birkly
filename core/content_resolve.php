<?php
/**
 * Shared collection resolution for AI/MCP read actions.
 */

declare(strict_types=1);

/**
 * Resolve a collection directory name from slug, display name, or id (case-insensitive).
 */
function birkly_content_resolve_collection_dir(Storage $storage, string $collectionRef): ?string
{
    $collectionRef = trim($collectionRef);
    if ($collectionRef === '') {
        return null;
    }

    $needle = strtolower($collectionRef);
    foreach ($storage->list('collections') as $dir) {
        $meta = $storage->read('collections/' . $dir . '/collection.json');
        if (!is_array($meta)) {
            if (strtolower((string) $dir) === $needle) {
                return $dir;
            }
            continue;
        }

        $candidates = [
            (string) ($meta['slug'] ?? ''),
            (string) ($meta['name'] ?? ''),
            (string) ($meta['title'] ?? ''),
            (string) ($meta['id'] ?? ''),
            (string) $dir,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && strtolower($candidate) === $needle) {
                return $dir;
            }
        }
    }

    return null;
}

/**
 * Resolve collection to on-disk directory name.
 * Preserves legacy slugs with underscores (e.g. site_elements) for AI/admin writes.
 */
function birkly_content_resolve_collection_dir_name(Storage $storage, string $collectionRef): ?string
{
    $collectionRef = trim($collectionRef);
    if ($collectionRef === '') {
        return null;
    }

    $dir = birkly_content_resolve_collection_dir($storage, $collectionRef);
    if ($dir !== null) {
        return $dir;
    }

    require_once __DIR__ . '/entry_slug.php';
    $normalized = birkly_normalize_slug($collectionRef);
    if ($normalized === '' || strcasecmp($normalized, $collectionRef) === 0) {
        return null;
    }

    return birkly_content_resolve_collection_dir($storage, $normalized);
}

/**
 * Lightweight entry row for MCP list_entries (avoids huge ContentEditor payloads).
 */
function birkly_content_entry_list_summary(array $entry): array
{
    $title = (string) ($entry['title'] ?? $entry['name'] ?? '');
    if ($title === '') {
        foreach (['headline', 'label', 'subject'] as $field) {
            if (!empty($entry[$field]) && is_string($entry[$field])) {
                $title = $entry[$field];
                break;
            }
        }
    }

    return array_filter([
        'id' => $entry['id'] ?? '',
        'slug' => $entry['slug'] ?? '',
        'title' => $title,
        'status' => $entry['status'] ?? '',
        'updated_at' => $entry['updated_at'] ?? '',
        'published_at' => $entry['published_at'] ?? '',
    ], static fn($value): bool => $value !== '' && $value !== null);
}
