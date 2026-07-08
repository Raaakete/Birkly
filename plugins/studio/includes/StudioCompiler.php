<?php
/**
 * Compile canvas JSON → HTML + Birkly template tags (P22-M6).
 */

require_once __DIR__ . '/StudioProjectRepository.php';

class StudioCompiler
{
    private StudioProjectRepository $repo;

    public function __construct(?StudioProjectRepository $repo = null)
    {
        $this->repo = $repo ?? new StudioProjectRepository();
    }

    public function compilePage(string $projectId, string $pageSlug): string
    {
        $canvas = $this->repo->getPageCanvas($projectId, $pageSlug);
        $project = $this->repo->getProject($projectId);
        if (!$canvas || !$project) {
            return '';
        }

        $title = htmlspecialchars((string) ($canvas['page']['title'] ?? $pageSlug), ENT_QUOTES, 'UTF-8');
        $projectSlug = $project['slug'] ?? $projectId;
        $revision = (int) ($project['revision'] ?? 1);
        $stylesUrl = function_exists('birkly_url')
            ? birkly_url('/site/' . rawurlencode($projectSlug) . '/styles.css?v=' . $revision)
            : '/site/' . rawurlencode($projectSlug) . '/styles.css?v=' . $revision;

        $body = $this->renderSections($canvas['sections'] ?? []);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$title}</title>
  <link rel="stylesheet" href="{$stylesUrl}">
</head>
<body class="studio-page">
{$body}
</body>
</html>
HTML;

        $path = $this->repo->pageHtmlPath($projectId, $pageSlug);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $html);
        $this->writeGeneratedStyles($projectId);
        return $html;
    }

    /**
     * Parse compiled HTML back to approximate canvas (P22-M7).
     */
    public function parseHtmlToCanvas(string $html, array $existing = []): array
    {
        $canvas = $existing;
        $canvas['version'] = $canvas['version'] ?? 1;
        if (!isset($canvas['sections'])) {
            $canvas['sections'] = [];
        }

        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $m)) {
            $body = trim($m[1]);
            if ($body !== '' && empty($canvas['sections'])) {
                $canvas['sections'][] = [
                    'id' => 'section-parsed',
                    'classes' => ['studio-section'],
                    'layout' => ['type' => 'grid', 'columns' => 1, 'gap' => '1rem'],
                    'rows' => [[
                        'id' => 'row-parsed',
                        'columns' => [[
                            'id' => 'col-parsed',
                            'width' => '100%',
                            'elements' => [[
                                'id' => 'el-raw',
                                'type' => 'html',
                                'content' => $body,
                            ]],
                        ]],
                    ]],
                ];
            }
        }

        return $canvas;
    }

    public function buildTokensCss(string $projectId): string
    {
        $path = $this->repo->projectDir($projectId) . '/tokens.json';
        if (!is_file($path)) {
            return '';
        }
        $tokens = json_decode((string) file_get_contents($path), true);
        if (!is_array($tokens)) {
            return '';
        }
        $vars = [];
        foreach ($tokens as $group => $values) {
            if (!is_array($values)) {
                continue;
            }
            foreach ($values as $key => $val) {
                $vars[] = '--studio-' . preg_replace('/[^a-z0-9-]/', '-', strtolower((string) $group . '-' . $key)) . ':' . $val;
            }
        }
        return $vars !== [] ? ':root{' . implode(';', $vars) . '}' : '';
    }

    public function buildResponsiveCss(string $projectId): string
    {
        $breakpoints = [
            'default' => 0,
            'tablet' => 768,
            'desktop' => 1024,
        ];
        $rules = [];
        foreach ($this->repo->listPageSlugs($projectId) as $slug) {
            $canvas = $this->repo->getPageCanvas($projectId, $slug);
            if (!$canvas) {
                continue;
            }
            if (isset($canvas['breakpoints']) && is_array($canvas['breakpoints'])) {
                foreach ($canvas['breakpoints'] as $name => $bp) {
                    if (isset($bp['minWidth'])) {
                        $breakpoints[$name] = (int) $bp['minWidth'];
                    }
                }
            }
            foreach ($canvas['sections'] ?? [] as $section) {
                $sid = preg_replace('/[^a-z0-9_-]/', '', (string) ($section['id'] ?? ''));
                if ($sid === '') {
                    continue;
                }
                $responsive = $section['responsive'] ?? [];
                foreach ($responsive as $bpName => $layout) {
                    $cols = (int) ($layout['columns'] ?? 1);
                    $gap = $layout['gap'] ?? ($section['layout']['gap'] ?? '1rem');
                    $min = $breakpoints[$bpName] ?? 0;
                    $selector = '[data-studio-section="' . $sid . '"]';
                    $rule = $selector . '{display:grid;grid-template-columns:repeat(' . $cols . ',minmax(0,1fr));gap:' . $gap . '}';
                    if ($min > 0) {
                        $rules[] = '@media(min-width:' . $min . 'px){' . $rule . '}';
                    } else {
                        $rules[] = $rule;
                    }
                }
            }
        }
        return implode("\n", $rules);
    }

    public function writeGeneratedStyles(string $projectId): void
    {
        $css = "/* Studio generated (P23) */\n" . $this->buildTokensCss($projectId) . "\n" . $this->buildResponsiveCss($projectId);
        $path = $this->repo->projectDir($projectId) . '/styles/generated.css';
        file_put_contents($path, $css);
    }

    private function renderSections(array $sections): string
    {
        $out = '';
        foreach ($sections as $section) {
            $classes = $this->classAttr($section['classes'] ?? ['studio-section']);
            $sid = preg_replace('/[^a-z0-9_-]/', '', (string) ($section['id'] ?? ''));
            $dataAttr = $sid !== '' ? ' data-studio-section="' . htmlspecialchars($sid, ENT_QUOTES) . '"' : '';
            $layout = $section['responsive']['default'] ?? $section['layout'] ?? [];
            $style = $this->layoutStyle($layout);
            $rowsHtml = '';
            foreach ($section['rows'] ?? [] as $row) {
                $colsHtml = '';
                foreach ($row['columns'] ?? [] as $col) {
                    $elsHtml = '';
                    foreach ($col['elements'] ?? [] as $el) {
                        $elsHtml .= $this->renderElement($el);
                    }
                    $colsHtml .= '<div class="studio-col"' . $this->inlineWidth($col['width'] ?? '') . '>' . $elsHtml . '</div>';
                }
                $rowsHtml .= '<div class="studio-row">' . $colsHtml . '</div>';
            }
            $out .= '<section class="' . $classes . '"' . $dataAttr . ' style="' . htmlspecialchars($style, ENT_QUOTES) . '">' . $rowsHtml . '</section>';
        }
        return $out;
    }

    private function renderElement(array $el): string
    {
        $type = $el['type'] ?? 'text';
        if ($type === 'binding') {
            return $this->renderBinding($el);
        }
        if ($type === 'html' || $type === 'raw') {
            return (string) ($el['content'] ?? '');
        }
        if ($type === 'image') {
            $src = htmlspecialchars((string) ($el['src'] ?? ''), ENT_QUOTES);
            $alt = htmlspecialchars((string) ($el['alt'] ?? ''), ENT_QUOTES);
            return '<img src="' . $src . '" alt="' . $alt . '"' . $this->elementAttrs($el) . '>';
        }
        if ($type === 'button' || $type === 'link') {
            $tag = $type === 'button' ? 'a' : 'a';
            $href = htmlspecialchars((string) ($el['href'] ?? '#'), ENT_QUOTES);
            $label = htmlspecialchars((string) ($el['content'] ?? 'Link'), ENT_QUOTES);
            $class = $type === 'button' ? ' studio-btn' : '';
            return '<' . $tag . ' href="' . $href . '" class="studio-link' . $class . '"' . $this->elementAttrs($el) . '>' . $label . '</' . $tag . '>';
        }
        if ($type === 'spacer') {
            $h = (int) ($el['height'] ?? 24);
            return '<div class="studio-spacer" style="height:' . $h . 'px"></div>';
        }
        if ($type === 'divider') {
            return '<hr class="studio-divider"' . $this->elementAttrs($el) . '>';
        }

        $tag = preg_replace('/[^a-z0-9]/', '', strtolower((string) ($el['tag'] ?? 'p'))) ?: 'p';
        $content = htmlspecialchars((string) ($el['content'] ?? ''), ENT_QUOTES);
        return '<' . $tag . $this->elementAttrs($el) . '>' . $content . '</' . $tag . '>';
    }

    private function renderBinding(array $el): string
    {
        $bindingType = $el['bindingType'] ?? 'loop';
        $collection = preg_replace('/[^a-z0-9_-]/', '', (string) ($el['collection'] ?? ''));
        $template = (string) ($el['itemTemplate'] ?? '<div>{title}</div>');

        if ($bindingType === 'single') {
            $slug = preg_replace('/[^a-z0-9_-]/', '', (string) ($el['slug'] ?? ''));
            return "{from '{$collection}' get entry '{$slug}'}\n" . $template . "\n{end}";
        }
        if ($bindingType === 'site_element') {
            $slug = preg_replace('/[^a-z0-9_-]/', '', (string) ($el['slug'] ?? ''));
            return "{from 'site_elements' get entry '{$slug}'}\n" . $template . "\n{end}";
        }
        if ($bindingType === 'filtered') {
            $field = preg_replace('/[^a-z0-9_-]/', '', (string) ($el['filterField'] ?? 'status'));
            $value = preg_replace('/[^a-z0-9_-]/', '', (string) ($el['filterValue'] ?? 'published'));
            return "{for each entry in '{$collection}' with {$field} = {$value}}\n" . $template . "\n{end}";
        }
        if ($bindingType === 'template_function') {
            $name = preg_replace('/[^a-z0-9_-]/', '', (string) ($el['functionName'] ?? ''));
            $args = (string) ($el['functionArgs'] ?? '');
            return '{' . $name . ($args !== '' ? ' ' . $args : '') . '}';
        }

        return "{for each entry in '{$collection}'}\n" . $template . "\n{end}";
    }

    private function elementAttrs(array $el): string
    {
        $parts = [];
        $classes = $el['classes'] ?? [];
        if (is_array($classes) && $classes !== []) {
            $parts[] = 'class="' . htmlspecialchars(implode(' ', $classes), ENT_QUOTES) . '"';
        }
        $style = $this->stylesToCss($el['styles'] ?? []);
        if ($style !== '') {
            $parts[] = 'style="' . htmlspecialchars($style, ENT_QUOTES) . '"';
        }
        return $parts !== [] ? ' ' . implode(' ', $parts) : '';
    }

    private function stylesToCss(array $styles): string
    {
        $map = [
            'textAlign' => 'text-align',
            'fontSize' => 'font-size',
            'fontWeight' => 'font-weight',
            'color' => 'color',
            'backgroundColor' => 'background-color',
            'padding' => 'padding',
            'margin' => 'margin',
            'border' => 'border',
            'borderRadius' => 'border-radius',
            'display' => 'display',
        ];
        $rules = [];
        foreach ($styles as $key => $val) {
            if ($val === '' || $val === null) {
                continue;
            }
            $prop = $map[$key] ?? strtolower(preg_replace('/([A-Z])/', '-$1', (string) $key));
            $rules[] = ltrim($prop, '-') . ':' . $val;
        }
        return implode(';', $rules);
    }

    private function layoutStyle(array $layout): string
    {
        $type = $layout['type'] ?? 'grid';
        $gap = $layout['gap'] ?? '1rem';
        $columns = (int) ($layout['columns'] ?? 1);
        if ($type === 'flex') {
            $dir = $layout['direction'] ?? 'row';
            return "display:flex;flex-direction:{$dir};gap:{$gap};flex-wrap:wrap";
        }
        return "display:grid;grid-template-columns:repeat({$columns}, minmax(0, 1fr));gap:{$gap}";
    }

    private function inlineWidth(string $width): string
    {
        if ($width === '' || $width === '100%') {
            return '';
        }
        return ' style="flex:0 0 ' . htmlspecialchars($width, ENT_QUOTES) . '"';
    }

    private function classAttr(array $classes): string
    {
        $list = array_filter(array_map('strval', $classes));
        return htmlspecialchars(implode(' ', $list), ENT_QUOTES);
    }
}
