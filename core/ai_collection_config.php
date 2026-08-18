<?php
/**
 * Shared collection configuration for AI create/update (entry_method, sub_collections, public API).
 */

declare(strict_types=1);

/** @return list<string> */
function birkly_ai_valid_entry_methods(): array
{
    return ['admin_only', 'public_api', 'user_submit'];
}

function birkly_ai_generate_public_api_key(): string
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $apiKey = 'birkly_';
    for ($i = 0; $i < 32; $i++) {
        $apiKey .= $chars[random_int(0, strlen($chars) - 1)];
    }

    return $apiKey;
}

/** @return list<string> */
function birkly_collection_api_settings_keys(): array
{
    return [
        'auto_publish',
        'rate_limit',
        'notification_email',
        'email_connection_alias',
        'captcha_provider',
        'captcha_site_key',
        'captcha_secret_key',
        'allow_anonymous_read',
    ];
}

/**
 * @param array<string, mixed> $apiSettings
 * @return list<string>
 */
function birkly_collection_api_settings_unknown_keys(array $apiSettings): array
{
    $allowed = birkly_collection_api_settings_keys();
    $unknown = [];
    foreach ($apiSettings as $key => $_) {
        if (!in_array((string) $key, $allowed, true)) {
            $unknown[] = (string) $key;
        }
    }
    return $unknown;
}

/**
 * @param array<string, mixed> $apiSettings
 */
function birkly_validate_api_settings_keys(array $apiSettings): ?string
{
    $unknown = birkly_collection_api_settings_unknown_keys($apiSettings);
    if ($unknown !== []) {
        return 'Unknown api_settings keys: ' . implode(', ', $unknown);
    }
    return null;
}

/**
 * @param array<string, mixed> $apiSettings
 * @return array<string, mixed>
 */
function birkly_migrate_collection_api_settings(array $apiSettings): array
{
    unset($apiSettings['public_read_fields'], $apiSettings['public_read_requires']);
    return $apiSettings;
}

/**
 * @param array<string, mixed> $apiSettings
 * @return array<string, mixed>
 */
function birkly_ai_normalize_api_settings(array $apiSettings): array
{
    $apiSettings = birkly_migrate_collection_api_settings($apiSettings);
    return [
        'auto_publish' => !empty($apiSettings['auto_publish']),
        'rate_limit' => isset($apiSettings['rate_limit']) ? (int) $apiSettings['rate_limit'] : 10,
        'notification_email' => (string) ($apiSettings['notification_email'] ?? ''),
        'email_connection_alias' => trim((string) ($apiSettings['email_connection_alias'] ?? '')),
        'captcha_provider' => (string) ($apiSettings['captcha_provider'] ?? ''),
        'captcha_site_key' => (string) ($apiSettings['captcha_site_key'] ?? ''),
        'captcha_secret_key' => (string) ($apiSettings['captcha_secret_key'] ?? ''),
        'allow_anonymous_read' => !empty($apiSettings['allow_anonymous_read']),
    ];
}

/**
 * Apply entry_method + api_key/api_settings to collection metadata.
 *
 * @param array<string, mixed> $collection
 * @param array<string, mixed> $actionData
 * @param array<string, mixed>|null $existing
 */
function birkly_ai_apply_entry_method_config(array &$collection, array $actionData, ?array $existing = null): void
{
    if (!isset($actionData['entry_method'])) {
        return;
    }

    $entryMethod = (string) $actionData['entry_method'];
    if (!in_array($entryMethod, birkly_ai_valid_entry_methods(), true)) {
        $entryMethod = 'admin_only';
    }

    $collection['entry_method'] = $entryMethod;
    $apiKey = (string) ($actionData['api_key'] ?? '');
    $apiSettings = is_array($actionData['api_settings'] ?? null) ? $actionData['api_settings'] : [];

    if ($entryMethod === 'public_api') {
        if ($apiKey === '' && is_array($existing) && !empty($existing['api_key'])) {
            $apiKey = (string) $existing['api_key'];
        }
        if ($apiKey === '') {
            $apiKey = birkly_ai_generate_public_api_key();
        }
        $collection['api_key'] = $apiKey;
        $merged = birkly_ai_normalize_api_settings($apiSettings);
        if (is_array($existing['api_settings'] ?? null)) {
            $merged = array_merge($existing['api_settings'], $merged);
        }
        $collection['api_settings'] = $merged;
    } else {
        unset($collection['api_key'], $collection['api_settings']);
    }
}

/**
 * @param array<int, mixed> $fields
 * @return list<array<string, mixed>>
 */
function birkly_ai_normalize_sub_collection_fields(array $fields): array
{
    $processed = [];
    foreach ($fields as $fieldData) {
        if (!is_array($fieldData)) {
            continue;
        }
        $fieldName = trim((string) ($fieldData['name'] ?? ''));
        $fieldLabel = trim((string) ($fieldData['label'] ?? ''));
        $fieldType = trim((string) ($fieldData['type'] ?? ''));
        if ($fieldName === '' && $fieldLabel !== '') {
            $fieldName = strtolower(trim(preg_replace('/[^A-Za-z0-9_]+/', '_', $fieldLabel)));
        }
        if ($fieldName === '' || $fieldType === '') {
            continue;
        }
        $field = [
            'name' => strtolower(trim(preg_replace('/[^A-Za-z0-9_]+/', '_', $fieldName))),
            'label' => $fieldLabel !== '' ? $fieldLabel : $fieldName,
            'type' => $fieldType,
            'required' => !empty($fieldData['required']),
        ];
        if (isset($fieldData['options']) && is_array($fieldData['options'])) {
            $field['options'] = $fieldData['options'];
        }
        $processed[] = $field;
    }

    return $processed;
}

/**
 * @param array<int, mixed> $subCollections
 * @return list<array<string, mixed>>
 */
function birkly_ai_process_sub_collections(array $subCollections, string $parentSlug): array
{
    $processed = [];
    foreach ($subCollections as $subCollectionData) {
        if (!is_array($subCollectionData)) {
            continue;
        }
        $slug = trim((string) ($subCollectionData['slug'] ?? ''));
        $name = trim((string) ($subCollectionData['name'] ?? ''));
        if ($slug === '' || $name === '') {
            continue;
        }

        $fields = [];
        if (isset($subCollectionData['schema']['fields']) && is_array($subCollectionData['schema']['fields'])) {
            $fields = birkly_ai_normalize_sub_collection_fields($subCollectionData['schema']['fields']);
        } elseif (isset($subCollectionData['fields']) && is_array($subCollectionData['fields'])) {
            $fields = birkly_ai_normalize_sub_collection_fields($subCollectionData['fields']);
        }

        $entryMethod = (string) ($subCollectionData['entry_method'] ?? 'admin_only');
        if (!in_array($entryMethod, birkly_ai_valid_entry_methods(), true)) {
            $entryMethod = 'admin_only';
        }

        $sub = [
            'slug' => $slug,
            'name' => $name,
            'description' => trim((string) ($subCollectionData['description'] ?? '')),
            'entry_method' => $entryMethod,
            'auto_publish' => !empty($subCollectionData['auto_publish'])
                || !empty($subCollectionData['api_settings']['auto_publish']),
            'fields' => $fields,
            'parent_field' => trim((string) ($subCollectionData['parent_field'] ?? ($parentSlug . '_id'))),
        ];

        if (!empty($subCollectionData['parent_sub_collection'])) {
            $sub['parent_sub_collection'] = (string) $subCollectionData['parent_sub_collection'];
        }

        $processed[] = $sub;
    }

    return $processed;
}

/**
 * Merge collection action payload into collection.json structure.
 *
 * @param array<string, mixed> $collection
 * @param array<string, mixed> $actionData
 * @param array<string, mixed>|null $existing
 */
function birkly_ai_merge_collection_config(array &$collection, array $actionData, ?array $existing = null): ?string
{
    if (isset($actionData['api_settings']) && is_array($actionData['api_settings'])) {
        $apiSettingsError = birkly_validate_api_settings_keys($actionData['api_settings']);
        if ($apiSettingsError !== null) {
            return $apiSettingsError;
        }
    }

    birkly_ai_apply_entry_method_config($collection, $actionData, $existing);

    if (isset($actionData['sub_collections']) && is_array($actionData['sub_collections'])) {
        $parentSlug = (string) ($collection['slug'] ?? $actionData['slug'] ?? '');
        $collection['sub_collections'] = birkly_ai_process_sub_collections($actionData['sub_collections'], $parentSlug);
    }

    return null;
}

/**
 * Summary row for list_collections AI read action.
 *
 * @return array<string, mixed>|null
 */
function birkly_ai_summarize_collection_from_dir(string $dir): ?array
{
    $collectionFile = $dir . '/collection.json';
    if (!is_file($collectionFile)) {
        return null;
    }

    $collectionMeta = json_decode((string) file_get_contents($collectionFile), true);
    if (!is_array($collectionMeta)) {
        return null;
    }

    $fields = $collectionMeta['fields'] ?? [];
    $schemaFile = $dir . '/schema.json';
    if (is_file($schemaFile)) {
        $schema = json_decode((string) file_get_contents($schemaFile), true);
        if (is_array($schema) && !empty($schema['fields'])) {
            $fields = $schema['fields'];
        }
    }

    $subCollections = $collectionMeta['sub_collections'] ?? [];
    $subSummary = [];
    if (is_array($subCollections)) {
        foreach ($subCollections as $sc) {
            if (!is_array($sc)) {
                continue;
            }
            $subSummary[] = [
                'slug' => $sc['slug'] ?? '',
                'name' => $sc['name'] ?? '',
                'entry_method' => $sc['entry_method'] ?? 'admin_only',
            ];
        }
    }

    return [
        'slug' => $collectionMeta['slug'] ?? basename($dir),
        'name' => $collectionMeta['name'] ?? $collectionMeta['title'] ?? basename($dir),
        'description' => $collectionMeta['description'] ?? '',
        'entry_method' => $collectionMeta['entry_method'] ?? 'admin_only',
        'field_count' => is_array($fields) ? count($fields) : 0,
        'fields' => $fields,
        'sub_collections' => $subSummary,
    ];
}
