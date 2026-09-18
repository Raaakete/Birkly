<?php

declare(strict_types=1);

require_once __DIR__ . '/fieldtype_catalog.php';
require_once __DIR__ . '/fieldtype_presentation.php';

/**
 * Render DynamicItems block values for templates (markdown/editor as HTML).
 *
 * @param mixed $value  array of blocks or JSON string
 */
function birkly_dynamicitems_render_html($value): string
{
    if (is_string($value) && $value !== '') {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($value)) {
        return '';
    }

    $parts = [];
    foreach ($value as $block) {
        if (!is_array($block)) {
            continue;
        }
        $type = birkly_fieldtype_normalize_type_slug((string) ($block['type'] ?? 'text'));
        $blockValue = $block['value'] ?? '';
        $name = (string) ($block['name'] ?? $type);
        $label = (string) ($block['label'] ?? '');

        $inner = birkly_fieldtype_format_leaf_for_template($name, $blockValue, $type, []);
        $trusted = in_array($type, ['contenteditor', 'markdown'], true);

        $class = 'birkly-dynamicitem birkly-dynamicitem--' . preg_replace('/[^a-z0-9_-]/', '', $type);
        if ($label !== '') {
            $parts[] = '<section class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">'
                . '<h3 class="birkly-dynamicitem-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</h3>'
                . ($trusted ? $inner : htmlspecialchars((string) $inner, ENT_QUOTES, 'UTF-8'))
                . '</section>';
        } else {
            $parts[] = '<div class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">'
                . ($trusted ? $inner : htmlspecialchars((string) $inner, ENT_QUOTES, 'UTF-8'))
                . '</div>';
        }
    }

    return implode("\n", $parts);
}
