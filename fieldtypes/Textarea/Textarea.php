<?php
namespace Birkly\FieldTypes\Textarea;

use Birkly\FieldTypes\BaseField;

class Textarea extends BaseField {
    protected $rows;
    protected $placeholder;

    public function __construct(array $config) {
        parent::__construct($config);
        $this->rows = $config['rows'] ?? 4;
        $this->placeholder = $config['placeholder'] ?? '';
    }

    public function getType(): string {
        return 'textarea';
    }

    public function getCategory(): string {
        return 'Content & Text';
    }

    public function getIcon(): string {
        return 'align-left';
    }

    public function getDescription(): string {
        return 'Plain paragraphs without formatting';
    }

    public function render(): string {
        $required = $this->required ? 'required' : '';
        $placeholderText = $this->placeholder ?: 'Enter ' . strtolower($this->label ?: 'text');
        $placeholder = "placeholder=\"" . htmlspecialchars($placeholderText) . "\"";
        $rows = (int)$this->rows;
        $value = htmlspecialchars($this->value ?? '');
        return <<<HTML
        <textarea name="{$this->name}" id="field_{$this->name}" class="input" rows="{$rows}" {$required} {$placeholder}>{$value}</textarea>
        HTML;
    }

    public function validate($value): array {
        $errors = [];
        if ($this->required && empty($value)) {
            $errors[] = "{$this->label} is required";
        }
        return $errors;
    }

    public function getJavaScript(): string {
        return '';
    }

    public function getCSS(): string {
        return '';
    }

    public function getAdminConfig(): array {
        return [
            'type' => 'textarea',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            'rows' => $this->rows,
            'placeholder' => $this->placeholder
        ];
    }

    public function getSchema(): array {
        return [
            'type' => 'string',
            'required' => $this->required,
        ];
    }

    public function getDefaultValue() {
        return '';
    }

    public function processValue($value) {
        return trim($value);
    }

    public function processLoadedValue($value) {
        return $value;
    }

    public function renderListCell($value, array $context = []): string {
        // Textarea values can be longer, use shorter truncation
        $context['truncate'] = $context['truncate'] ?? 60;
        return parent::renderListCell($value, $context);
    }

    public function getTableMinWidth(): string {
        return '200px'; // Textarea fields need moderate space for preview
    }

    public function getConfigUI($fieldIndex, $existingData = []): string {
        $placeholder = htmlspecialchars($existingData['placeholder'] ?? '');
        $rows = htmlspecialchars($existingData['rows'] ?? 4);
        
        return <<<HTML
            <div class="field-config-section">
                <label>Placeholder</label>
                <input type="text" name="fields[{$fieldIndex}][placeholder]" class="input" value="{$placeholder}" placeholder="Enter placeholder text">
            </div>
            <div class="field-config-section">
                <label>Rows</label>
                <input type="number" name="fields[{$fieldIndex}][rows]" class="input" value="{$rows}" min="1" max="20">
            </div>
        HTML;
    }
} 