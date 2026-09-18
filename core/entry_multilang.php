<?php
/**
 * Multi-language entry helpers.
 */

declare(strict_types=1);

/**
 * Read a language-specific field from request data (nested or flat bracket keys).
 *
 * @param mixed $default
 * @return mixed
 */
function birkly_entry_lang_field_raw(array $entryData, string $langCode, string $fieldName, $default = null)
{
    if (isset($entryData[$langCode]) && is_array($entryData[$langCode])
        && array_key_exists($fieldName, $entryData[$langCode])) {
        return $entryData[$langCode][$fieldName];
    }

    $flatKey = $langCode . '[' . $fieldName . ']';
    if (array_key_exists($flatKey, $entryData)) {
        return $entryData[$flatKey];
    }

    return $default;
}

/**
 * Resolve the canonical top-level entry title for multilanguage entries.
 */
function birkly_entry_resolve_top_level_title(
    array $entryOutput,
    array $entryData,
    array $multilanguageLanguages,
    string $entryId,
    ?array $existingEntry = null
): string {
    if (!empty($multilanguageLanguages)) {
        foreach ($multilanguageLanguages as $langCode) {
            $candidate = trim((string) ($entryOutput[$langCode]['title'] ?? ''));
            if ($candidate !== '') {
                return $candidate;
            }
        }
    }

    $topLevel = trim((string) ($entryData['title'] ?? ''));
    if ($topLevel !== '' && $topLevel !== $entryId) {
        return $topLevel;
    }

    if (is_array($existingEntry)) {
        $existingTitle = trim((string) ($existingEntry['title'] ?? ''));
        $existingId = (string) ($existingEntry['id'] ?? '');
        if ($existingTitle !== '' && $existingTitle !== $existingId) {
            return $existingTitle;
        }
    }

    return $topLevel !== '' ? $topLevel : $entryId;
}

/**
 * Whether a top-level title value is a placeholder (entry id) rather than real content.
 */
function birkly_entry_title_is_placeholder(string $title, array $entry): bool
{
    $title = trim($title);
    if ($title === '') {
        return true;
    }

    $entryId = trim((string) ($entry['id'] ?? ''));
    return $entryId !== '' && $title === $entryId;
}

/**
 * Inject a language-specific value into rendered fieldtype HTML (after name/id rewrite).
 */
function birkly_entry_multilang_apply_field_value(string $html, string $fieldName, string $langCode, $langValue): string
{
    if ($html === '' || $fieldName === '' || $langCode === '') {
        return $html;
    }

    if ($langValue === null || $langValue === '') {
        return $html;
    }

    if (is_array($langValue)) {
        $encoded = htmlspecialchars(
            json_encode($langValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ENT_QUOTES,
            'UTF-8'
        );
    } else {
        $stringValue = (string) $langValue;
        if ($stringValue === '') {
            return $html;
        }
        $encoded = htmlspecialchars($stringValue, ENT_QUOTES, 'UTF-8');
    }

    $langFieldId = 'field_' . $langCode . '_' . $fieldName;
    $hiddenId = $langFieldId . '_value';

    foreach ([$hiddenId, $langFieldId] as $elementId) {
        foreach (['"', "'"] as $quote) {
            $q = preg_quote($quote, '/');
            $pattern = '/(\bid=' . $q . preg_quote($elementId, '/') . $q . '[^>]*\bvalue=' . $q . ')([^' . $q . ']*?)(' . $q . ')/i';
            if (preg_match($pattern, $html)) {
                return preg_replace_callback(
                    $pattern,
                    static function (array $m) use ($encoded): string {
                        return $m[1] . $encoded . $m[3];
                    },
                    $html,
                    1
                ) ?? $html;
            }

            $pattern = '/(\bvalue=' . $q . ')([^' . $q . ']*?)(' . $q . '[^>]*\bid=' . $q . preg_quote($elementId, '/') . $q . ')/i';
            if (preg_match($pattern, $html)) {
                return preg_replace_callback(
                    $pattern,
                    static function (array $m) use ($encoded): string {
                        return $m[1] . $encoded . $m[3];
                    },
                    $html,
                    1
                ) ?? $html;
            }
        }
    }

    return $html;
}

/**
 * @return list<string>
 */
function birkly_entry_collection_multilanguage_languages(string $collectionSlug): array
{
    require_once __DIR__ . '/entry_storage.php';
    $collectionMeta = birkly_entry_read_collection_meta($collectionSlug);
    if (!is_array($collectionMeta) || empty($collectionMeta['multilanguage']['enabled'])) {
        return [];
    }

    $languages = $collectionMeta['multilanguage']['languages'] ?? [];
    if (!is_array($languages)) {
        return [];
    }

    $normalized = [];
    foreach ($languages as $langCode) {
        if (is_string($langCode) && $langCode !== '') {
            $normalized[] = $langCode;
        }
    }

    return $normalized;
}

/**
 * @param mixed $rawValue
 * @param array<string, mixed> $field
 * @return mixed
 */
function birkly_entry_process_fieldtype_value(string $fieldName, string $fieldType, $rawValue, array $field)
{
    if (!function_exists('birkly_fieldtype_persists_in_entry')) {
        require_once __DIR__ . '/fieldtype_virtual.php';
    }
    if (!birkly_fieldtype_persists_in_entry($field)) {
        return null;
    }

    $value = $rawValue;
    if ($fieldType === '') {
        return $value;
    }

    $GLOBALS['__HARU_INTERNAL_CALL'] = true;
    require_once __DIR__ . '/../api/admin_fieldtypes.php';

    $className = getFieldtypeClass($fieldType);
    if (!$className || !class_exists($className)) {
        return $value;
    }

    try {
        $tempErrorReporting = error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
        $ft = new $className([
            'name' => $fieldName,
            'label' => $field['label'] ?? $fieldName,
            'value' => $rawValue,
            'required' => $field['required'] ?? false,
            'options' => $field['options'] ?? [],
        ]);
        error_reporting($tempErrorReporting);

        if (method_exists($ft, 'processValue')) {
            $value = $ft->processValue($rawValue);
        }
    } catch (Exception $e) {
        error_log("Error processing fieldtype {$fieldType} for field {$fieldName}: " . $e->getMessage());
    }

    return $value;
}

/**
 * Merge language-specific field writes into entry output (admin + MCP shared path).
 *
 * Only fields present in $entryData for each configured language are updated; other
 * language blocks and fields on the entry are preserved.
 *
 * @param array<string, mixed> $entryOutput
 * @param array<string, mixed> $entryData
 * @param list<array<string, mixed>> $fields
 * @param list<string> $multilanguageLanguages
 * @param array<string, mixed>|null $existingEntry
 * @return array<string, mixed>
 */
function birkly_entry_apply_multilang_fields(
    array $entryOutput,
    array $entryData,
    array $fields,
    array $multilanguageLanguages,
    ?array $existingEntry,
    string $entryId
): array {
    foreach ($multilanguageLanguages as $langCode) {
        if (!is_string($langCode) || $langCode === '') {
            continue;
        }

        if (!isset($entryOutput[$langCode]) || !is_array($entryOutput[$langCode])) {
            $entryOutput[$langCode] = is_array($existingEntry[$langCode] ?? null)
                ? $existingEntry[$langCode]
                : [];
        }

            foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $fieldName = (string) ($field['name'] ?? '');
            if ($fieldName === '') {
                continue;
            }

            if (!function_exists('birkly_fieldtype_persists_in_entry')) {
                require_once __DIR__ . '/fieldtype_virtual.php';
            }
            if (!birkly_fieldtype_persists_in_entry($field)) {
                continue;
            }

            $rawValue = birkly_entry_lang_field_raw($entryData, $langCode, $fieldName);
            if ($rawValue === null) {
                continue;
            }

            $fieldType = (string) ($field['type'] ?? '');
            $entryOutput[$langCode][$fieldName] = birkly_entry_process_fieldtype_value(
                $fieldName,
                $fieldType,
                $rawValue,
                $field
            );
        }

        $rawTitle = birkly_entry_lang_field_raw($entryData, $langCode, 'title');
        if ($rawTitle !== null) {
            $entryOutput[$langCode]['title'] = trim((string) $rawTitle);
        }
    }

    $entryOutput['title'] = birkly_entry_resolve_top_level_title(
        $entryOutput,
        $entryData,
        $multilanguageLanguages,
        $entryId,
        $existingEntry
    );

    return $entryOutput;
}

