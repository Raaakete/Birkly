<?php
/**
 * Fieldtype icon registry — maps fieldtype keys to Birkly icon names.
 */

require_once __DIR__ . '/icons.php';

/**
 * @return array<string, string> normalized fieldtype key => icon name
 */
function birkly_fieldtype_icon_map(): array
{
    return [
        'text' => 'text-aa',
        'textarea' => 'article',
        'contenteditor' => 'layout',
        'markdown' => 'article',
        'code' => 'code',
        'number' => 'hash',
        'range' => 'sliders-horizontal',
        'date' => 'calendar',
        'datetime' => 'calendar-clock',
        'time' => 'clock',
        'checkbox' => 'check-square',
        'toggle' => 'toggle-left',
        'radio' => 'circle-dot',
        'select' => 'caret-down',
        'multiselect' => 'list-checks',
        'color' => 'palette',
        'tags' => 'tag',
        'email' => 'envelope',
        'url' => 'link',
        'phone' => 'phone',
        'file' => 'file-text',
        'gallery' => 'squares-four',
        'map' => 'map-pin',
        'relation' => 'users',
        'dynamicitems' => 'list',
        'oembed' => 'play',
        'media' => 'image',
    ];
}

function birkly_normalize_fieldtype_key(?string $fieldtype): string
{
    return strtolower(preg_replace('/[-_\s]+/', '', (string) $fieldtype));
}

function birkly_fieldtype_registry_icon(?string $fieldtype): string
{
    $key = birkly_normalize_fieldtype_key($fieldtype);
    $map = birkly_fieldtype_icon_map();

    return $map[$key] ?? 'stack';
}

/**
 * @param array{size?: int|string, class?: string, title?: string, aria_hidden?: bool} $attrs
 */
function birkly_fieldtype_icon(?string $fieldtype, array $attrs = []): string
{
    return birkly_icon(birkly_fieldtype_registry_icon($fieldtype), $attrs);
}

/**
 * @param array<int, array<string, mixed>> $fieldtypes
 */
function birkly_fieldtype_display_label(?string $fieldtype, array $fieldtypes = []): string
{
    if ($fieldtype === null || $fieldtype === '') {
        return 'Select type';
    }

    foreach ($fieldtypes as $ft) {
        $name = (string) ($ft['name'] ?? '');
        $type = (string) ($ft['type'] ?? '');
        if ($name === $fieldtype || $type === $fieldtype) {
            return (string) ($ft['label'] ?? $fieldtype);
        }
    }

    return ucfirst((string) $fieldtype);
}

/**
 * @param array{size?: int|string} $attrs
 * @param array<int, array<string, mixed>> $fieldtypes
 */
function birkly_fieldtype_type_badge(?string $fieldtype, array $fieldtypes = [], array $attrs = []): string
{
    $size = $attrs['size'] ?? 14;
    $label = birkly_fieldtype_display_label($fieldtype, $fieldtypes);
    $typeAttr = htmlspecialchars((string) $fieldtype, ENT_QUOTES, 'UTF-8');
    $labelHtml = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $iconHtml = $fieldtype ? birkly_fieldtype_icon($fieldtype, ['size' => $size, 'class' => 'fieldtype-type-icon-svg']) : '';

    return '<span class="fieldtype-type" data-fieldtype="' . $typeAttr . '">'
        . '<span class="fieldtype-type-icon" aria-hidden="true">' . $iconHtml . '</span>'
        . '<span class="fieldtype-type-label">' . $labelHtml . '</span>'
        . '</span>';
}
