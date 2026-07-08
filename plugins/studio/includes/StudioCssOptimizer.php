<?php
/**
 * Export CSS optimizer (P23-M15).
 */

class StudioCssOptimizer
{
    public static function minify(string $css): string
    {
        $css = preg_replace('!/\*.*?\*/!s', '', $css) ?? $css;
        $css = preg_replace('/\s+/', ' ', $css) ?? $css;
        $css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css) ?? $css;
        return trim($css);
    }
}
