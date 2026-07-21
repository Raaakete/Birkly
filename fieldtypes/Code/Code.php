<?php
namespace Birkly\FieldTypes\Code;

use Birkly\FieldTypes\BaseField;

class Code extends BaseField {
    protected $mode;
    protected $rows;

    public function __construct(array $config) {
        parent::__construct($config);
        $this->mode = strtolower((string)($config['mode'] ?? 'text'));
        if (!in_array($this->mode, ['text', 'json'], true)) {
            $this->mode = 'text';
        }
        $this->rows = (int)($config['rows'] ?? 12);
    }

    public function getType(): string {
        return 'code';
    }

    public function getCategory(): string {
        return 'Content & Text';
    }

    public function render(): string {
        $required = $this->required ? 'required' : '';
        $rows = max(4, $this->rows);
        $value = htmlspecialchars((string)($this->value ?? ''), ENT_QUOTES, 'UTF-8');
        $mode = htmlspecialchars($this->mode, ENT_QUOTES, 'UTF-8');
        $status = $this->mode === 'json' ? 'JSON will be validated on blur.' : '';

        return <<<HTML
        <div class="code-field" data-code-field data-code-mode="{$mode}">
            <textarea
                name="{$this->name}"
                id="field_{$this->name}"
                class="input code-field-input"
                rows="{$rows}"
                spellcheck="false"
                {$required}
            >{$value}</textarea>
            <div class="code-field-status">{$status}</div>
        </div>
        HTML;
    }

    public function validate($value): array {
        $errors = [];
        $value = (string)$value;

        if ($this->required && trim($value) === '') {
            $errors[] = "{$this->label} is required";
            return $errors;
        }

        if ($this->mode === 'json' && trim($value) !== '') {
            json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = "{$this->label} must contain valid JSON";
            }
        }

        return $errors;
    }

    public function getJavaScript(): string {
        return file_get_contents(__DIR__ . '/fieldtype.js');
    }

    public function getCSS(): string {
        return <<<'CSS'
        .code-field {
            display: grid;
            gap: var(--space-2, 0.5rem);
        }

        .code-field-input {
            min-height: 240px;
            font-family: var(--font-family-mono, SFMono-Regular, Consolas, monospace);
            line-height: 1.6;
            tab-size: 2;
        }

        .code-field-status {
            font-size: 0.875rem;
            color: var(--text-secondary, #6b7280);
        }

        .code-field-status.is-valid {
            color: var(--success-600, #16a34a);
        }

        .code-field-status.is-invalid {
            color: var(--error-600, #dc2626);
        }
        CSS;
    }

    public function getAdminConfig(): array {
        return [
            'type' => 'code',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            'mode' => $this->mode,
            'rows' => $this->rows,
        ];
    }

    public function getSchema(): array {
        return [
            'type' => 'string',
            'format' => 'code',
            'mode' => $this->mode,
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
        return parent::renderListCell((string)$value, ['truncate' => $context['truncate'] ?? 48]);
    }

    public function getTableMinWidth(): string {
        return '220px';
    }

    public function getConfigUI($fieldIndex, $existingData = []): string {
        $mode = strtolower((string)($existingData['mode'] ?? 'text'));
        $rows = htmlspecialchars((string)($existingData['rows'] ?? 12), ENT_QUOTES, 'UTF-8');
        $textSelected = $mode === 'json' ? '' : 'selected';
        $jsonSelected = $mode === 'json' ? 'selected' : '';

        return <<<HTML
            <div class="field-config-section">
                <label>Mode</label>
                <select name="fields[{$fieldIndex}][mode]" class="input">
                    <option value="text" {$textSelected}>Plain Text</option>
                    <option value="json" {$jsonSelected}>JSON</option>
                </select>
            </div>
            <div class="field-config-section">
                <label>Rows</label>
                <input type="number" name="fields[{$fieldIndex}][rows]" class="input" value="{$rows}" min="4" max="40">
            </div>
        HTML;
    }
}
