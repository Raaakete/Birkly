<?php
/**
 * Figma JSON import → Studio canvas sections + design tokens (P26-M11).
 *
 * Accepts simplified Figma export JSON (frame tree + fills).
 */

class StudioFigmaImporter
{
    /**
     * @return array{sections: array<int, array<string, mixed>>, tokens: array<string, mixed>, warnings: array<int, string>}
     */
    public static function import(array $json): array
    {
        $warnings = [];
        $tokens = ['colors' => [], 'spacing' => []];
        $sections = [];

        $frames = self::extractFrames($json);
        if ($frames === []) {
            $warnings[] = 'No frames found — expected "frames" array or document.children';
            return ['sections' => [], 'tokens' => $tokens, 'warnings' => $warnings];
        }

        $colorIdx = 0;
        foreach ($frames as $frame) {
            if (!is_array($frame)) {
                continue;
            }
            $section = self::frameToSection($frame, $colorIdx, $tokens, $warnings);
            if ($section !== null) {
                $sections[] = $section;
            }
        }

        return ['sections' => $sections, 'tokens' => $tokens, 'warnings' => $warnings];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function extractFrames(array $json): array
    {
        if (isset($json['frames']) && is_array($json['frames'])) {
            return $json['frames'];
        }
        $doc = $json['document'] ?? $json;
        $children = $doc['children'] ?? [];
        if (!is_array($children)) {
            return [];
        }
        $frames = [];
        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }
            $type = strtolower((string) ($child['type'] ?? ''));
            if (in_array($type, ['frame', 'group', 'component', 'section'], true)) {
                $frames[] = $child;
            }
        }
        return $frames;
    }

    /**
     * @param array<string, mixed> $tokens
     * @param array<int, string> $warnings
     */
    private static function frameToSection(array $frame, int &$colorIdx, array &$tokens, array &$warnings): ?array
    {
        $name = (string) ($frame['name'] ?? 'Imported section');
        $sid = 'figma-' . studio_slugify($name) . '-' . substr(studio_new_id(), 4, 6);
        $bg = self::extractFillColor($frame);
        $classes = ['studio-section', 'studio-figma-import'];
        $styles = [];
        if ($bg !== '') {
            $tokenKey = 'figma-' . (++$colorIdx);
            $tokens['colors'][$tokenKey] = $bg;
            $styles['backgroundColor'] = 'var(--studio-colors-' . $tokenKey . ')';
        }

        $elements = [];
        $children = $frame['children'] ?? [];
        if (is_array($children)) {
            foreach ($children as $child) {
                if (!is_array($child)) {
                    continue;
                }
                $el = self::nodeToElement($child, $warnings);
                if ($el !== null) {
                    $elements[] = $el;
                }
            }
        }

        if ($elements === []) {
            $elements[] = [
                'id' => studio_new_id(),
                'type' => 'heading',
                'tag' => 'h2',
                'content' => $name,
                'classes' => ['studio-figma-title'],
            ];
        }

        return [
            'id' => $sid,
            'classes' => $classes,
            'styles' => $styles,
            'layout' => ['type' => 'grid', 'columns' => 1, 'gap' => '1rem'],
            'rows' => [[
                'id' => $sid . '-row',
                'columns' => [[
                    'id' => $sid . '-col',
                    'width' => '100%',
                    'elements' => $elements,
                ]],
            ]],
            'meta' => ['figmaName' => $name],
        ];
    }

    /**
     * @param array<int, string> $warnings
     */
    private static function nodeToElement(array $node, array &$warnings): ?array
    {
        $type = strtolower((string) ($node['type'] ?? ''));
        $id = studio_new_id();

        if ($type === 'text') {
            $text = (string) ($node['characters'] ?? $node['text'] ?? '');
            $fontSize = (int) ($node['style']['fontSize'] ?? $node['fontSize'] ?? 16);
            $tag = $fontSize >= 28 ? 'h2' : ($fontSize >= 20 ? 'h3' : 'p');
            $el = [
                'id' => $id,
                'type' => $tag === 'p' ? 'text' : 'heading',
                'tag' => $tag,
                'content' => $text,
                'classes' => ['studio-figma-text'],
            ];
            $color = self::extractFillColor($node);
            if ($color !== '') {
                $el['styles'] = ['color' => $color];
            }
            return $el;
        }

        if (in_array($type, ['rectangle', 'ellipse', 'vector'], true)) {
            $warnings[] = 'Skipped shape node "' . ($node['name'] ?? $type) . '" — use text frames for content';
            return null;
        }

        if ($type === 'frame' || $type === 'group') {
            $nested = $node['children'] ?? [];
            if (is_array($nested)) {
                foreach ($nested as $child) {
                    if (is_array($child)) {
                        $el = self::nodeToElement($child, $warnings);
                        if ($el !== null) {
                            return $el;
                        }
                    }
                }
            }
        }

        return null;
    }

    private static function extractFillColor(array $node): string
    {
        $fills = $node['fills'] ?? $node['background'] ?? [];
        if (!is_array($fills)) {
            return '';
        }
        foreach ($fills as $fill) {
            if (!is_array($fill)) {
                continue;
            }
            if (($fill['visible'] ?? true) === false) {
                continue;
            }
            $color = $fill['color'] ?? null;
            if (is_array($color)) {
                return self::rgbaToHex($color, (float) ($fill['opacity'] ?? 1));
            }
            if (is_string($color) && $color !== '') {
                return $color;
            }
        }
        return '';
    }

    /**
     * @param array{r?:float|int,g?:float|int,b?:float|int,a?:float|int} $c
     */
    private static function rgbaToHex(array $c, float $opacity = 1): string
    {
        $r = (int) round(($c['r'] ?? 0) * 255);
        $g = (int) round(($c['g'] ?? 0) * 255);
        $b = (int) round(($c['b'] ?? 0) * 255);
        $a = $c['a'] ?? $opacity;
        if ($a < 1) {
            return sprintf('rgba(%d,%d,%d,%.2f)', $r, $g, $b, $a);
        }
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
