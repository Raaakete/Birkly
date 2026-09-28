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
 * $cells picks which grid characters are drawn; the default is every cell
 * that is on at rest (# and the part letters a–z).
 *
 * @param list<string> $rows
 */
function birkly_icon_grid_path(array $rows, string $cells = '#abcdefghijklmnopqrstuvwxyz'): string
{
    $d = '';
    foreach ($rows as $y => $row) {
        $x = 0;
        $len = strlen($row);
        while ($x < $len) {
            if (!str_contains($cells, $row[$x])) {
                $x++;
                continue;
            }
            $start = $x;
            while ($x < $len && str_contains($cells, $row[$x])) {
                $x++;
            }
            $w = $x - $start;
            $d .= "M{$start} {$y}h{$w}v1h-{$w}z";
        }
    }
    return $d;
}

/**
 * Split a grid into its layers: '#' (the still body), then one path per part
 * letter (on at rest) and per ghost digit (off at rest), in that order.
 *
 * @param list<string> $rows
 * @return array<string, string>
 */
function birkly_icon_grid_layers(array $rows): array
{
    $chars = count_chars(implode('', $rows), 3);
    $parts = preg_replace('/[^a-z]/', '', $chars);
    $ghosts = preg_replace('/[^1-9]/', '', $chars);
    $layers = [];
    $base = birkly_icon_grid_path($rows, '#');
    if ($base !== '') {
        $layers['#'] = $base;
    }
    foreach (str_split($parts . $ghosts) as $c) {
        if ($c !== '') {
            $layers[$c] = birkly_icon_grid_path($rows, $c);
        }
    }
    return $layers;
}

/**
 * The hover gesture of an icon, or '' when it has none.
 */
function birkly_icon_gesture(string $name): string
{
    return birkly_icon_data()['gestures'][birkly_icon_resolve($name)] ?? '';
}

function birkly_icon_pixel_size($requested): int
{
    return (is_numeric($requested) && (float) $requested >= 20) ? 26 : 13;
}

/**
 * Path markup for a pixel icon: the body, then parts (data-part="a") and
 * ghosts (data-part="1", opacity 0 until a gesture shows them).
 */
function birkly_icon_pixel_paths(string $birch): string
{
    static $markup = [];
    if (!isset($markup[$birch])) {
        $out = '';
        foreach (birkly_icon_grid_layers(birkly_icon_data()['pixel'][$birch]) as $layer => $d) {
            if ($layer === '#') {
                $out .= '<path d="' . $d . '"/>';
            } elseif (ctype_digit((string) $layer)) {
                $out .= '<path data-part="' . $layer . '" opacity="0" d="' . $d . '"/>';
            } else {
                $out .= '<path data-part="' . $layer . '" d="' . $d . '"/>';
            }
        }
        $markup[$birch] = $out;
    }
    return $markup[$birch];
}

/**
 * Build inline SVG markup for an icon.
 *
 * `gesture` overrides the icon's hover gesture by name, or turns it off with
 * false (drag handles, decorative icons, icons inside moving content).
 *
 * @param array{size?: int|string, class?: string, title?: string, aria_hidden?: bool, pixel?: bool, gesture?: string|false} $attrs
 */
function birkly_icon_svg(string $name, array $attrs = []): string
{
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
    $gesture = array_key_exists('gesture', $attrs) ? (string) $attrs['gesture'] : ($data['gestures'][$birch] ?? '');
    $gestureAttr = preg_match('/^[a-z][a-z0-9-]*$/', $gesture) === 1 ? ' data-gesture="' . $gesture . '"' : '';

    if ($usePixel) {
        return sprintf(
            '<svg class="%s" width="%d" height="%d" viewBox="0 0 13 13" fill="currentColor" shape-rendering="crispEdges"%s%s>%s%s</svg>',
            $classAttr, $size, $size, $gestureAttr, $ariaAttr, $titleMarkup, birkly_icon_pixel_paths($birch)
        );
    }

    return sprintf(
        '<svg class="%s" width="%d" height="%d" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="square"%s%s>%s<path d="%s"/></svg>',
        $classAttr, $size, $size, $gestureAttr, $ariaAttr, $titleMarkup, $data['stroke'][$birch]
    );
}

/**
 * Inline SVG for an icon (returns string for template <?= ?> usage).
 *
 * @param array{size?: int|string, class?: string, title?: string, aria_hidden?: bool, pixel?: bool, gesture?: string|false} $attrs
 */
function birkly_icon(string $name, array $attrs = []): string
{
    return birkly_icon_svg($name, $attrs);
}
