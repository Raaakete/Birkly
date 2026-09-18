<?php

declare(strict_types=1);

require_once __DIR__ . '/fieldtype_catalog.php';
require_once __DIR__ . '/fieldtype_presentation.php';
require_once __DIR__ . '/map_presentation.php';
require_once __DIR__ . '/plugins/template_functions.php';

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

        $inner = birkly_dynamicitems_render_block_inner($type, $blockValue, $name);
        $trusted = in_array($type, ['contenteditor', 'content-editor', 'editor', 'wysiwyg', 'markdown'], true);

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

/**
 * @param mixed $blockValue
 */
function birkly_dynamicitems_render_block_inner(string $type, $blockValue, string $name): string
{
    $type = birkly_fieldtype_normalize_type_slug($type);

    if (in_array($type, ['file', 'image', 'media'], true) && (is_string($blockValue) || is_numeric($blockValue))) {
        $mediaImg = function_exists('get_template_function') ? get_template_function('media_img') : null;
        if ($mediaImg !== null) {
            return (string) $mediaImg((string) $blockValue, '');
        }
    }

    if ($type === 'map') {
        return birkly_map_render_html($blockValue);
    }

    if ($type === 'range') {
        $rangeHtml = function_exists('get_template_function') ? get_template_function('range_html') : null;
        if ($rangeHtml !== null) {
            return (string) $rangeHtml($blockValue);
        }
    }

    if ($type === 'oembed' && is_string($blockValue) && $blockValue !== '') {
        $oembed = function_exists('get_template_function') ? get_template_function('oembed_html') : null;
        if ($oembed !== null) {
            return (string) $oembed($blockValue);
        }
    }

    return birkly_fieldtype_format_leaf_for_template($name, $blockValue, $type, []);
}
