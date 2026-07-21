<?php
namespace Birkly\FieldTypes\Markdown;

use Birkly\FieldTypes\BaseField;

class Markdown extends BaseField {
    protected $rows;
    protected $placeholder;

    public function __construct(array $config) {
        parent::__construct($config);
        $this->rows = (int)($config['rows'] ?? 10);
        $this->placeholder = $config['placeholder'] ?? 'Write markdown...';
    }

    public function getType(): string {
        return 'markdown';
    }

    public function getCategory(): string {
        return 'Content & Text';
    }

    public function render(): string {
        $required = $this->required ? 'required' : '';
        $rows = max(3, $this->rows);
        $placeholder = htmlspecialchars($this->placeholder, ENT_QUOTES, 'UTF-8');
        $value = htmlspecialchars((string)($this->value ?? ''), ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <div class="markdown-field" data-markdown-field>
            <div class="markdown-field-tabs">
                <button type="button" class="btn btn-secondary btn-sm is-active" data-markdown-tab="write">Write</button>
                <button type="button" class="btn btn-secondary btn-sm" data-markdown-tab="preview">Preview</button>
            </div>
            <textarea
                name="{$this->name}"
                id="field_{$this->name}"
                class="input markdown-field-input"
                rows="{$rows}"
                {$required}
                placeholder="{$placeholder}"
            >{$value}</textarea>
            <div class="markdown-field-preview" hidden></div>
        </div>
        HTML;
    }

    public function validate($value): array {
        $errors = [];

        if ($this->required && trim((string)$value) === '') {
            $errors[] = "{$this->label} is required";
        }

        return $errors;
    }

    public function getJavaScript(): string {
        return file_get_contents(__DIR__ . '/fieldtype.js');
    }

    public function getCSS(): string {
        return <<<'CSS'
        .markdown-field {
            display: grid;
            gap: var(--space-2, 0.5rem);
        }

        .markdown-field-tabs {
            display: flex;
            gap: var(--space-2, 0.5rem);
        }

        .markdown-field-tabs .is-active {
            background: var(--primary-50, #eff6ff);
            color: var(--primary-700, #1d4ed8);
        }

        .markdown-field-input,
        .markdown-field-preview {
            min-height: 220px;
        }

        .markdown-field-input {
            font-family: var(--font-family-mono, SFMono-Regular, Consolas, monospace);
            line-height: 1.6;
        }

        .markdown-field-preview {
            border: 1px solid var(--border-medium, #e5e7eb);
            border-radius: var(--radius-sm, 0.375rem);
            background: var(--bg-primary, #ffffff);
            padding: var(--space-3, 0.75rem);
            overflow-wrap: anywhere;
        }

        .markdown-field-preview h1,
        .markdown-field-preview h2,
        .markdown-field-preview h3,
        .markdown-field-preview h4,
        .markdown-field-preview h5,
        .markdown-field-preview h6 {
            margin: 0 0 var(--space-2, 0.5rem);
        }

        .markdown-field-preview p,
        .markdown-field-preview ul,
        .markdown-field-preview ol,
        .markdown-field-preview blockquote {
            margin: 0 0 var(--space-2, 0.5rem);
        }

        .markdown-field-preview code {
            font-family: var(--font-family-mono, SFMono-Regular, Consolas, monospace);
            background: var(--bg-secondary, #f5f5f5);
            padding: 0.1rem 0.35rem;
            border-radius: var(--radius-xs, 0.25rem);
        }

        .markdown-field-preview blockquote {
            border-left: 3px solid var(--border-medium, #d1d5db);
            padding-left: var(--space-3, 0.75rem);
            color: var(--text-secondary, #4b5563);
        }
        CSS;
    }

    public function getAdminConfig(): array {
        return [
            'type' => 'markdown',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            'rows' => $this->rows,
            'placeholder' => $this->placeholder,
        ];
    }

    public function getSchema(): array {
        return [
            'type' => 'string',
            'format' => 'markdown',
            'required' => $this->required,
        ];
    }

    public function getDefaultValue() {
        return '';
    }

    public function processValue($value) {
        return rtrim(str_replace(["\r\n", "\r"], "\n", (string)$value));
    }

    public function processLoadedValue($value) {
        return (string)$value;
    }

    public function renderListCell($value, array $context = []): string {
        $plain = trim(strip_tags((string)$value));
        return parent::renderListCell($plain, ['truncate' => $context['truncate'] ?? 60]);
    }

    public function getTableMinWidth(): string {
        return '220px';
    }

    public function getConfigUI($fieldIndex, $existingData = []): string {
        $placeholder = htmlspecialchars($existingData['placeholder'] ?? 'Write markdown...', ENT_QUOTES, 'UTF-8');
        $rows = htmlspecialchars((string)($existingData['rows'] ?? 10), ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <div class="field-config-section">
                <label>Placeholder</label>
                <input type="text" name="fields[{$fieldIndex}][placeholder]" class="input" value="{$placeholder}" placeholder="Write markdown...">
            </div>
            <div class="field-config-section">
                <label>Rows</label>
                <input type="number" name="fields[{$fieldIndex}][rows]" class="input" value="{$rows}" min="3" max="30">
            </div>
        HTML;
    }
}
