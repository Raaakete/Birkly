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

    public function getIcon(): string {
        return 'news';
    }

    public function getDescription(): string {
        return 'Formatted text written in Markdown';
    }

    public function render(): string {
        $required = $this->required ? 'required' : '';
        $rows = max(3, $this->rows);
        $placeholder = htmlspecialchars($this->placeholder, ENT_QUOTES, 'UTF-8');
        $value = htmlspecialchars((string)($this->value ?? ''), ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <div class="markdown-field" data-markdown-field>
            <div class="markdown-field-tabs">
                <button type="button" class="markdown-tab is-active" data-markdown-tab="write" aria-pressed="true">Write</button>
                <button type="button" class="markdown-tab" data-markdown-tab="preview" aria-pressed="false">Preview</button>
            </div>
            <textarea
                name="{$this->name}"
                id="field_{$this->name}"
                class="input is-mono markdown-field-input"
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
        /* Write | Preview is a segmented control; the preview is a well. */
        .markdown-field {
            display: grid;
            gap: var(--s-2);
        }
        .markdown-field-tabs {
            display: inline-flex;
            justify-self: start;
            gap: 2px;
            padding: 2px;
            border-radius: var(--r);
            background: var(--surface-2);
        }
        .markdown-tab {
            height: 24px;
            padding: 0 var(--s-2);
            border: 0;
            border-radius: var(--r-inset);
            background: none;
            color: var(--text-2);
            font: var(--weight-medium) var(--size-small) / 1 var(--font-sans);
            cursor: pointer;
        }
        .markdown-tab.is-active {
            background: var(--surface);
            color: var(--text);
        }
        .markdown-field-input,
        .markdown-field-preview {
            min-height: 220px;
        }
        .markdown-field-preview {
            padding: var(--s-3);
            border-radius: var(--r);
            background: var(--surface-2);
            overflow-wrap: anywhere;
        }
        .markdown-field-preview h1,
        .markdown-field-preview h2,
        .markdown-field-preview h3,
        .markdown-field-preview h4,
        .markdown-field-preview h5,
        .markdown-field-preview h6,
        .markdown-field-preview p,
        .markdown-field-preview ul,
        .markdown-field-preview ol,
        .markdown-field-preview blockquote {
            margin: 0 0 var(--s-2);
        }
        .markdown-field-preview code {
            padding: 1px 4px;
            border-radius: var(--r-inset);
            background: var(--surface);
            font: var(--weight-regular) var(--size-mono) / var(--lh-mono) var(--font-mono);
        }
        .markdown-field-preview blockquote {
            padding-inline-start: var(--s-3);
            border-inline-start: 2px solid var(--line-strong);
            color: var(--text-2);
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

    public function outputsTrustedHtml(): bool
    {
        return true;
    }

    public function formatValueForTemplate($value, array $fieldConfig = [], array $context = []): string
    {
        require_once __DIR__ . '/markdown_render.php';
        return birkly_markdown_render((string) $value);
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
