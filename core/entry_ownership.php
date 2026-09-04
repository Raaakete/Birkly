<?php
/**
 * Schema-driven entry ownership helpers (P75).
 */

/**
 * Normalize schema to a flat list of field definitions.
 *
 * @param array<string, mixed>|list<array<string, mixed>> $schema
 * @return list<array<string, mixed>>
 */
function birkly_schema_fields_list($schema): array
{
    if (!is_array($schema)) {
        return [];
    }
    if (isset($schema['fields']) && is_array($schema['fields'])) {
        return $schema['fields'];
    }
    if (isset($schema[0]) && is_array($schema[0]) && isset($schema[0]['name'])) {
        return $schema;
    }
    return [];
}

/**
 * Find the canonical owner field definition (type=user, ownership=true).
 *
 * @param array<string, mixed>|list<array<string, mixed>> $schema
 * @return array<string, mixed>|null
 */
function birkly_get_owner_field_config($schema): ?array
{
    foreach (birkly_schema_fields_list($schema) as $field) {
        if (!is_array($field)) {
            continue;
        }
        if (($field['type'] ?? '') === 'user' && !empty($field['ownership'])) {
            return $field;
        }
    }
    return null;
}

/**
 * Resolve the owner user id for an entry (schema field first, then legacy keys).
 *
 * @param array<string, mixed> $entry
 * @param array<string, mixed>|list<array<string, mixed>>|null $schema
 */
function birkly_get_entry_owner_id(array $entry, $schema = null): string
{
    $ownerField = $schema !== null ? birkly_get_owner_field_config($schema) : null;
    if ($ownerField !== null) {
        $fieldName = (string) ($ownerField['name'] ?? 'owner');
        $value = $entry[$fieldName] ?? '';
        if (is_array($value)) {
            $value = $value[0] ?? '';
        }
        $value = trim((string) $value);
        if ($value !== '') {
            return $value;
        }
    }

    foreach (['owner', 'author_user_id', 'author_id', 'user_id'] as $legacyKey) {
        if (!array_key_exists($legacyKey, $entry)) {
            continue;
        }
        $value = $entry[$legacyKey];
        if (is_array($value)) {
            $value = $value[0] ?? '';
        }
        $value = trim((string) $value);
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

/**
 * Stamp the schema owner field from the active session user.
 *
 * @param array<string, mixed> $data
 * @param array<string, mixed>|list<array<string, mixed>> $schema
 * @return array<string, mixed>
 */
function birkly_stamp_entry_owner(array $data, $schema, ?string $userId, bool $allowAdminOverride = false): array
{
    $ownerField = birkly_get_owner_field_config($schema);
    if ($ownerField === null) {
        return $data;
    }

    $fieldName = (string) ($ownerField['name'] ?? 'owner');
    $required = !empty($ownerField['required']);
    $userId = trim((string) ($userId ?? ''));

    if ($allowAdminOverride) {
        $existing = $data[$fieldName] ?? '';
        if (is_array($existing)) {
            $existing = $existing[0] ?? '';
        }
        $existing = trim((string) $existing);
        if ($existing !== '') {
            $data[$fieldName] = $existing;
            return $data;
        }
        if ($userId !== '') {
            $data[$fieldName] = $userId;
            return $data;
        }
        if ($required) {
            throw new RuntimeException('Authentication required');
        }
        return $data;
    }

    if ($required && $userId === '') {
        throw new RuntimeException('Authentication required');
    }
    if ($userId !== '') {
        $data[$fieldName] = $userId;
    }

    return $data;
}

/**
 * Validate ownership field rules on a collection/sub-collection schema.
 *
 * @param array<string, mixed>|list<array<string, mixed>> $schema
 * @return array{valid:bool,errors:list<string>}
 */
function birkly_validate_schema_ownership_fields($schema): array
{
    $errors = [];
    $ownershipCount = 0;

    foreach (birkly_schema_fields_list($schema) as $field) {
        if (!is_array($field) || empty($field['ownership'])) {
            continue;
        }
        $fieldName = (string) ($field['name'] ?? '');
        if (($field['type'] ?? '') !== 'user') {
            $errors[] = $fieldName !== ''
                ? "Field \"$fieldName\" has ownership=true but is not type \"user\""
                : 'Ownership flag is only allowed on type "user" fields';
            continue;
        }
        $ownershipCount++;
    }

    if ($ownershipCount > 1) {
        $errors[] = 'At most one ownership field is allowed per schema';
    }

    return [
        'valid' => $ownershipCount <= 1 && $errors === [],
        'errors' => $errors,
    ];
}

/**
 * Validate ownership rules on a collection schema and its sub-collections.
 *
 * @param list<array<string, mixed>> $fields
 * @param list<array<string, mixed>> $subCollections
 */
function birkly_validate_collection_ownership_schemas(array $fields, array $subCollections = []): ?string
{
    $check = birkly_validate_schema_ownership_fields(['fields' => $fields]);
    if (!$check['valid']) {
        return implode('; ', $check['errors']);
    }

    foreach ($subCollections as $sub) {
        if (!is_array($sub)) {
            continue;
        }
        $subFields = $sub['fields'] ?? [];
        if (!is_array($subFields)) {
            $subFields = [];
        }
        $subCheck = birkly_validate_schema_ownership_fields(['fields' => $subFields]);
        if (!$subCheck['valid']) {
            $slug = (string) ($sub['slug'] ?? 'sub-collection');
            return 'Sub-collection "' . $slug . '": ' . implode('; ', $subCheck['errors']);
        }
        $nested = $sub['sub_collections'] ?? [];
        if (is_array($nested) && $nested !== []) {
            $nestedError = birkly_validate_collection_ownership_schemas([], $nested);
            if ($nestedError !== null) {
                return $nestedError;
            }
        }
    }

    return null;
}

/**
 * Remove client-supplied values for schema fields hidden from public forms.
 *
 * @param array<string, mixed> $fields
 * @param array<string, mixed>|list<array<string, mixed>> $schema
 * @return array<string, mixed>
 */
function birkly_filter_public_submit_fields(array $fields, $schema): array
{
    foreach (birkly_schema_fields_list($schema) as $field) {
        if (!is_array($field)) {
            continue;
        }
        $name = (string) ($field['name'] ?? '');
        if ($name === '') {
            continue;
        }
        $isOwnership = ($field['type'] ?? '') === 'user' && !empty($field['ownership']);
        $publicVisible = array_key_exists('public_visible', $field)
            ? !empty($field['public_visible'])
            : !$isOwnership;
        if (!$publicVisible) {
            unset($fields[$name]);
        }
    }

    return $fields;
}
