<?php
/**
 * Entry slug helpers — stable URLs on title-only edits (P2-R11).
 */

/**
 * Reserved entry field names — sidebar only (P29-D5).
 *
 * @return list<string>
 */
/**
 * Schema fields shown as dedicated smart-table columns (P39-M1).
 *
 * @return list<string>
 */
function birkly_entries_list_reserved_column_names(): array
{
    return ['title', 'status', 'slug'];
}

/**
 * Filter schema fields for entries list / smart-table columns.
 *
 * @param array<int, array<string, mixed>> $fields
 * @return array<int, array<string, mixed>>
 */
function birkly_entries_list_visible_fields(array $fields): array
{
    $reserved = array_flip(birkly_entries_list_reserved_column_names());

    return array_values(array_filter($fields, static function (array $field) use ($reserved): bool {
        $name = strtolower((string) ($field['name'] ?? ''));
        return $name !== '' && !isset($reserved[$name]);
    }));
}

function birkly_entry_reserved_field_names(): array
{
    return [
        'slug',
        'status',
        'published_at',
        'expires_at',
        'reviewer',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'og_title',
        'og_description',
        'og_image',
        'canonical_url',
        'robots_noindex',
        'ai_summary',
        'structured_data_json',
    ];
}

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
 * @param array<string, mixed> $data
 */
function birkly_is_auto_slug_enabled(array $data): bool
{
    if (!array_key_exists('auto_slug', $data)) {
        return false;
    }

    $value = $data['auto_slug'];

    return $value === '1' || $value === 1 || $value === true || $value === 'on';
}

/**
 * @param array<string, mixed> $data
 */
function birkly_slug_from_title_or_id(string $title, ?string $id = null): string
{
    $title = trim($title);
    if ($title !== '') {
        $generated = birkly_generate_slug($title);
        if ($generated !== '') {
            return $generated;
        }
    }

    if ($id !== null && $id !== '') {
        return birkly_normalize_slug($id);
    }

    return '';
}

/**
 * Effective URL slug for an entry (stored slug, or generated from title).
 *
 * @param array<string, mixed> $entry
 */
function birkly_entry_effective_slug(array $entry): string
{
    $stored = trim((string) ($entry['slug'] ?? ''));
    if ($stored !== '') {
        return birkly_normalize_slug($stored);
    }

    $title = (string) ($entry['title'] ?? '');
    $id = (string) ($entry['id'] ?? '');

    return birkly_slug_from_title_or_id($title, $id !== '' ? $id : null);
}

/**
 * @param array<string, mixed> $entry
 */
function birkly_entry_matches_slug(array $entry, string $slug): bool
{
    $slug = birkly_normalize_slug($slug);
    if ($slug === '') {
        return false;
    }

    $stored = trim((string) ($entry['slug'] ?? ''));
    if ($stored !== '' && birkly_normalize_slug($stored) === $slug) {
        return true;
    }

    return birkly_entry_effective_slug($entry) === $slug;
}

/**
 * Ensure public API / template consumers always have a usable slug field.
 *
 * @param array<string, mixed> $entry
 * @return array<string, mixed>
 */
function birkly_public_entry_with_effective_slug(array $entry): array
{
    $effective = birkly_entry_effective_slug($entry);
    if ($effective !== '' && trim((string) ($entry['slug'] ?? '')) === '') {
        $entry['slug'] = $effective;
    }

    return $entry;
}

/**
 * @param 'create'|'update'|string $action
 * @param array<string, mixed> $entryData
 * @param array<string, mixed>|null $existingEntry
 */
function birkly_resolve_entry_slug(
    string $action,
    array $entryData,
    ?array $existingEntry,
    string $title,
    ?string $entryId = null
): string {
    $entryId = $entryId ?? ($existingEntry['id'] ?? null);

    if (birkly_is_auto_slug_enabled($entryData)) {
        return birkly_slug_from_title_or_id($title, $entryId);
    }

    $slug = trim((string) ($entryData['slug'] ?? ''));
    if ($slug !== '') {
        return birkly_normalize_slug($slug);
    }

    if ($action === 'update' && !empty($existingEntry['slug'])) {
        return birkly_normalize_slug((string) $existingEntry['slug']);
    }

    $fromTitleOrId = birkly_slug_from_title_or_id($title, $entryId);
    if ($fromTitleOrId !== '') {
        return $fromTitleOrId;
    }

    return birkly_generate_slug($title);
}

/**
 * @param array<string, mixed> $postData
 * @param array<string, mixed>|null $existingCollection
 */
/**
 * Whether the auto-generate slug checkbox should be checked in admin UI.
 *
 * @param string $slugValue Current slug field value
 * @param string $titleValue Title or collection name
 * @param string|null $idValue Entry/collection ID fallback
 * @param bool $isNew True when creating a new record
 */
function birkly_auto_slug_checked(
    string $slugValue,
    string $titleValue,
    ?string $idValue = null,
    bool $isNew = false
): bool {
    $expected = birkly_slug_from_title_or_id($titleValue, $idValue);
    if ($isNew || $slugValue === '') {
        return true;
    }

    return $slugValue === $expected;
}

/**
 * Resolve create vs update for entry saves.
 *
 * The entry editor always posts to ?action=save. Create vs update must be
 * determined by whether an entry_id is present — not by the URL action.
 * Without this, new entries enqueue entry_updated instead of entry_created.
 */
function birkly_resolve_entry_save_action(?string $entryId): string
{
    if (trim((string) $entryId) !== '') {
        return 'update';
    }
    return 'create';
}

function birkly_resolve_collection_slug(
    array $postData,
    ?array $existingCollection,
    string $name,
    ?string $collectionId = null
): string {
    $collectionId = $collectionId
        ?? ($existingCollection['id'] ?? null)
        ?? ($existingCollection['slug'] ?? null);

    if (birkly_is_auto_slug_enabled($postData)) {
        return birkly_slug_from_title_or_id($name, $collectionId);
    }

    $slug = trim((string) ($postData['slug'] ?? ''));
    if ($slug !== '') {
        return birkly_normalize_slug($slug);
    }

    if ($existingCollection && !empty($existingCollection['slug'])) {
        return birkly_normalize_slug((string) $existingCollection['slug']);
    }

    $fromNameOrId = birkly_slug_from_title_or_id($name, $collectionId);
    if ($fromNameOrId !== '') {
        return $fromNameOrId;
    }

    return '';
}

/**
 * Validate a collection or sub-collection slug before filesystem paths are built.
 */
function birkly_validate_content_slug(string $slug): bool
{
    if ($slug === '') {
        return false;
    }

    if (strpos($slug, '..') !== false
        || strpos($slug, '/') !== false
        || strpos($slug, '\\') !== false) {
        return false;
    }

    return preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]*$/', $slug) === 1;
}
