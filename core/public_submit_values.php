<?php

declare(strict_types=1);

/**
 * Normalize multiselect/tags values from public form payloads (POST arrays, comma lists, JSON strings).
 *
 * @param array<string, mixed> $field Schema field definition
 * @param mixed $raw Submitted value
 * @return list<string>
 */
function birkly_public_submit_coerce_string_list_field(array $field, $raw): array
{
    $type = (string) ($field['type'] ?? '');
    $items = birkly_public_submit_parse_string_list_input($raw);
    if ($items === []) {
        return [];
    }

    $sanitized = [];
    foreach ($items as $item) {
        $s = trim($item);
        if ($s === '') {
            continue;
        }
        // Match text fields: strip tags; do not HTML-entity-encode stored JSON values.
        $s = strip_tags($s);
        if ($s === '') {
            continue;
        }
        $sanitized[] = $s;
    }
    $sanitized = array_values(array_unique($sanitized));

    if ($type === 'multiselect') {
        $allowed = birkly_public_submit_multiselect_allowed_values($field);
        if ($allowed !== []) {
            $allowedMap = array_fill_keys($allowed, true);
            $sanitized = array_values(array_filter(
                $sanitized,
                static fn (string $v): bool => isset($allowedMap[$v])
            ));
        }
    }

    return $sanitized;
}

/**
 * @return list<string>
 */
function birkly_public_submit_multiselect_allowed_values(array $field): array
{
    $options = $field['options'] ?? [];
    if (!is_array($options) || $options === []) {
        return [];
    }
    require_once __DIR__ . '/content_locale.php';
    $resolved = birkly_selection_resolve_options($options, null);
    $values = [];
    foreach ($resolved as $opt) {
        if (!is_array($opt)) {
            continue;
        }
        $v = (string) ($opt['value'] ?? '');
        if ($v !== '') {
            $values[] = $v;
        }
    }

    return $values;
}

/**
 * @param mixed $raw
 * @return list<string>
 */
function birkly_public_submit_parse_string_list_input($raw): array
{
    if (is_array($raw)) {
        $items = $raw;
    } elseif (is_string($raw)) {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [];
        }
        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $items = $decoded;
        } else {
            $items = preg_split('/\s*,\s*/', $trimmed, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
    } else {
        return [];
    }

    $out = [];
    foreach ($items as $item) {
        if (is_scalar($item) || (is_object($item) && method_exists($item, '__toString'))) {
            $s = trim((string) $item);
            if ($s !== '') {
                $out[] = $s;
            }
        }
    }

    return array_values($out);
}

/**
 * Whether a public-submit field value is empty for required validation.
 *
 * @param mixed $value
 */
function birkly_public_submit_field_value_is_empty(string $type, $value): bool
{
    if (in_array($type, ['multiselect', 'tags'], true)) {
        if (is_array($value)) {
            return $value === [];
        }

        return trim((string) $value) === '';
    }

    if (is_array($value)) {
        return $value === [];
    }

    return trim((string) $value) === '';
}