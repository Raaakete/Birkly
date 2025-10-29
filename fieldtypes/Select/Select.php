<?php
namespace HaruCMS\FieldTypes\Select;

use HaruCMS\FieldTypes\BaseField;

class Select extends BaseField {
    protected $options;

    public function __construct(array $config) {
        parent::__construct($config);
        $this->options = $config['options'] ?? [];
    }

    public function getType(): string {
        return 'select';
    }

    public function render(): string {
        $required = $this->required ? 'required' : '';
        $optionsHtml = '<option value="">Select ' . htmlspecialchars($this->label) . '</option>';
        foreach ($this->options as $opt) {
            $selected = ($this->value == $opt['value']) ? 'selected' : '';
            $optionsHtml .= '<option value="' . htmlspecialchars($opt['value']) . '" ' . $selected . '>' . htmlspecialchars($opt['label']) . '</option>';
        }
        return <<<HTML
        <select name="{$this->name}" id="field_{$this->name}" class="input" {$required}>
            {$optionsHtml}
        </select>
        HTML;
    }

    public function validate($value): array {
        $errors = [];
        if ($this->required && empty($value)) {
            $errors[] = "{$this->label} is required";
        }
        if ($value && !in_array($value, array_column($this->options, 'value'))) {
            $errors[] = "Invalid value for {$this->label}";
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
            'type' => 'select',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            'options' => $this->options
        ];
    }

    public function getSchema(): array {
        return [
            'type' => 'string',
            'required' => $this->required,
            'options' => $this->options
        ];
    }

    public function getDefaultValue() {
        return '';
    }

    public function processValue($value) {
        return $value;
    }

    public function processLoadedValue($value) {
        return $value;
    }
} 