<?php
/**
 * Canonical built-in fieldtype slugs and MCP/agent guidance (aliases documented).
 */

declare(strict_types=1);

/**
 * @return list<string>
 */
function birkly_core_fieldtype_slugs(): array
{
    return [
        'checkbox',
        'code',
        'color',
        'contenteditor',
        'date',
        'datetime',
        'dynamicitems',
        'email',
        'file',
        'gallery',
        'map',
        'markdown',
        'multiselect',
        'number',
        'oembed',
        'password',
        'phone',
        'radio',
        'range',
        'relation',
        'select',
        'tags',
        'text',
        'textarea',
        'time',
        'toggle',
        'url',
    ];
}

/**
 * Normalize schema type strings (including legacy MCP/agent aliases).
 */
function birkly_fieldtype_normalize_type_slug(string $type): string
{
    $normalized = strtolower(trim($type));
    $aliases = [
        'tag' => 'tags',
        'dynamic-items' => 'dynamicitems',
        'content-editor' => 'contenteditor',
        'editor' => 'contenteditor',
        'wysiwyg' => 'contenteditor',
        'media' => 'file',
        'image' => 'file',
        'richtext' => 'contenteditor',
    ];

    return $aliases[$normalized] ?? $normalized;
}

function birkly_fieldtype_is_known_slug(string $type): bool
{
    $normalized = birkly_fieldtype_normalize_type_slug($type);
    if (in_array($normalized, birkly_core_fieldtype_slugs(), true)) {
        return true;
    }

    if (function_exists('is_plugin_fieldtype') && is_plugin_fieldtype($normalized)) {
        return true;
    }

    return false;
}

/**
 * One-line agent hint per normalized type (templates + MCP).
 */
function birkly_fieldtype_mcp_hint_for_type(string $type): string
{
    $type = birkly_fieldtype_normalize_type_slug($type);
    $map = [
        'text' => 'Scalar; use {title} or {\'field\'}.',
        'textarea' => 'Plain text; escaped in templates.',
        'contenteditor' => 'Trusted HTML; include field in _html_fields / type editor.',
        'markdown' => 'Rendered to HTML in templates when type is markdown.',
        'email' => 'Validated email string.',
        'url' => 'External URL string — not for media library assets.',
        'code' => 'Plain or JSON mode; $ and ${} preserved.',
        'number' => 'Numeric scalar.',
        'date' => 'Date string; optional {field format "…"}.',
        'datetime' => 'Datetime string.',
        'time' => 'Time string.',
        'checkbox' => 'Stored 0/1; filters use with \'field\' = \'1\'.',
        'toggle' => 'Boolean-like on/off.',
        'select' => 'Single option value.',
        'multiselect' => 'String array; loop or {\'field\'} comma list.',
        'tags' => 'String array; loop or {\'field\'} comma list.',
        'color' => 'Hex color string.',
        'file' => 'Media id; use {media_url(\'field\')} / {media_img(\'field\')}.',
        'gallery' => 'Media id array; {gallery_html(\'field\')} or loop + media_img.',
        'map' => 'Object lat/lng; dot paths only.',
        'range' => 'Object min/max; dot paths.',
        'phone' => 'Phone string.',
        'radio' => 'Single radio value.',
        'relation' => 'Relation objects; {relation_titles(\'field\')} or relation_links.',
        'oembed' => 'Embed URL; {oembed_html(\'field\')}.',
        'dynamicitems' => 'Block list; {dynamicitems_html(\'field\')}, {dynamicitem(\'field\', \'blockName\')}, {\'field\'.\'blockName\'} (nested: {\'field\'.\'group\'.\'block\'}), or loop blocks.',
        'password' => 'Hashed; never returned on read ([redacted]); not for templates.',
    ];

    return $map[$type] ?? 'See fieldtypes playbook.';
}

/**
 * @return list<string>
 */
function birkly_mcp_fieldtype_guidance_lines(): array
{
    return [
        'Valid built-in types: ' . implode(', ', birkly_core_fieldtype_slugs()) . '.',
        'Aliases (normalized automatically): image/media→file, editor/wysiwyg→contenteditor, tag→tags, boolean→use checkbox or toggle (invalid type).',
        'Not fieldtypes: slug (system entry field), json (use code mode json or sub-collections).',
        'Template shelf (core): media_url, media_img, gallery_html, oembed_html, relation_titles, relation_links, dynamicitems_html.',
        'create_collection validates fields before mkdir — invalid types fail without ghost directories.',
        'Sensitive: password fields masked on read_entry and public API.',
    ];
}
