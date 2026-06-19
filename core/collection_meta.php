<?php
/**
 * Normalize collection.json metadata for consistent API responses (P2-M6-T3).
 */

function birkly_normalize_collection_meta(array $meta, string $dir = ''): array
{
    $slug = trim((string)($meta['slug'] ?? $dir));
    if ($slug === '') {
        $slug = 'collection';
    }

    $title = trim((string)($meta['title'] ?? $meta['name'] ?? $slug));
    if ($title === '') {
        $title = $slug;
    }

    $id = trim((string)($meta['id'] ?? ''));
    if ($id === '') {
        $id = 'col-' . $slug;
    }

    return [
        'id' => $id,
        'title' => $title,
        'slug' => $slug,
    ];
}
