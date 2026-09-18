<?php
/**
 * Helpers for fieldtypes that output trusted HTML in templates.
 */

declare(strict_types=1);

/**
 * Whether a collection schema field type stores rich HTML (Content Editor, legacy wysiwyg).
 */
function birkly_fieldtype_outputs_html(string $type): bool
{
    $normalized = strtolower(trim($type));

    return in_array($normalized, ['editor', 'content-editor', 'contenteditor', 'wysiwyg', 'markdown'], true);
}

/**
 * @return list<array<string,mixed>>
 */
function birkly_collection_schema_fields(array $collectionMeta): array
{
    if (isset($collectionMeta['schema']['fields']) && is_array($collectionMeta['schema']['fields'])) {
        return $collectionMeta['schema']['fields'];
    }
    if (isset($collectionMeta['fields']) && is_array($collectionMeta['fields'])) {
        return $collectionMeta['fields'];
    }

    return [];
}

/**
 * Field names on a collection that should render as raw HTML in templates.
 *
 * @return list<string>
 */
function birkly_collection_html_field_names_from_meta(array $collectionMeta): array
{
    $names = [];
    foreach (birkly_collection_schema_fields($collectionMeta) as $field) {
        if (!is_array($field)) {
            continue;
        }
        $name = trim((string) ($field['name'] ?? ''));
        $type = (string) ($field['type'] ?? '');
        if ($name !== '' && birkly_fieldtype_outputs_html($type)) {
            $names[] = $name;
        }
    }

    return array_values(array_unique($names));
}

/**
 * Load collection metadata merged with schema.json field definitions.
 *
 * @return array<string,mixed>
 */
function birkly_collection_meta_with_schema($storage, string $dir): array
{
    $meta = $storage->read('collections/' . $dir . '/collection.json');
    if (!is_array($meta)) {
        $meta = [];
    }

    $schema = $storage->read('collections/' . $dir . '/schema.json');
    if (is_array($schema)) {
        if (isset($schema['fields']) && is_array($schema['fields'])) {
            $meta['fields'] = $schema['fields'];
        }
        if (isset($schema['schema']) && is_array($schema['schema'])) {
            $meta['schema'] = $schema['schema'];
        }
    }

    return $meta;
}

/**
 *
 * @param list<string> $collectionSlugs
 * @return list<string>
 */
function birkly_page_bundle_html_field_names($storage, array $collectionSlugs): array
{
    require_once __DIR__ . '/public_read.php';

    $all = [];
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
        if ($meta === []) {
            continue;
        }
        foreach (birkly_collection_html_field_names_from_meta($meta) as $name) {
            $all[$name] = true;
        }
    }

    return array_keys($all);
}

/**
 * Extract the leaf field name from a template path (e.g. 'body', 'content'.0.'value').
 */
function birkly_template_engine_field_name_for_escape(string $pathOrName): string
{
    $pathOrName = trim($pathOrName);
    if ($pathOrName === '') {
        return '';
    }

    if (preg_match_all("/'([^']+)'|(\\d+)/", $pathOrName, $parts, PREG_SET_ORDER) && count($parts) > 0) {
        $last = $parts[count($parts) - 1];

        return $last[1] !== '' ? (string) $last[1] : (string) ($last[2] ?? '');
    }

    if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)/', $pathOrName, $m)) {
        return (string) $m[1];
    }

    return $pathOrName;
}

/**
 * @param array<string,mixed> $options
 */
function birkly_template_engine_should_output_raw_html(string $pathOrName, array $options): bool
{
    if (($options['escapeHtml'] ?? null) === false) {
        return true;
    }

    $fieldName = birkly_template_engine_field_name_for_escape($pathOrName);
    if ($fieldName === '') {
        return false;
    }

    // Legacy name heuristic (content / html in field slug).
    if (stripos($fieldName, 'content') !== false || stripos($fieldName, 'html') !== false) {
        return true;
    }

    $htmlFields = $options['htmlFields'] ?? [];
    if (!is_array($htmlFields)) {
        return false;
    }

    if (array_is_list($htmlFields)) {
        return in_array($fieldName, $htmlFields, true);
    }

    if (isset($htmlFields[$fieldName])) {
        return true;
    }

    foreach ($htmlFields as $names) {
        if (is_array($names) && in_array($fieldName, $names, true)) {
            return true;
        }
    }

    $fieldTypes = $options['fieldTypes'] ?? [];
    if (is_array($fieldTypes)) {
        $type = (string) ($fieldTypes[$fieldName] ?? '');
        if ($type !== '') {
            require_once __DIR__ . '/fieldtype_presentation.php';
            if (birkly_fieldtype_outputs_html_for_type($type)) {
                return true;
            }
        }
    }

    return false;
}
