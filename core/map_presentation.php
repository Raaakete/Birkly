<?php

declare(strict_types=1);

/**
 * Storefront map embed (no JS API key — OpenStreetMap embed).
 *
 * @param mixed $value
 */
function birkly_map_render_html($value): string
{
    if (is_string($value) && $value !== '') {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $value = $decoded;
        }
    }
    if (!is_array($value)) {
        return '';
    }

    $lat = (float) ($value['lat'] ?? $value[0] ?? 0);
    $lng = (float) ($value['lng'] ?? $value[1] ?? 0);
    if ($lat === 0.0 && $lng === 0.0) {
        return '';
    }

    $label = trim((string) ($value['label'] ?? $value['address'] ?? ''));
    $pad = 0.02;
    $bbox = rawurlencode((string) ($lng - $pad) . ',' . (string) ($lat - $pad) . ','
        . (string) ($lng + $pad) . ',' . (string) ($lat + $pad));
    $marker = rawurlencode((string) $lat . ',' . (string) $lng);
    $src = 'https://www.openstreetmap.org/export/embed.html?bbox=' . $bbox
        . '&layer=mapnik&marker=' . $marker;

    $srcAttr = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
    $caption = $label !== ''
        ? '<p class="birkly-map-caption">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</p>'
        : '';

    return '<div class="birkly-map">'
        . '<iframe class="birkly-map-frame" src="' . $srcAttr . '" loading="lazy" title="Map"></iframe>'
        . $caption
        . '</div>';
}
