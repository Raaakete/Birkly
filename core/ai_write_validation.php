<?php
/**
 * MCP / AI write-tool input validation — reject unknown keys instead of silent discard.
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $payload
 * @param list<string> $allowed
 */
function birkly_ai_reject_unknown_keys(array $payload, array $allowed, string $label = 'payload'): ?string
{
    $unknown = [];
    foreach ($payload as $key => $_) {
        if (!in_array((string) $key, $allowed, true)) {
            $unknown[] = (string) $key;
        }
    }
    if ($unknown === []) {
        return null;
    }
    sort($unknown);

    return 'Unknown ' . $label . ' keys: ' . implode(', ', $unknown);
}

/** @return list<string> */
function birkly_ai_collection_action_allowed_keys(): array
{
    return [
        'slug', 'collection', 'collection_slug', 'collectionSlug', 'name', 'title', 'description',
        'fields', 'schema', 'entry_method', 'api_settings', 'api_key', 'allow_public_read', 'sub_collections', 'data',
        'dry_run',
    ];
}

/** @return list<string> */
function birkly_ai_entry_write_meta_keys(): array
{
    return [
        'collection', 'title', 'slug', 'status', 'id', 'data', 'type', 'action', 'description',
        'arguments', 'payload', 'created_at', 'updated_at', 'published_at', 'created_by', 'updated_by',
    ];
}

/**
 * @return list<string>
 */
function birkly_ai_entry_write_allowed_keys_for_collection(string $collectionSlug): array
{
    $allowed = birkly_ai_entry_write_meta_keys();
    require_once __DIR__ . '/ai_collection_config.php';
    $record = birkly_ai_load_collection_record($collectionSlug);
    if (is_array($record) && !empty($record['fields']) && is_array($record['fields'])) {
        foreach ($record['fields'] as $field) {
            if (is_array($field) && !empty($field['name'])) {
                $allowed[] = (string) $field['name'];
            }
        }
    }

    return array_values(array_unique($allowed));
}

/**
 * Apply top-level title, slug, and status onto entry field data (create/update with nested data).
 *
 * @param array<string, mixed> $payload
 * @param array<string, mixed> $entryData
 * @return array<string, mixed>
 */
function birkly_ai_merge_entry_write_meta_from_payload(array $payload, array $entryData): array
{
    foreach (['title', 'slug', 'status'] as $key) {
        if (!array_key_exists($key, $payload)) {
            continue;
        }
        $value = $payload[$key];
        if ($value === null) {
            continue;
        }
        if ($key !== 'status' && is_string($value) && trim($value) === '') {
            continue;
        }
        $entryData[$key] = $value;
    }

    foreach (['collection', 'data', 'type', 'action', 'description', 'arguments', 'payload'] as $wrapper) {
        unset($entryData[$wrapper]);
    }

    return $entryData;
}

/** @return list<string> */
function birkly_ai_update_settings_allowed_keys(): array
{
    return ['category', 'settings', 'data', 'type', 'action', 'description', 'arguments', 'payload'];
}

/** @return list<string> */
function birkly_ai_update_page_allowed_keys(): array
{
    return ['slug', 'id', 'title', 'content', 'data', 'type', 'action', 'description', 'arguments', 'payload'];
}

/** @return list<string> */
function birkly_ai_update_user_allowed_keys(): array
{
    return ['id', 'email', 'name', 'role', 'data', 'type', 'action', 'description', 'arguments', 'payload'];
}

/**
 * Validate collection schema field types at write time.
 *
 * @param list<array<string, mixed>> $fields
 */
function birkly_ai_validate_collection_field_types(array $fields): ?string
{
    if ($fields === []) {
        return null;
    }

    require_once __DIR__ . '/../api/admin_fieldtypes.php';
    if (function_exists('load_plugins')) {
        require_once __DIR__ . '/plugins.php';
        load_plugins();
    }

    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $type = trim((string) ($field['type'] ?? ''));
        if ($type === '') {
            continue;
        }
        if (function_exists('is_plugin_fieldtype') && is_plugin_fieldtype(strtolower($type))) {
            continue;
        }
        if (getFieldtypeClass($type) === null) {
            return 'Unknown field type: ' . $type;
        }
    }

    return null;
}
