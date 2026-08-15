<?php
/**
 * Entry field validation helpers (P30).
 */

require_once __DIR__ . '/entry_multilang.php';

/**
 * Validate required schema fields before persisting an entry.
 *
 * @return array<string, string> field name => error message
 */
function birkly_validate_entry_required_fields(
    array $fields,
    array $entryOutput,
    array $entryData,
    bool $multilanguageEnabled = false,
    array $multilanguageLanguages = []
): array {
    $errors = [];
    $reserved = ['slug', 'status', 'published_at', 'expires_at', 'reviewer', 'meta_title', 'meta_description'];

    foreach ($fields as $field) {
        $fieldName = $field['name'] ?? '';
        if ($fieldName === '' || empty($field['required']) || in_array($fieldName, $reserved, true)) {
            continue;
        }

        $label = $field['label'] ?? $fieldName;
        $isEmpty = static function ($value): bool {
            if ($value === null) {
                return true;
            }
            if (is_string($value)) {
                return trim($value) === '';
            }
            if (is_array($value)) {
                return count($value) === 0;
            }
            return false;
        };

        if ($multilanguageEnabled && !empty($multilanguageLanguages)) {
            foreach ($multilanguageLanguages as $langCode) {
                $langValue = $entryOutput[$langCode][$fieldName]
                    ?? birkly_entry_lang_field_raw($entryData, $langCode, $fieldName);
                if ($isEmpty($langValue)) {
                    $errors[$fieldName] = $label . ' is required (' . $langCode . ')';
                    break;
                }
            }
            continue;
        }

        $value = $entryOutput[$fieldName] ?? $entryData[$fieldName] ?? null;
        if ($isEmpty($value)) {
            $errors[$fieldName] = $label . ' is required';
        }
    }

    return $errors;
}
