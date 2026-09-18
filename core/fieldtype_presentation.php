<?php
/**
 * Fieldtype-owned presentation: template leaf formatting, HTML trust, API masking.
 */

declare(strict_types=1);

require_once __DIR__ . '/../fieldtypes/BaseField.php';
require_once __DIR__ . '/../fieldtypes/FieldRegistry.php';
require_once __DIR__ . '/fieldtype_html.php';
require_once __DIR__ . '/fieldtype_catalog.php';

use Birkly\FieldTypes\BaseField;
use Birkly\FieldTypes\FieldRegistry;

function birkly_fieldtype_presentation_boot(): void
{
    static $booted = false;
    if ($booted) {
        return;
    }
    FieldRegistry::autoRegisterFieldTypes();
    require_once __DIR__ . '/fieldtype_template_shelf.php';
    birkly_fieldtype_register_template_shelf();
    birkly_fieldtype_presentation_register_filters();
    $booted = true;
}

/**
 * @param array<string,mixed> $field
 */
function birkly_fieldtype_create_instance(array $field, $value = null): ?BaseField
{
    $type = strtolower(trim((string) ($field['type'] ?? '')));
    if ($type === '') {
        return null;
    }

    birkly_fieldtype_presentation_boot();

    $config = [
        'name' => (string) ($field['name'] ?? ''),
        'label' => (string) ($field['label'] ?? $field['name'] ?? ''),
        'value' => $value,
        'required' => !empty($field['required']),
        'options' => is_array($field['options'] ?? null) ? $field['options'] : [],
    ];
    foreach ($field as $key => $val) {
        if (!array_key_exists($key, $config)) {
            $config[$key] = $val;
        }
    }

    try {
        return FieldRegistry::createField($type, $config);
    } catch (Throwable $e) {
        error_log('birkly_fieldtype_create_instance: ' . $e->getMessage());
        return null;
    }
}

/**
 * @param list<array<string,mixed>> $fields
 * @return array<string,string> field name => type slug
 */
function birkly_collection_field_type_map_from_fields(array $fields): array
{
    $map = [];
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $name = trim((string) ($field['name'] ?? ''));
        $type = strtolower(trim((string) ($field['type'] ?? '')));
        if ($name !== '' && $type !== '') {
            $map[$name] = $type;
        }
    }

    return $map;
}

/**
 * @param array<string,mixed> $collectionMeta
 * @return array<string,string>
 */
function birkly_collection_field_type_map_from_meta(array $collectionMeta): array
{
    return birkly_collection_field_type_map_from_fields(birkly_collection_schema_fields($collectionMeta));
}

/**
 * @param list<string> $collectionSlugs
 * @return array<string,string>
 */
function birkly_page_bundle_field_type_map($storage, array $collectionSlugs): array
{
    require_once __DIR__ . '/public_read.php';

    $map = [];
    foreach (array_unique($collectionSlugs) as $slug) {
        $slug = trim((string) $slug);
        if ($slug === '' || $slug === 'site_elements') {
            continue;
        }
        $dir = birkly_public_resolve_collection_dir($storage, $slug);
        if ($dir === null) {
            continue;
        }
        $meta = birkly_collection_meta_with_schema($storage, $dir);
        foreach (birkly_collection_field_type_map_from_meta($meta) as $name => $type) {
            $map[$name] = $type;
        }
    }

    return $map;
}

function birkly_fieldtype_outputs_html_for_type(string $type): bool
{
    $type = strtolower(trim($type));
    if ($type === '') {
        return false;
    }

    if (in_array($type, ['editor', 'content-editor', 'contenteditor', 'wysiwyg', 'markdown'], true)) {
        return true;
    }

    birkly_fieldtype_presentation_boot();
    $class = FieldRegistry::getFieldType($type);
    if ($class && class_exists($class)) {
        try {
            $ft = new $class(['name' => '_probe', 'label' => '_probe']);
            if (method_exists($ft, 'outputsTrustedHtml') && $ft->outputsTrustedHtml()) {
                return true;
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    return birkly_fieldtype_outputs_html($type);
}

/**
 * @param list<array<string,mixed>> $fields
 * @return list<string>
 */
function birkly_collection_html_field_names_from_field_list(array $fields): array
{
    $names = [];
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $name = trim((string) ($field['name'] ?? ''));
        $type = (string) ($field['type'] ?? '');
        if ($name !== '' && birkly_fieldtype_outputs_html_for_type($type)) {
            $names[] = $name;
        }
    }

    return array_values(array_unique($names));
}

/**
 * @param array<string,mixed> $collectionMeta
 * @return list<string>
 */
function birkly_collection_html_field_names_from_meta_presentation(array $collectionMeta): array
{
    return birkly_collection_html_field_names_from_field_list(birkly_collection_schema_fields($collectionMeta));
}

function birkly_fieldtype_format_leaf_for_template(string $fieldName, $value, string $fieldType, array $context = []): string
{
    if ($value === null) {
        return '';
    }

    $fieldType = strtolower(trim($fieldType));
    if ($fieldType === 'password' && $value !== '') {
        return '••••••';
    }

    $field = ['name' => $fieldName, 'type' => $fieldType];
    $ft = birkly_fieldtype_create_instance($field, $value);
    if ($ft && method_exists($ft, 'isSensitive') && $ft->isSensitive()) {
        return '••••••';
    }
    if ($ft && method_exists($ft, 'formatValueForTemplate')) {
        return (string) $ft->formatValueForTemplate($value, $field, $context);
    }

    if (is_scalar($value)) {
        return (string) $value;
    }

    if (is_array($value)) {
        $parts = [];
        foreach ($value as $item) {
            if (is_scalar($item) || $item === null) {
                $parts[] = (string) $item;
            }
        }
        if ($parts !== []) {
            return implode(', ', $parts);
        }
    }

    return '';
}

/**
 * @param array<string,mixed> $entry
 * @param list<array<string,mixed>> $fields
 * @return array<string,mixed>
 */
function birkly_fieldtype_resolve_collection_dir($storage, string $collectionSlug): ?string
{
    require_once __DIR__ . '/public_read.php';
    $dir = birkly_public_resolve_collection_dir($storage, $collectionSlug);
    if ($dir !== null) {
        return $dir;
    }
    require_once __DIR__ . '/content_resolve.php';

    return birkly_content_resolve_collection_dir($storage, $collectionSlug);
}

function birkly_entry_mask_sensitive_fields(array $entry, array $fields): array
{
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $name = (string) ($field['name'] ?? '');
        if ($name === '' || !array_key_exists($name, $entry)) {
            continue;
        }
        $type = strtolower(trim((string) ($field['type'] ?? '')));
        if ($type === 'password' && ($entry[$name] ?? '') !== '' && $entry[$name] !== '[redacted]') {
            $entry[$name] = '[redacted]';
            continue;
        }
        $ft = birkly_fieldtype_create_instance($field, $entry[$name]);
        if ($ft && method_exists($ft, 'isSensitive') && $ft->isSensitive()) {
            $entry[$name] = $ft->maskValueForApiRead($entry[$name], $field);
        }
    }

    return $entry;
}

/**
 * @param array<string,mixed> $entry
 */
function birkly_entry_mask_sensitive_for_collection(array $entry, string $collectionSlug, $storage = null): array
{
    if ($storage === null) {
        require_once __DIR__ . '/storage/StorageFactory.php';
        $storage = get_storage();
    }
    $dir = birkly_fieldtype_resolve_collection_dir($storage, $collectionSlug);
    if ($dir === null) {
        return $entry;
    }
    $meta = birkly_collection_meta_with_schema($storage, $dir);
    $fields = birkly_collection_schema_fields($meta);

    return birkly_entry_mask_sensitive_fields($entry, $fields);
}

function birkly_fieldtype_presentation_register_filters(): void
{
    if (!function_exists('add_filter')) {
        require_once __DIR__ . '/plugins.php';
    }

    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;

    add_filter('birkly_ai_read_entry', static function (array $entry, string $collection) {
        return birkly_entry_mask_sensitive_for_collection($entry, $collection);
    }, 10);

    add_filter('birkly_public_entry_filter', static function (array $entry, string $collection) {
        return birkly_entry_mask_sensitive_for_collection($entry, $collection);
    }, 10);

    add_filter('birkly_ai_read_entry', static function (array $entry, string $collection) {
        return birkly_entry_attach_mcp_read_hints($entry, $collection);
    }, 15);
}

/**
 * Mask sensitive fields (and attach MCP hints) before returning entry JSON to AI/MCP clients.
 *
 * @param array<string,mixed> $entry
 */
function birkly_ai_prepare_entry_for_read_response(array $entry, string $collectionSlug, $storage = null): array
{
    if (!function_exists('apply_filters')) {
        require_once __DIR__ . '/plugins.php';
    }
    birkly_fieldtype_presentation_boot();

    return apply_filters('birkly_ai_read_entry', $entry, $collectionSlug);
}

/**
 * @param array<string,mixed> $entry
 * @return array<string,mixed>
 */
function birkly_entry_attach_mcp_read_hints(array $entry, string $collectionSlug, $storage = null): array
{
    if ($storage === null) {
        require_once __DIR__ . '/storage/StorageFactory.php';
        $storage = get_storage();
    }
    $dir = birkly_fieldtype_resolve_collection_dir($storage, $collectionSlug);
    if ($dir === null) {
        return $entry;
    }
    $meta = birkly_collection_meta_with_schema($storage, $dir);
    $fields = birkly_collection_schema_fields($meta);
    $hints = [];
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $name = trim((string) ($field['name'] ?? ''));
        $type = trim((string) ($field['type'] ?? ''));
        if ($name === '' || $type === '') {
            continue;
        }
        $hints[$name] = [
            'type' => birkly_fieldtype_normalize_type_slug($type),
            'hint' => birkly_fieldtype_mcp_hint_for_type($type),
        ];
    }
    if ($hints !== []) {
        $entry['_mcp_field_hints'] = $hints;
    }

    return $entry;
}
