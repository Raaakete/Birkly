<?php
/**
 * Normalize admin / JSON sub-entry saves (multilanguage + fieldtypes).
 */

declare(strict_types=1);

/**
 * Remove flat bracket keys (e.g. en[cover]) left over from FormData JSON saves.
 *
 * @param array<string, mixed> $entry
 * @return array<string, mixed>
 */
function birkly_entry_strip_flat_lang_bracket_keys(array $entry): array
{
    foreach (array_keys($entry) as $key) {
        if (!is_string($key)) {
            continue;
        }
        if (preg_match('/^[a-z]{2}(?:-[a-z]{2,4})?\[/', $key)) {
            unset($entry[$key]);
        }
    }

    return $entry;
}

/**
 * @return list<array<string, mixed>>
 */
function birkly_sub_entry_schema_fields(string $parentCollection, string $subCollectionSlug): array
{
    if (!function_exists('getSubCollectionConfig')) {
        require_once __DIR__ . '/sub_entries.php';
    }

    $subConfig = getSubCollectionConfig($parentCollection, $subCollectionSlug);
    if (!is_array($subConfig)) {
        return [];
    }

    $fields = $subConfig['schema']['fields'] ?? $subConfig['fields'] ?? [];

    return is_array($fields) ? $fields : [];
}

/**
 * Apply submitted sub-entry editor payload onto an existing record.
 *
 * Uses parent collection multilanguage settings (same as entry editor UI).
 *
 * @param array<string, mixed> $entryData Raw POST / JSON body fields
 * @param array<string, mixed>|null $existingEntry
 * @return array<string, mixed>
 */
function birkly_sub_entry_prepare_save_data(
    string $parentCollection,
    string $subCollectionSlug,
    string $subEntryId,
    array $entryData,
    ?array $existingEntry
): array {
    require_once __DIR__ . '/entry_multilang.php';
    require_once __DIR__ . '/fieldtype_virtual.php';

    $existingEntry = is_array($existingEntry) ? $existingEntry : [];
    $fields = birkly_sub_entry_schema_fields($parentCollection, $subCollectionSlug);

    $entryData = birkly_entry_data_normalize_multilang_post_keys($entryData);

    $multilanguageLanguages = birkly_entry_collection_multilanguage_languages($parentCollection);
    $entryOutput = $existingEntry;

    if ($multilanguageLanguages !== []) {
        require_once __DIR__ . '/entry_multilang_field_mode.php';
        $fieldModes = birkly_multilang_entry_field_modes_from_post(
            $entryData,
            $fields,
            $existingEntry,
            true
        );
        if ($fieldModes !== []) {
            $entryOutput[BIRKLY_MULTILANG_ENTRY_MODES_KEY] = $fieldModes;
        }

        $entryOutput = birkly_entry_apply_multilang_fields(
            $entryOutput,
            $entryData,
            $fields,
            $multilanguageLanguages,
            $existingEntry,
            $subEntryId
        );
    } else {
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $fieldName = (string) ($field['name'] ?? '');
            if ($fieldName === '') {
                continue;
            }

            if (!array_key_exists($fieldName, $entryData)) {
                continue;
            }

            $fieldType = (string) ($field['type'] ?? '');
            $processed = birkly_entry_process_fieldtype_value(
                $fieldName,
                $fieldType,
                $entryData[$fieldName],
                $field
            );
            if ($processed !== null) {
                $entryOutput[$fieldName] = $processed;
            }
        }

        if (array_key_exists('title', $entryData)) {
            $entryOutput['title'] = trim((string) $entryData['title']);
        }

        if (trim((string) ($entryOutput['title'] ?? '')) === '') {
            $entryOutput['title'] = birkly_entry_resolve_top_level_title(
                $entryOutput,
                $entryData,
                [],
                $subEntryId,
                $existingEntry
            );
        }
    }

    $systemKeys = [
        'status',
        'slug',
        'published_at',
        'expires_at',
        'reviewer',
        'review_notes',
        'order',
        'section_key',
        'auto_slug',
    ];
    foreach ($systemKeys as $systemKey) {
        if (array_key_exists($systemKey, $entryData)) {
            $entryOutput[$systemKey] = $entryData[$systemKey];
        }
    }

    if (trim((string) ($entryOutput['title'] ?? '')) === '') {
        $entryOutput['title'] = $subEntryId;
    }

    return birkly_entry_strip_flat_lang_bracket_keys($entryOutput);
}
