<?php
namespace HaruCMS\FieldTypes\Editor;

use HaruCMS\FieldTypes\BaseField;

/**
 * Unified Editor fieldtype (Phase 1)
 * Modes: wysiwyg | markdown | html
 * Stores canonical HTML in hidden input `field_{name}_value`.
 */
class Editor extends BaseField {
    public function getType(): string { return 'editor'; }

    public function getCategory(): string {
        return 'Content & Text';
    }

    public static function getConfig(): array {
        return [
            'label' => 'Editor',
            'description' => 'Unified editor supporting WYSIWYG, Markdown, and HTML modes',
            'options' => [
                'default_mode' => [ 'type' => 'string', 'default' => 'wysiwyg' ],
            ],
        ];
    }

    public function render(): string {
        $value = (string)($this->value ?? '');
        $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5);
        $id = 'field_' . $this->name;
        $required = $this->required ? 'required' : '';

        $toolbar = '<div class="ed-toolbar">'
            .'<div class="ed-modes">'
            .'<button type="button" data-mode="advanced" class="ed-mode active">Advanced</button>'
            .'<button type="button" data-mode="wysiwyg" class="ed-mode">WYSIWYG</button>'
            .'<button type="button" data-mode="markdown" class="ed-mode">Markdown</button>'
            .'<button type="button" data-mode="block" class="ed-mode">Block</button>'
            .'<button type="button" data-mode="html" class="ed-mode">HTML</button>'
            .'</div>'
            .'<div class="ed-actions">'
            .'<button type="button" class="ed-fullscreen" title="Fullscreen">⤢ Fullscreen</button>'
            .'</div>'
        .'</div>'
        .'<div class="ed-toolsbar">'
            .'<div class="ed-tools" data-for="wysiwyg">'
            .'<div class="grp">'
            .'<button type="button" data-cmd="bold"><b>B</b></button>'
            .'<button type="button" data-cmd="italic"><i>I</i></button>'
            .'<button type="button" data-cmd="underline"><u>U</u></button>'
            .'<button type="button" data-cmd="strikeThrough"><s>S</s></button>'
            .'</div>'
            .'<div class="grp">'
            .'<button type="button" data-cmd="formatBlock" data-value="h1">H1</button>'
            .'<button type="button" data-cmd="formatBlock" data-value="h2">H2</button>'
            .'<button type="button" data-cmd="formatBlock" data-value="h3">H3</button>'
            .'</div>'
            .'<div class="grp">'
            .'<button type="button" data-cmd="insertUnorderedList">• List</button>'
            .'<button type="button" data-cmd="insertOrderedList">1. List</button>'
            .'<button type="button" data-cmd="formatBlock" data-value="blockquote">❝</button>'
            .'</div>'
            .'<div class="grp">'
            .'<button type="button" data-cmd="createLink">🔗</button>'
            .'<button type="button" data-cmd="removeFormat">Tx</button>'
            .'</div>'
            .'</div>'
            .'<div class="ed-tools" data-for="markdown" style="display:none">'
            .'<div class="grp">'
            .'<button type="button" data-md="**">Bold</button>'
            .'<button type="button" data-md="*">Italic</button>'
            .'<button type="button" data-md="`">Code</button>'
            .'<button type="button" data-md="[]()">Link</button>'
            .'</div>'
            .'</div>'
        .'</div>';

        // Provide initial value for robust client-side restore
        $dataInitial = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5);
        return <<<HTML
        <div class="editor-field" id="{$id}_wrapper" data-name="{$this->name}" data-initial-value="{$dataInitial}">
            {$toolbar}
            <div class="ed-surface">
                <div id="{$id}_advanced" class="ed-pane ed-advanced editor-surface" style="display:block;" {$required}></div>
                <div id="{$id}_wysiwyg" class="ed-pane ed-wysiwyg" contenteditable="true" style="display:none;" {$required}>$escaped</div>
                <textarea id="{$id}_markdown" class="ed-pane ed-markdown" style="display:none;" {$required}></textarea>
                <div class="ed-block-surface" style="display:none;"></div>
                <div class="code-editor" id="{$id}_html_wrap" style="display:none;">
                  <textarea id="{$id}_html" class="ed-pane ed-html" {$required}>$escaped</textarea>
                  <div class="code-gutter" aria-hidden="true"></div>
                </div>
            </div>
            <input type="hidden" name="{$this->name}" id="{$id}_value" value="$escaped">
        </div>
        HTML;
    }

    public function validate($value): array {
        $errors = [];
        if ($this->required && trim(strip_tags((string)$value)) === '') {
            $errors[] = $this->label . ' is required';
        }
        return $errors;
    }

    public function getJavaScript(): string {
        // Load split modules (modes) first, then the entry script
        $parts = [];
        // Load full Advanced stack (self-contained within fieldtype)
        $paths = [
            __DIR__ . '/core/commands.js',
            __DIR__ . '/core/bus.js',
            __DIR__ . '/core/model.js',
            __DIR__ . '/core/ops.js',
            __DIR__ . '/core/selection.js',
            __DIR__ . '/view/grid.js',
            __DIR__ . '/modes/advanced.js',
            __DIR__ . '/modes/wysiwyg.js',
            __DIR__ . '/modes/markdown.js',
            __DIR__ . '/modes/html.js',
            __DIR__ . '/modes/block.js',
            __DIR__ . '/ui/wysiwygToolbar.js',
            __DIR__ . '/ui/markdownToolbar.js',
            __DIR__ . '/ui/blockToolbar.js',
            __DIR__ . '/fieldtype.js',
        ];
        foreach ($paths as $p) {
            if (file_exists($p)) {
                $parts[] = file_get_contents($p);
            }
        }
        return implode("\n\n", $parts);
    }

    public function getCSS(): string {
        $parts = [];
        $paths = [ __DIR__ . '/view/grid.css', __DIR__ . '/fieldtype.css', __DIR__ . '/advanced.css' ];
        foreach ($paths as $p) { if (file_exists($p)) { $parts[] = file_get_contents($p); } }
        return implode("\n", $parts);
    }

    public function getAdminConfig(): array {
        return [
            'type' => 'editor',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            // Expose lightweight plugin API surface for admin to discover
            'plugins' => [
                'blocks' => [],
                'tools' => []
            ]
        ];
    }

    public function getSchema(): array { return ['type' => 'string', 'required' => $this->required]; }
    public function getDefaultValue() { return ''; }
    public function processValue($value) { return $value; }
    public function processLoadedValue($value) { return $value; }

    public function renderListCell($value, array $context = []): string {
        $text = trim(strip_tags((string)$value));
        if ($text === '') return '<span class="ft-list-empty">–</span>';
        if (mb_strlen($text) > 80) $text = mb_substr($text,0,79).'…';
        return '<span class="ft-list-text">'.htmlspecialchars($text, ENT_QUOTES, 'UTF-8').'</span>';
    }

    public function getListColumnWidth(): string { return '480px'; }
}


