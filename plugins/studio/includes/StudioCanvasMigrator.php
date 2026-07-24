<?php
/**
 * Canvas v1 → v2 migration (P23-M14).
 */

class StudioCanvasMigrator
{
    public static function upgrade(array $canvas): array
    {
        $version = (int) ($canvas['version'] ?? 1);
        if ($version >= 2) {
            return $canvas;
        }
        $canvas['version'] = 2;
        $canvas['breakpoint'] = $canvas['breakpoint'] ?? 'default';
        $canvas['breakpoints'] = $canvas['breakpoints'] ?? [
            'default' => ['minWidth' => 0],
            'tablet' => ['minWidth' => 768],
            'desktop' => ['minWidth' => 1024],
        ];
        $canvas['designTokens'] = $canvas['designTokens'] ?? [
            'colors' => ['primary' => '#0d6efd', 'text' => '#1a1a1a'],
            'spacing' => ['sm' => '0.5rem', 'md' => '1rem', 'lg' => '2rem'],
        ];
        if (!isset($canvas['customClasses'])) {
            $canvas['customClasses'] = [];
        }
        foreach ($canvas['sections'] ?? [] as $si => $section) {
            if (!isset($section['responsive'])) {
                $cols = (int) ($section['layout']['columns'] ?? 1);
                $canvas['sections'][$si]['responsive'] = [
                    'default' => ['columns' => 1],
                    'tablet' => ['columns' => min(2, $cols)],
                    'desktop' => ['columns' => $cols],
                ];
            }
        }
        return $canvas;
    }
}
