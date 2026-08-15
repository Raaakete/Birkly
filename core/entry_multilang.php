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
                return preg_replace($pattern, '${1}' . $encoded . '${3}', $html, 1);
            }

            $pattern = '/(\bvalue=' . $q . ')([^' . $q . ']*?)(' . $q . '[^>]*\bid=' . $q . preg_quote($elementId, '/') . $q . ')/i';
            if (preg_match($pattern, $html)) {
                return preg_replace($pattern, '${1}' . $encoded . '${3}', $html, 1);
            }
        }
    }

    return $html;
}
