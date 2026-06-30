<?php
/**
 * Entry slug helpers — stable URLs on title-only edits (P2-R11).
 */

/**
 * Normalize URL slugs: lowercase, hyphens only, no underscores (P5.2-M2).
 */
function birkly_normalize_slug(string $slug): string
{
    $slug = strtolower(trim($slug));
    $slug = str_replace('_', '-', $slug);
    $slug = preg_replace('/\s+/', '-', $slug);
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    $slug = preg_replace('/-+/', '-', $slug);

    return trim($slug, '-');
}

function birkly_generate_slug(string $title): string
{
    $slug = strtolower($title);
    $slug = str_replace('_', '-', $slug);
    $slug = preg_replace('/\s+/', '-', $slug);
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');

    return birkly_normalize_slug($slug);
}

/**
 * @param 'create'|'update'|string $action
 */
function birkly_resolve_entry_slug(string $action, array $entryData, ?array $existingEntry, string $title): string
{
    $slug = trim((string)($entryData['slug'] ?? ''));
    if ($slug !== '') {
        return birkly_normalize_slug($slug);
    }
    if ($action === 'update' && !empty($existingEntry['slug'])) {
        return birkly_normalize_slug((string) $existingEntry['slug']);
    }

    return birkly_generate_slug($title);
}
