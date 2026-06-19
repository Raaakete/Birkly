<?php
/**
 * Entry slug helpers — stable URLs on title-only edits (P2-R11).
 */

function birkly_generate_slug(string $title): string
{
    $slug = strtolower($title);
    $slug = preg_replace('/\s+/', '-', $slug);
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

/**
 * @param 'create'|'update'|string $action
 */
function birkly_resolve_entry_slug(string $action, array $entryData, ?array $existingEntry, string $title): string
{
    $slug = trim((string)($entryData['slug'] ?? ''));
    if ($slug !== '') {
        return $slug;
    }
    if ($action === 'update' && !empty($existingEntry['slug'])) {
        return (string)$existingEntry['slug'];
    }
    return birkly_generate_slug($title);
}
