<?php
namespace HaruCMS\FieldTypes\Textarea;

use HaruCMS\FieldTypes\BaseField;

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

    public function render(): string {
        $required = $this->required ? 'required' : '';
        $placeholder = $this->placeholder ? "placeholder=\"{$this->placeholder}\"" : '';
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
} 