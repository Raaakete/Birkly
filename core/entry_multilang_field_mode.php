<?php
/**
 * Per-field multilanguage mode (shared vs localize) — platform layer, not fieldtypes.
 */

declare(strict_types=1);

const BIRKLY_MULTILANG_MODE_LOCALIZE = 'localize';
const BIRKLY_MULTILANG_MODE_SHARED = 'shared';

/**
 * Default mode for a field type when multilanguage is enabled and the editor did not choose.
 */
function birkly_multilang_field_mode_default(string $fieldType): string
{
    require_once __DIR__ . '/fieldtype_catalog.php';
    $type = birkly_fieldtype_normalize_type_slug($fieldType);

    $sharedByDefault = [
        'file',
        'gallery',
        'map',
        'oembed',
        'relation',
        'checkbox',
        'toggle',
    ];

    if (in_array($type, $sharedByDefault, true)) {
        return BIRKLY_MULTILANG_MODE_SHARED;
    }

    return BIRKLY_MULTILANG_MODE_LOCALIZE;
}

/**
 * Effective mode for a schema field (always localize when collection multilanguage is off).
 *
 * @param array<string, mixed> $field
 */
function birkly_multilang_field_mode(array $field, bool $multilanguageEnabled): string
{
    if (!$multilanguageEnabled) {
        return BIRKLY_MULTILANG_MODE_LOCALIZE;
    }

    $stored = $field['multilanguage_mode'] ?? '';
    if ($stored === BIRKLY_MULTILANG_MODE_SHARED || $stored === BIRKLY_MULTILANG_MODE_LOCALIZE) {
        return $stored;
    }

    return birkly_multilang_field_mode_default((string) ($field['type'] ?? ''));
}

/**
 * @param array<string, mixed> $field
 */
function birkly_multilang_field_is_shared(array $field, bool $multilanguageEnabled): bool
{
    return birkly_multilang_field_mode($field, $multilanguageEnabled) === BIRKLY_MULTILANG_MODE_SHARED;
}

/**
 * Persist multilanguage_mode on a schema field row from collection editor POST.
 *
 * @param array<string, mixed> $field
 * @param array<string, mixed> $fieldData
 * @return array<string, mixed>
 */
function birkly_multilang_schema_field_apply_mode(array $field, array $fieldData, bool $multilanguageEnabled): array
{
    if (!$multilanguageEnabled) {
        unset($field['multilanguage_mode']);

        return $field;
    }

    $type = (string) ($field['type'] ?? '');
    if (!empty($fieldData['multilanguage_shared']) || ($fieldData['multilanguage_mode'] ?? '') === BIRKLY_MULTILANG_MODE_SHARED) {
        $field['multilanguage_mode'] = BIRKLY_MULTILANG_MODE_SHARED;
    } elseif (($fieldData['multilanguage_mode'] ?? '') === BIRKLY_MULTILANG_MODE_LOCALIZE) {
        $field['multilanguage_mode'] = BIRKLY_MULTILANG_MODE_LOCALIZE;
    } else {
        $field['multilanguage_mode'] = birkly_multilang_field_mode_default($type);
    }

    return $field;
}

/**
 * Read stored value for a shared field from an entry record.
 *
 * @param array<string, mixed> $entry
 * @param list<string> $languages
 * @return mixed
 */
function birkly_multilang_stored_shared_field_value(array $entry, string $fieldName, array $languages)
{
    if ($fieldName === '') {
        return null;
    }

    if (array_key_exists($fieldName, $entry) && !is_array($entry[$fieldName])) {
        return $entry[$fieldName];
    }

    foreach ($languages as $langCode) {
        if (!is_string($langCode) || $langCode === '') {
            continue;
        }
        if (isset($entry[$langCode]) && is_array($entry[$langCode]) && array_key_exists($fieldName, $entry[$langCode])) {
            return $entry[$langCode][$fieldName];
        }
    }

    return null;
}

/**
 * Raw POST / payload value for a shared field (top-level name wins, then any language tab).
 *
 * @param array<string, mixed> $entryData
 * @param list<string> $languages
 * @return mixed|null null when the request did not include this field
 */
function birkly_multilang_shared_field_raw_from_post(array $entryData, string $fieldName, array $languages)
{
    if ($fieldName === '') {
        return null;
    }

    if (array_key_exists($fieldName, $entryData)) {
        return $entryData[$fieldName];
    }

    foreach ($languages as $langCode) {
        if (!is_string($langCode) || $langCode === '') {
            continue;
        }
        if (!function_exists('birkly_entry_lang_field_raw')) {
            require_once __DIR__ . '/entry_multilang.php';
        }
        $raw = birkly_entry_lang_field_raw($entryData, $langCode, $fieldName);
        if ($raw !== null) {
            return $raw;
        }
    }

    return null;
}

/**
 * After language merge on public read, ensure shared fields use canonical storage when blocks diverge.
 *
 * @param array<string, mixed> $transformed
 * @param array<string, mixed> $entry
 * @param list<array<string, mixed>> $schemaFields
 * @param list<string> $languages
 * @return array<string, mixed>
 */
function birkly_multilang_public_apply_shared_fields(
    array $transformed,
    array $entry,
    array $schemaFields,
    array $languages,
    bool $multilanguageEnabled
): array {
    if (!$multilanguageEnabled || $schemaFields === []) {
        return $transformed;
    }

    foreach ($schemaFields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $fieldName = (string) ($field['name'] ?? '');
        if ($fieldName === '' || $fieldName === 'title') {
            continue;
        }
        if (!birkly_multilang_field_is_shared($field, true)) {
            continue;
        }
        if (!function_exists('birkly_fieldtype_persists_in_entry')) {
            require_once __DIR__ . '/fieldtype_virtual.php';
        }
        if (!birkly_fieldtype_persists_in_entry($field)) {
            continue;
        }

        $canonical = birkly_multilang_stored_shared_field_value($entry, $fieldName, $languages);
        if ($canonical !== null) {
            $transformed[$fieldName] = $canonical;
        }
    }

    return $transformed;
}
