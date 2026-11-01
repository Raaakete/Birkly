<?php

declare(strict_types=1);

require_once __DIR__ . '/fieldtype_catalog.php';
require_once __DIR__ . '/fieldtype_presentation.php';
require_once __DIR__ . '/dynamicitems_presentation.php';

/**
 * Whether a list looks like DynamicItems blocks (type + name on items).
 *
 * @param mixed $value
 */
function birkly_dynamicitems_is_block_list($value): bool
{
    if (!is_array($value) || $value === []) {
        return false;
    }
    if (function_exists('array_is_list')) {
        if (!array_is_list($value)) {
            return false;
        }
    } elseif (array_keys($value) !== range(0, count($value) - 1)) {
        return false;
    }
    foreach ($value as $item) {
        if (!is_array($item) || !array_key_exists('type', $item)) {
            return false;
        }
    }

    return true;
}

/**
 * @param list<array<string,mixed>> $blocks
 */
function birkly_dynamicitems_find_block_by_name(array $blocks, string $name): ?array
{
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        if ((string) ($block['name'] ?? '') === $name) {
            return $block;
        }
    }

    return null;
}

/**
 * Resolve the block array at a DynamicItems field path (without formatting).
 *
 * @param list<array<int,string>> $parts
 * @param array<string,mixed> $data
 * @return array<string,mixed>|null
 */
function birkly_dynamicitems_resolve_block_at_path(array $parts, array $data): ?array
{
    if ($parts === []) {
        return null;
    }

    $entry = (array_key_exists('entry', $data) && is_array($data['entry']))
        ? $data['entry']
        : $data;
    if (!is_array($entry)) {
        return null;
    }

    $firstKey = (isset($parts[0][1]) && $parts[0][1] !== '') ? $parts[0][1] : ($parts[0][2] ?? '');
    if ($firstKey === '' || !array_key_exists($firstKey, $entry)) {
        return null;
    }
    $current = $entry[$firstKey];
    if (!birkly_dynamicitems_is_block_list($current)) {
        return null;
    }

    $blocks = $current;
    $lastIndex = count($parts) - 1;
    for ($i = 1; $i <= $lastIndex; $i++) {
        $key = (isset($parts[$i][1]) && $parts[$i][1] !== '') ? $parts[$i][1] : ($parts[$i][2] ?? '');
        if ($key === '') {
            return null;
        }
        $block = birkly_dynamicitems_find_block_by_name($blocks, $key);
        if ($block === null) {
            return null;
        }
        if ($i === $lastIndex) {
            return $block;
        }
        $type = birkly_fieldtype_normalize_type_slug((string) ($block['type'] ?? ''));
        if ($type !== 'dynamicitems') {
            return null;
        }
        $nested = $block['value'] ?? null;
        if (!birkly_dynamicitems_is_block_list($nested)) {
            return null;
        }
        $blocks = $nested;
    }

    return null;
}

/**
 * @param list<string> $segments
 * @return list<array<int,string>>|null
 */
function birkly_dynamicitems_quoted_segments_to_parts(array $segments): ?array
{
    if (count($segments) < 2) {
        return null;
    }
    $parts = [];
    foreach ($segments as $segment) {
        $parts[] = [0 => '', 1 => (string) $segment, 2 => ''];
    }

    return $parts;
}

/**
 * Parse {'field'.'block'…} references from a template arg fragment.
 *
 * @param array<string,mixed> $data
 */
function birkly_dynamicitems_resolve_quoted_path_arg(string $raw, array $data): mixed
{
    if (!preg_match_all("/'([^']+)'/", $raw, $m) || count($m[1]) < 2) {
        return null;
    }
    $parts = birkly_dynamicitems_quoted_segments_to_parts($m[1]);
    if ($parts === null) {
        return null;
    }
    $block = birkly_dynamicitems_resolve_block_at_path($parts, $data);
    if ($block !== null) {
        return $block;
    }

    return birkly_dynamicitems_resolve_field_path($parts, $data);
}

/**
 * Resolve a block item's raw formatted value (no output escaping).
 *
 * @param array<string,mixed> $block
 * @param array<string,mixed> $context
 */
function birkly_dynamicitems_format_block_value_raw(array $block, array $context = []): string
{
    $type = birkly_fieldtype_normalize_type_slug((string) ($block['type'] ?? 'text'));
    $name = (string) ($block['name'] ?? $type);
    $value = $block['value'] ?? '';

    if ($type === 'dynamicitems') {
        return birkly_dynamicitems_render_html($value);
    }

    if (in_array($type, ['file', 'image', 'media'], true) && (is_string($value) || is_numeric($value))) {
        birkly_fieldtype_presentation_boot();
        $mediaUrl = function_exists('get_template_function') ? get_template_function('media_url') : null;
        if ($mediaUrl !== null) {
            return (string) $mediaUrl((string) $value);
        }
    }

    return birkly_fieldtype_format_leaf_for_template(
        $name,
        $value,
        $type,
        ['entry' => $context['entry'] ?? []]
    );
}

/**
 * Resolve a block item's value for template output (type-aware, escaped when required).
 *
 * @param array<string,mixed> $block
 * @param array<string,mixed> $context
 */
function birkly_dynamicitems_format_block_value(array $block, array $context = []): string
{
    $type = birkly_fieldtype_normalize_type_slug((string) ($block['type'] ?? 'text'));
    $formatted = birkly_dynamicitems_format_block_value_raw($block, $context);
    if (birkly_dynamicitems_block_outputs_trusted_html($type)) {
        return $formatted;
    }
    if (!function_exists('birkly_template_engine_escape_html')) {
        require_once __DIR__ . '/birkly_template_engine.php';
    }

    return birkly_template_engine_escape_html($formatted);
}

/**
 * Infer DynamicItems block type for dotted paths (e.g. b.value, value on spread block).
 *
 * @param array<string,mixed> $data
 */
function birkly_dynamicitems_infer_block_type_for_path(string $pathOrName, array $data): ?string
{
    $pathOrName = trim($pathOrName);
    if ($pathOrName === 'value' && isset($data['type']) && is_string($data['type']) && $data['type'] !== '') {
        return birkly_fieldtype_normalize_type_slug($data['type']);
    }
    if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\.value$/D', $pathOrName, $m)) {
        return null;
    }
    $var = $m[1];
    if (!array_key_exists($var, $data) || !is_array($data[$var])) {
        return null;
    }
    $block = $data[$var];
    if (!array_key_exists('type', $block)) {
        return null;
    }

    return birkly_fieldtype_normalize_type_slug((string) $block['type']);
}

/**
 * Resolve DynamicItems field + block name path on an entry (e.g. content → heading → value).
 *
 * @param list<array<int,string>> $parts
 * @param array<string,mixed> $data
 */
function birkly_dynamicitems_resolve_field_path(array $parts, array $data): ?string
{
    $block = birkly_dynamicitems_resolve_block_at_path($parts, $data);
    if ($block === null) {
        return null;
    }

    return birkly_dynamicitems_format_block_value($block, $data);
}

/**
 * Existence check for {'field'.'block'…} paths on DynamicItems fields.
 *
 * @param array<string,mixed> $data
 */
function birkly_dynamicitems_path_exists(string $ref, array $data): bool
{
    if (!preg_match_all("/'([^']+)'/", $ref, $m) || count($m[1]) < 2) {
        return false;
    }
    $parts = [];
    foreach ($m[1] as $segment) {
        $parts[] = [0 => '', 1 => $segment, 2 => ''];
    }
    return birkly_dynamicitems_resolve_field_path($parts, $data) !== null;
}

/**
 * Resolve dynamicitem(fieldName, blockName) on current entry context.
 *
 * @param array<string,mixed> $data
 */
function birkly_dynamicitems_resolve_named_item(string $fieldName, string $blockName, array $data): string
{
    $fieldName = trim($fieldName);
    $blockName = trim($blockName);
    if ($fieldName === '' || $blockName === '') {
        return '';
    }
    $parts = [
        [0 => '', 1 => $fieldName, 2 => ''],
        [0 => '', 1 => $blockName, 2 => ''],
    ];

    return birkly_dynamicitems_resolve_field_path($parts, $data) ?? '';
}

/**
 * Walk a dotted identifier in render data (returns arrays for nested loops).
 *
 * @param array<string,mixed> $data
 */
function birkly_dynamicitems_resolve_data_path(string $expr, array $data): mixed
{
    $trimmed = trim($expr);
    if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)+)$/D', $trimmed)) {
        return null;
    }
    if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)((?:\.[a-zA-Z_][a-zA-Z0-9_]*)+)$/D', $trimmed, $m)) {
        return null;
    }
    $head = $m[1];
    if (!array_key_exists($head, $data)) {
        return null;
    }
    $cur = $data[$head];
    $rest = $m[2];
    if (preg_match_all('/\.([a-zA-Z_][a-zA-Z0-9_]*)/', $rest, $segs)) {
        foreach ($segs[1] as $key) {
            if (!is_array($cur) || !array_key_exists($key, $cur)) {
                return null;
            }
            $cur = $cur[$key];
        }
    }

    return $cur;
}

/**
 * Coerce template-function args (media id from block.value paths, block objects).
 *
 * @param mixed $arg
 */
function birkly_dynamicitems_coerce_media_arg($arg): string
{
    if (is_string($arg) || is_numeric($arg)) {
        return trim((string) $arg);
    }
    if (!is_array($arg)) {
        return '';
    }
    if (array_key_exists('value', $arg) && (is_string($arg['value']) || is_numeric($arg['value']))) {
        return trim((string) $arg['value']);
    }

    return '';
}
