<?php
/**
 * Fieldtype badges for the collection builder. Each fieldtype declares its own
 * icon and description (BaseField::getIcon() / getDescription()); these helpers
 * only read them from the getAvailableFieldtypes() rows.
 */

require_once __DIR__ . '/icons.php';

function birkly_normalize_fieldtype_key(?string $fieldtype): string
{
    return strtolower(preg_replace('/[-_\s]+/', '', (string) $fieldtype));
}

/**
 * The registry row for a fieldtype, matched on name or type.
 *
 * @param array<int, array<string, mixed>> $fieldtypes getAvailableFieldtypes() rows
 * @return array<string, mixed>|null
 */
function birkly_fieldtype_registry_entry(?string $fieldtype, array $fieldtypes): ?array
{
    $key = birkly_normalize_fieldtype_key($fieldtype);
    if ($key === '') {
        return null;
    }
    foreach ($fieldtypes as $ft) {
        if (birkly_normalize_fieldtype_key((string) ($ft['name'] ?? '')) === $key
            || birkly_normalize_fieldtype_key((string) ($ft['type'] ?? '')) === $key) {
            return $ft;
        }
    }

    return null;
}

/**
 * Icon name declared by the fieldtype (BaseField::getIcon()), or the generic one.
 *
 * @param array<int, array<string, mixed>> $fieldtypes getAvailableFieldtypes() rows
 */
function birkly_fieldtype_registry_icon(?string $fieldtype, array $fieldtypes = []): string
{
    $icon = (string) (birkly_fieldtype_registry_entry($fieldtype, $fieldtypes)['icon'] ?? '');

    return $icon !== '' && in_array($icon, birkly_icon_names(), true) ? $icon : 'layers';
}

/**
 * @param array{size?: int|string, class?: string, title?: string, aria_hidden?: bool} $attrs
 */
function birkly_fieldtype_icon(?string $fieldtype, array $fieldtypes = [], array $attrs = []): string
{
    return birkly_icon(birkly_fieldtype_registry_icon($fieldtype, $fieldtypes), $attrs);
}

/**
 * @param array<int, array<string, mixed>> $fieldtypes
 */
function birkly_fieldtype_display_label(?string $fieldtype, array $fieldtypes = []): string
{
    if ($fieldtype === null || $fieldtype === '') {
        return 'Select type';
    }

    $entry = birkly_fieldtype_registry_entry($fieldtype, $fieldtypes);

    return $entry !== null ? (string) ($entry['label'] ?? $fieldtype) : ucfirst((string) $fieldtype);
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
    $iconHtml = $fieldtype ? birkly_fieldtype_icon($fieldtype, $fieldtypes, ['size' => $size, 'class' => 'fieldtype-type-icon-svg']) : '';

    return '<span class="fieldtype-type" data-fieldtype="' . $typeAttr . '">'
        . '<span class="fieldtype-type-icon" aria-hidden="true">' . $iconHtml . '</span>'
        . '<span class="fieldtype-type-label">' . $labelHtml . '</span>'
        . '</span>';
}
