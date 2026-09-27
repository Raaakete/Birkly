<?php
/**
 * Birkly admin icons — Birch v5 pixel family (13 × 13).
 * Data lives in core/icon_data.php; admin/js/icon.js is generated from it.
 *
 * Icons render at exactly 13 px, or 26 px (2 px per box) when a size of 20 or
 * more is asked for. Never at fractional scales.
 */

function birkly_icon_data(): array
{
    static $data = null;
    if ($data === null) {
        $data = require __DIR__ . '/icon_data.php';
    }
    return $data;
}

/**
 * Resolve a pre-Birch name to its Birch icon. Unknown names pass through.
 */
function birkly_icon_resolve(string $name): string
{
    $aliases = birkly_icon_data()['aliases'];
    return $aliases[$name] ?? $name;
}

/**
 * Every callable icon name (Birch names and aliases).
 *
 * @return list<string>
 */
function birkly_icon_names(): array
{
    $data = birkly_icon_data();
    return array_values(array_unique(array_merge(
        array_keys($data['pixel']),
        array_keys($data['stroke']),
        array_keys($data['aliases'])
    )));
}

/**
 * One path of 1 × 1 cells per horizontal run, for a 13 × 13 grid.
 *
 * @param list<string> $rows
 */
function birkly_icon_grid_path(array $rows): string
{
    $d = '';
    foreach ($rows as $y => $row) {
        $x = 0;
        $len = strlen($row);
        while ($x < $len) {
            if ($row[$x] !== '#') {
                $x++;
                continue;
            }
            $start = $x;
            while ($x < $len && $row[$x] === '#') {
                $x++;
            }
            $w = $x - $start;
            $d .= "M{$start} {$y}h{$w}v1h-{$w}z";
        }
    }
    return $d;
}

function birkly_icon_pixel_size($requested): int
{
    return (is_numeric($requested) && (float) $requested >= 20) ? 26 : 13;
}

/**
 * Build inline SVG markup for an icon.
 *
 * @param array{size?: int|string, class?: string, title?: string, aria_hidden?: bool, pixel?: bool} $attrs
 */
function birkly_icon_svg(string $name, array $attrs = []): string
{
    static $paths = [];

    $data = birkly_icon_data();
    $birch = birkly_icon_resolve($name);
    $usePixel = !empty($attrs['pixel']) || !isset($data['stroke'][$birch]);
    if ($usePixel && !isset($data['pixel'][$birch])) {
        return '';
    }

    $size = birkly_icon_pixel_size($attrs['size'] ?? 13);
    $class = trim('birkly-icon' . ($size === 26 ? ' birkly-icon--l' : '') . ' ' . ($attrs['class'] ?? ''));
    $title = $attrs['title'] ?? '';
    $ariaHidden = $attrs['aria_hidden'] ?? ($title === '');
    $titleMarkup = $title !== '' ? '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>' : '';
    $ariaAttr = $ariaHidden ? ' aria-hidden="true"' : '';
    $classAttr = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');

    if ($usePixel) {
        $paths[$birch] ??= birkly_icon_grid_path($data['pixel'][$birch]);
        return sprintf(
            '<svg class="%s" width="%d" height="%d" viewBox="0 0 13 13" fill="currentColor" shape-rendering="crispEdges"%s>%s<path d="%s"/></svg>',
            $classAttr, $size, $size, $ariaAttr, $titleMarkup, $paths[$birch]
        );
    }

    return sprintf(
        '<svg class="%s" width="%d" height="%d" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="square"%s>%s<path d="%s"/></svg>',
        $classAttr, $size, $size, $ariaAttr, $titleMarkup, $data['stroke'][$birch]
    );
}

/**
 * Inline SVG for an icon (returns string for template <?= ?> usage).
 *
 * @param array{size?: int|string, class?: string, title?: string, aria_hidden?: bool, pixel?: bool} $attrs
 */
function birkly_icon(string $name, array $attrs = []): string
{
    return birkly_icon_svg($name, $attrs);
}
