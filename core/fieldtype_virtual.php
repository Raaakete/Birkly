<?php
/**
 * Virtual fieldtype helpers (P76).
 *
 * Virtual fieldtypes render in admin but do not persist values on entry JSON.
 */

/**
 * Instantiate a fieldtype class for schema inspection.
 *
 * @param array<string, mixed> $field
 */
function birkly_fieldtype_new_instance(array $field, $value = null): ?object
{
    $fieldType = (string) ($field['type'] ?? '');
    if ($fieldType === '' || !function_exists('getFieldtypeClass')) {
        return null;
    }

    $className = getFieldtypeClass($fieldType);
    if ($className === '' || !class_exists($className)) {
        return null;
    }

    try {
        $config = [
            'name' => (string) ($field['name'] ?? ''),
            'label' => (string) ($field['label'] ?? $field['name'] ?? ''),
            'value' => $value,
            'required' => !empty($field['required']),
            'options' => is_array($field['options'] ?? null) ? $field['options'] : [],
        ];

        foreach ($field as $key => $setting) {
            if (!array_key_exists($key, $config)) {
                $config[$key] = $setting;
            }
        }

        return new $className($config);
    } catch (Throwable $e) {
        error_log('birkly_fieldtype_new_instance: ' . $e->getMessage());
        return null;
    }
}

/**
 * Whether a fieldtype persists its value on the entry record.
 *
 * @param array<string, mixed> $field
 */
function birkly_fieldtype_persists_in_entry(array $field): bool
{
    $ft = birkly_fieldtype_new_instance($field);
    if ($ft === null) {
        return true;
    }
    if (method_exists($ft, 'persistsInEntry')) {
        return (bool) $ft->persistsInEntry();
    }

    return true;
}

/**
 * Resolve a virtual field value when loading an entry for admin/API display.
 *
 * @param array<string, mixed> $entry
 * @param array<string, mixed> $field
 * @param array<string, mixed> $context
 */
function birkly_fieldtype_resolve_value_for_entry(array $entry, array $field, array $context = [])
{
    $stored = $entry[(string) ($field['name'] ?? '')] ?? null;
    $ft = birkly_fieldtype_new_instance($field, $stored);
    if ($ft === null) {
        return $stored;
    }
    if (method_exists($ft, 'resolveValueForEntry')) {
        return $ft->resolveValueForEntry($entry, $field, $context);
    }

    return $stored;
}

/**
 * Remove non-persisting schema fields from an entry payload before save.
 *
 * @param array<string, mixed> $entry
 * @param list<array<string, mixed>> $fields
 * @param list<string> $multilanguageLanguages
 * @return array<string, mixed>
 */
function birkly_strip_non_persisting_entry_fields(
    array $entry,
    array $fields,
    bool $multilanguageEnabled = false,
    array $multilanguageLanguages = []
): array {
    $nonPersisting = [];
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $name = (string) ($field['name'] ?? '');
        if ($name === '' || birkly_fieldtype_persists_in_entry($field)) {
            continue;
        }
        $nonPersisting[$name] = true;
    }

    if ($nonPersisting === []) {
        return $entry;
    }

    foreach (array_keys($nonPersisting) as $name) {
        unset($entry[$name]);
    }

    if ($multilanguageEnabled) {
        foreach ($multilanguageLanguages as $langCode) {
            if (!is_string($langCode) || $langCode === '' || !isset($entry[$langCode]) || !is_array($entry[$langCode])) {
                continue;
            }
            foreach (array_keys($nonPersisting) as $name) {
                unset($entry[$langCode][$name]);
            }
        }
    }

    return $entry;
}

/**
 * Attach resolved virtual field values for admin display (does not mutate stored entry files).
 *
 * @param array<string, mixed> $entry
 * @param list<array<string, mixed>> $fields
 * @param array<string, mixed> $context
 * @return array<string, mixed>
 */
function birkly_attach_virtual_field_values(array $entry, array $fields, array $context = []): array
{
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $name = (string) ($field['name'] ?? '');
        if ($name === '' || birkly_fieldtype_persists_in_entry($field)) {
            continue;
        }
        $entry[$name] = birkly_fieldtype_resolve_value_for_entry($entry, $field, $context);
    }

    return $entry;
}
