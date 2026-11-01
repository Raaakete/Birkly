<?php
/**
 * Per-field multilanguage mode (shared vs localize) — platform layer, not fieldtypes.
 *
 * Mode is stored per entry in `multilang_field_modes` (author choice at edit time).
 * Type defaults apply when a field has no stored mode yet.
 */

declare(strict_types=1);

const BIRKLY_MULTILANG_MODE_LOCALIZE = 'localize';
const BIRKLY_MULTILANG_MODE_SHARED = 'shared';

const BIRKLY_MULTILANG_ENTRY_MODES_KEY = 'multilang_field_modes';

/**
 * Default mode for a field type when multilanguage is enabled and the entry has no choice yet.
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
 * @return array<string, string>
 */
function birkly_multilang_entry_field_modes(array $entry): array
{
    $modes = $entry[BIRKLY_MULTILANG_ENTRY_MODES_KEY] ?? [];
    if (!is_array($modes)) {
        return [];
    }

    $normalized = [];
    foreach ($modes as $fieldName => $mode) {
        if (!is_string($fieldName) || $fieldName === '') {
            continue;
        }
        if ($mode === BIRKLY_MULTILANG_MODE_SHARED || $mode === BIRKLY_MULTILANG_MODE_LOCALIZE) {
            $normalized[$fieldName] = $mode;
        }
    }

    return $normalized;
}

/**
 * Effective mode for one schema field on an entry (always localize when collection multilanguage is off).
 *
 * @param array<string, mixed> $field
 * @param array<string, mixed> $entry
 */
function birkly_multilang_field_mode_for_entry(array $field, bool $multilanguageEnabled, array $entry): string
{
    if (!$multilanguageEnabled) {
        return BIRKLY_MULTILANG_MODE_LOCALIZE;
    }

    $fieldName = (string) ($field['name'] ?? '');
    if ($fieldName !== '') {
        $entryModes = birkly_multilang_entry_field_modes($entry);
        if (isset($entryModes[$fieldName])) {
            return $entryModes[$fieldName];
        }
    }

    // Legacy: collection schema mode (read-only fallback for older collections).
    $schemaMode = $field['multilanguage_mode'] ?? '';
    if ($schemaMode === BIRKLY_MULTILANG_MODE_SHARED || $schemaMode === BIRKLY_MULTILANG_MODE_LOCALIZE) {
        return $schemaMode;
    }

    return birkly_multilang_field_mode_default((string) ($field['type'] ?? ''));
}

/**
 * @param array<string, mixed> $field
 * @param array<string, mixed> $entry
 */
function birkly_multilang_field_is_shared_for_entry(array $field, bool $multilanguageEnabled, array $entry): bool
{
    require_once __DIR__ . '/fieldtype_catalog.php';
    $type = birkly_fieldtype_normalize_type_slug((string) ($field['type'] ?? ''));
    if ($type === 'dynamicitems') {
        return false;
    }

    return birkly_multilang_field_mode_for_entry($field, $multilanguageEnabled, $entry) === BIRKLY_MULTILANG_MODE_SHARED;
}

function birkly_multilang_normalize_mode_value($raw): string
{
    return ($raw === BIRKLY_MULTILANG_MODE_SHARED || $raw === '1' || $raw === 1 || $raw === true)
        ? BIRKLY_MULTILANG_MODE_SHARED
        : BIRKLY_MULTILANG_MODE_LOCALIZE;
}

function birkly_multilang_block_mode_storage_key(string $fieldName, string $blockName): string
{
    return $fieldName . '.' . $blockName;
}

/**
 * @param array<string, mixed> $entry
 */
function birkly_multilang_block_is_shared_for_entry(
    array $entry,
    string $fieldName,
    string $blockName,
    string $blockType
): bool {
    $modes = birkly_multilang_entry_field_modes($entry);
    $key = birkly_multilang_block_mode_storage_key($fieldName, $blockName);
    if (isset($modes[$key])) {
        return $modes[$key] === BIRKLY_MULTILANG_MODE_SHARED;
    }

    return birkly_multilang_field_mode_default($blockType) === BIRKLY_MULTILANG_MODE_SHARED;
}

/**
 * @deprecated Use birkly_multilang_field_mode_for_entry()
 *
 * @param array<string, mixed> $field
 */
function birkly_multilang_field_mode(array $field, bool $multilanguageEnabled): string
{
    return birkly_multilang_field_mode_for_entry($field, $multilanguageEnabled, []);
}

/**
 * @deprecated Use birkly_multilang_field_is_shared_for_entry()
 *
 * @param array<string, mixed> $field
 */
function birkly_multilang_field_is_shared(array $field, bool $multilanguageEnabled): bool
{
    return birkly_multilang_field_mode($field, $multilanguageEnabled) === BIRKLY_MULTILANG_MODE_SHARED;
}

/**
 * Merge POST toggles with existing entry modes and type defaults for every schema field.
 *
 * @param array<string, mixed> $entryData
 * @param list<array<string, mixed>> $fields
 * @param array<string, mixed>|null $existingEntry
 * @return array<string, string>
 */
function birkly_multilang_entry_field_modes_from_post(
    array $entryData,
    array $fields,
    ?array $existingEntry,
    bool $multilanguageEnabled
): array {
    if (!$multilanguageEnabled) {
        return [];
    }

    $posted = $entryData[BIRKLY_MULTILANG_ENTRY_MODES_KEY] ?? [];
    if (!is_array($posted)) {
        $posted = [];
    }

    $existing = is_array($existingEntry) ? birkly_multilang_entry_field_modes($existingEntry) : [];
    $resolved = [];

    foreach ($posted as $key => $raw) {
        if (!is_string($key) || $key === '') {
            continue;
        }
        if (is_array($raw)) {
            foreach ($raw as $blockName => $blockRaw) {
                if (!is_string($blockName) || $blockName === '') {
                    continue;
                }
                $resolved[birkly_multilang_block_mode_storage_key($key, $blockName)] =
                    birkly_multilang_normalize_mode_value($blockRaw);
            }
            continue;
        }
        $resolved[$key] = birkly_multilang_normalize_mode_value($raw);
    }

    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $fieldName = (string) ($field['name'] ?? '');
        if ($fieldName === '' || $fieldName === 'title') {
            continue;
        }

        require_once __DIR__ . '/fieldtype_catalog.php';
        if (birkly_fieldtype_normalize_type_slug((string) ($field['type'] ?? '')) === 'dynamicitems') {
            continue;
        }

        if (isset($resolved[$fieldName])) {
            continue;
        }

        if (isset($existing[$fieldName])) {
            $resolved[$fieldName] = $existing[$fieldName];
            continue;
        }

        $resolved[$fieldName] = birkly_multilang_field_mode_for_entry($field, true, []);
    }

    foreach ($existing as $modeKey => $modeValue) {
        if (!is_string($modeKey) || strpos($modeKey, '.') === false) {
            continue;
        }
        if (!isset($resolved[$modeKey])) {
            $resolved[$modeKey] = $modeValue;
        }
    }

    return $resolved;
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
        if (!birkly_multilang_field_is_shared_for_entry($field, true, $entry)) {
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

/**
 * Fan out shared Dynamic Items block values across language tabs on save.
 *
 * @param array<string, mixed> $entryOutput
 * @param list<array<string, mixed>> $fields
 * @param list<string> $languages
 * @param array<string, string> $fieldModes
 * @return array<string, mixed>
 */
function birkly_multilang_entry_apply_shared_dynamic_block_values(
    array $entryOutput,
    array $fields,
    array $languages,
    array $fieldModes
): array {
    require_once __DIR__ . '/fieldtype_catalog.php';

    $entryContext = $entryOutput;
    $entryContext[BIRKLY_MULTILANG_ENTRY_MODES_KEY] = $fieldModes;

    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $fieldName = (string) ($field['name'] ?? '');
        if ($fieldName === '') {
            continue;
        }
        if (birkly_fieldtype_normalize_type_slug((string) ($field['type'] ?? '')) !== 'dynamicitems') {
            continue;
        }

        $referenceBlocks = null;
        foreach ($languages as $langCode) {
            if (!is_string($langCode) || $langCode === '') {
                continue;
            }
            $raw = $entryOutput[$langCode][$fieldName] ?? null;
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $raw = is_array($decoded) ? $decoded : null;
            }
            if (is_array($raw) && $raw !== []) {
                $referenceBlocks = $raw;
                break;
            }
        }

        if (!is_array($referenceBlocks)) {
            continue;
        }

        foreach ($referenceBlocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            $blockName = (string) ($block['name'] ?? '');
            if ($blockName === '') {
                continue;
            }
            $blockType = (string) ($block['type'] ?? 'text');
            if (!birkly_multilang_block_is_shared_for_entry($entryContext, $fieldName, $blockName, $blockType)) {
                continue;
            }

            $sharedValue = $block['value'] ?? null;
            foreach ($languages as $langCode) {
                if (!is_string($langCode) || $langCode === '') {
                    continue;
                }
                if (!isset($entryOutput[$langCode]) || !is_array($entryOutput[$langCode])) {
                    continue;
                }

                $langBlocks = $entryOutput[$langCode][$fieldName] ?? null;
                if (is_string($langBlocks)) {
                    $decoded = json_decode($langBlocks, true);
                    $langBlocks = is_array($decoded) ? $decoded : [];
                }
                if (!is_array($langBlocks)) {
                    continue;
                }

                $changed = false;
                foreach ($langBlocks as $idx => $langBlock) {
                    if (!is_array($langBlock)) {
                        continue;
                    }
                    if ((string) ($langBlock['name'] ?? '') !== $blockName) {
                        continue;
                    }
                    $langBlocks[$idx]['value'] = $sharedValue;
                    $changed = true;
                }

                if ($changed) {
                    $entryOutput[$langCode][$fieldName] = $langBlocks;
                }
            }
        }
    }

    return $entryOutput;
}
