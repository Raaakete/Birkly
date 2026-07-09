<?php
/**
 * Studio creative suite compiler (P26) — animations, popups, funnel stepper.
 */

class StudioCreative
{
    public static function creativeFromCanvas(array $canvas): array
    {
        $creative = $canvas['creative'] ?? [];
        return is_array($creative) ? $creative : [];
    }

    /**
     * @param array<int, array<string, mixed>> $animations
     */
    public static function buildAnimationCss(array $animations): string
    {
        if ($animations === []) {
            return '';
        }
        $css = "/* Studio creative animations (P26) */\n";
        foreach ($animations as $anim) {
            if (!is_array($anim)) {
                continue;
            }
            $name = self::safeAnimName((string) ($anim['name'] ?? $anim['id'] ?? ''));
            if ($name === '') {
                continue;
            }
            $frames = $anim['keyframes'] ?? [];
            if (!is_array($frames) || $frames === []) {
                $frames = [
                    '0%' => ['opacity' => '0', 'transform' => 'translateY(16px)'],
                    '100%' => ['opacity' => '1', 'transform' => 'translateY(0)'],
                ];
            }
            $css .= '@keyframes ' . $name . "{\n";
            foreach ($frames as $pct => $props) {
                if (!is_array($props)) {
                    continue;
                }
                $rules = [];
                foreach ($props as $prop => $val) {
                    $rules[] = self::cssProp((string) $prop) . ':' . (string) $val;
                }
                $css .= '  ' . self::normalizeKeyframePct((string) $pct) . '{' . implode(';', $rules) . "}\n";
            }
            $css .= "}\n";

            $targetId = preg_replace('/[^a-z0-9_-]/', '', (string) ($anim['targetId'] ?? ''));
            if ($targetId === '') {
                continue;
            }
            $duration = max(100, (int) ($anim['duration'] ?? 600));
            $delay = max(0, (int) ($anim['delay'] ?? 0));
            $easing = self::safeEasing((string) ($anim['easing'] ?? 'ease-out'));
            $trigger = (string) ($anim['trigger'] ?? 'scroll');
            $selector = '[data-studio-el="' . $targetId . '"]';

            if ($trigger === 'load') {
                $css .= $selector . '{animation:' . $name . ' ' . $duration . 'ms ' . $easing . ' ' . $delay . 'ms both;}' . "\n";
            } else {
                $css .= $selector . '.studio-anim-ready{animation:' . $name . ' ' . $duration . 'ms ' . $easing . ' ' . $delay . 'ms both;}' . "\n";
                $css .= $selector . '.studio-anim-pending{opacity:0;}' . "\n";
            }
        }
        return $css;
    }

    /**
     * @param array<int, array<string, mixed>> $popups
     */
    public static function buildPopupsHtml(array $popups): string
    {
        if ($popups === []) {
            return '';
        }
        $html = '';
        foreach ($popups as $popup) {
            if (!is_array($popup)) {
                continue;
            }
            $id = preg_replace('/[^a-z0-9_-]/', '', (string) ($popup['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $title = htmlspecialchars((string) ($popup['title'] ?? 'Dialog'), ENT_QUOTES, 'UTF-8');
            $body = (string) ($popup['body'] ?? $popup['content'] ?? '');
            $aria = htmlspecialchars((string) ($popup['ariaLabel'] ?? $popup['title'] ?? 'Dialog'), ENT_QUOTES, 'UTF-8');
            $width = self::safeCssSize((string) ($popup['width'] ?? 'min(480px,92vw)'));
            $trigger = (string) ($popup['trigger'] ?? 'click');
            $clickTarget = preg_replace('/[^a-z0-9_-]/', '', (string) ($popup['clickTargetId'] ?? ''));
            $delayMs = max(0, (int) ($popup['delayMs'] ?? 0));
            $exitIntent = !empty($popup['exitIntent']) || $trigger === 'exit_intent';

            $attrs = ' data-studio-popup="' . $id . '"'
                . ' data-popup-trigger="' . htmlspecialchars($trigger, ENT_QUOTES) . '"'
                . ' data-popup-delay="' . $delayMs . '"'
                . ' data-popup-exit="' . ($exitIntent ? '1' : '0') . '"';
            if ($clickTarget !== '') {
                $attrs .= ' data-popup-click-target="' . $clickTarget . '"';
            }

            $html .= '<div class="studio-popup-root" hidden' . $attrs . ' role="dialog" aria-modal="true" aria-label="' . $aria . '">'
                . '<div class="studio-popup-backdrop" data-popup-close tabindex="-1"></div>'
                . '<div class="studio-popup-panel" style="width:' . htmlspecialchars($width, ENT_QUOTES) . '">'
                . '<button type="button" class="studio-popup-close" data-popup-close aria-label="Close dialog">×</button>'
                . '<h2 class="studio-popup-title">' . $title . '</h2>'
                . '<div class="studio-popup-body">' . $body . '</div>'
                . '</div></div>';
        }
        return $html;
    }

    public static function buildFunnelHtml(?array $funnel, string $projectSlug, string $currentPage): string
    {
        if (!$funnel || empty($funnel['enabled'])) {
            return '';
        }
        $steps = $funnel['steps'] ?? [];
        if (!is_array($steps) || count($steps) < 2) {
            return '';
        }
        $currentIdx = 0;
        $items = '';
        foreach ($steps as $i => $step) {
            if (!is_array($step)) {
                continue;
            }
            $slug = (string) ($step['slug'] ?? $step['page'] ?? '');
            $label = htmlspecialchars((string) ($step['label'] ?? $slug), ENT_QUOTES, 'UTF-8');
            if ($slug === $currentPage) {
                $currentIdx = $i;
            }
            $href = self::funnelPageHref($projectSlug, $slug);
            $state = $i < $currentIdx ? 'done' : ($i === $currentIdx ? 'active' : 'upcoming');
            $items .= '<li class="studio-funnel-step studio-funnel-step--' . $state . '">'
                . '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '" class="studio-funnel-link">'
                . '<span class="studio-funnel-num">' . ($i + 1) . '</span>'
                . '<span class="studio-funnel-label">' . $label . '</span>'
                . '</a></li>';
        }
        if ($items === '') {
            return '';
        }
        $title = htmlspecialchars((string) ($funnel['title'] ?? 'Progress'), ENT_QUOTES, 'UTF-8');
        return '<nav class="studio-funnel" aria-label="' . $title . '"><ol class="studio-funnel-steps">' . $items . '</ol></nav>';
    }

    /**
     * @return array{css:string,config:array<string,mixed>}
     */
    public static function buildRuntimeConfig(array $canvas, ?array $funnel, string $projectSlug, string $pageSlug): array
    {
        $creative = self::creativeFromCanvas($canvas);
        $animations = is_array($creative['animations'] ?? null) ? $creative['animations'] : [];
        $popups = is_array($creative['popups'] ?? null) ? $creative['popups'] : [];

        $animConfig = [];
        foreach ($animations as $anim) {
            if (!is_array($anim)) {
                continue;
            }
            $targetId = preg_replace('/[^a-z0-9_-]/', '', (string) ($anim['targetId'] ?? ''));
            if ($targetId === '') {
                continue;
            }
            $animConfig[] = [
                'targetId' => $targetId,
                'trigger' => (string) ($anim['trigger'] ?? 'scroll'),
                'scrollOffset' => (float) ($anim['scrollOffset'] ?? 0.15),
                'clickTargetId' => preg_replace('/[^a-z0-9_-]/', '', (string) ($anim['clickTargetId'] ?? '')),
            ];
        }

        $popupConfig = [];
        foreach ($popups as $popup) {
            if (!is_array($popup)) {
                continue;
            }
            $id = preg_replace('/[^a-z0-9_-]/', '', (string) ($popup['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $popupConfig[] = [
                'id' => $id,
                'trigger' => (string) ($popup['trigger'] ?? 'click'),
                'clickTargetId' => preg_replace('/[^a-z0-9_-]/', '', (string) ($popup['clickTargetId'] ?? '')),
                'delayMs' => max(0, (int) ($popup['delayMs'] ?? 0)),
                'exitIntent' => !empty($popup['exitIntent']) || ($popup['trigger'] ?? '') === 'exit_intent',
            ];
        }

        return [
            'css' => self::buildAnimationCss($animations) . self::popupBaseCss(),
            'config' => [
                'animations' => $animConfig,
                'popups' => $popupConfig,
                'funnel' => $funnel && !empty($funnel['enabled']) ? [
                    'currentPage' => $pageSlug,
                    'steps' => array_values(array_map(static function ($s) {
                        return is_array($s) ? [
                            'slug' => (string) ($s['slug'] ?? $s['page'] ?? ''),
                            'label' => (string) ($s['label'] ?? ''),
                        ] : [];
                    }, $funnel['steps'] ?? [])),
                ] : null,
            ],
        ];
    }

    public static function readFunnel(string $projectDir): ?array
    {
        $path = rtrim($projectDir, '/') . '/funnel.json';
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    public static function writeFunnel(string $projectDir, array $funnel): void
    {
        $path = rtrim($projectDir, '/') . '/funnel.json';
        file_put_contents($path, json_encode($funnel, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    public static function runtimeScriptUrl(): string
    {
        $path = '/plugins/studio/assets/studio-creative-runtime.js';
        return function_exists('birkly_url') ? birkly_url($path) : $path;
    }

    private static function funnelPageHref(string $projectSlug, string $pageSlug): string
    {
        $pageSlug = $pageSlug !== '' ? $pageSlug : 'index';
        if ($pageSlug === 'index') {
            $path = '/site/' . rawurlencode($projectSlug) . '/';
        } else {
            $path = '/site/' . rawurlencode($projectSlug) . '/' . rawurlencode($pageSlug);
        }
        return function_exists('birkly_url') ? birkly_url($path) : $path;
    }

    private static function safeAnimName(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $name) ?? '';
        if ($name === '' || is_numeric($name[0])) {
            $name = 'anim-' . $name;
        }
        return $name;
    }

    private static function safeEasing(string $easing): string
    {
        $allowed = ['ease', 'ease-in', 'ease-out', 'ease-in-out', 'linear'];
        return in_array($easing, $allowed, true) ? $easing : 'ease-out';
    }

    private static function safeCssSize(string $size): string
    {
        if (preg_match('/^(?:\d+(?:\.\d+)?(?:px|rem|em|vw|vh|%)|min\([^)]+\)|max\([^)]+\))$/', $size)) {
            return $size;
        }
        return 'min(480px,92vw)';
    }

    private static function cssProp(string $prop): string
    {
        if (str_contains($prop, '-')) {
            return $prop;
        }
        return strtolower(preg_replace('/([A-Z])/', '-$1', $prop) ?? $prop);
    }

    private static function normalizeKeyframePct(string $pct): string
    {
        if (preg_match('/^\d+%$/', $pct)) {
            return $pct;
        }
        if (is_numeric($pct)) {
            return ((int) $pct) . '%';
        }
        if ($pct === 'from') {
            return '0%';
        }
        if ($pct === 'to') {
            return '100%';
        }
        return $pct;
    }

    private static function popupBaseCss(): string
    {
        return <<<'CSS'

.studio-popup-root{position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center}
.studio-popup-root[hidden]{display:none!important}
.studio-popup-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.45)}
.studio-popup-panel{position:relative;background:#fff;border-radius:12px;padding:1.5rem;max-height:90vh;overflow:auto;box-shadow:0 16px 48px rgba(0,0,0,.2)}
.studio-popup-close{position:absolute;top:.5rem;right:.75rem;border:0;background:transparent;font-size:1.5rem;line-height:1;cursor:pointer}
.studio-popup-title{margin:0 2rem .75rem 0;font-size:1.25rem}
.studio-funnel{margin:0 0 1.5rem;padding:1rem;background:#f6f8fa;border-radius:8px}
.studio-funnel-steps{display:flex;gap:.5rem;list-style:none;margin:0;padding:0;flex-wrap:wrap}
.studio-funnel-step{flex:1;min-width:5rem}
.studio-funnel-link{display:flex;flex-direction:column;align-items:center;gap:.25rem;text-decoration:none;color:inherit;padding:.5rem;border-radius:6px}
.studio-funnel-step--active .studio-funnel-link{background:#e7f1ff;font-weight:600}
.studio-funnel-step--done .studio-funnel-link{opacity:.85}
.studio-funnel-num{width:1.75rem;height:1.75rem;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#dee2e6;font-size:.85rem}
.studio-funnel-step--active .studio-funnel-num{background:#0d6efd;color:#fff}
.studio-funnel-step--done .studio-funnel-num{background:#198754;color:#fff}

CSS;
    }
}
