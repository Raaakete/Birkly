<?php
namespace HaruCMS\FieldTypes\Date;

use HaruCMS\FieldTypes\BaseField;

class Date extends BaseField {
    public function getType(): string {
        return 'date';
    }

    public function render(): string {
        $required = $this->required ? 'required' : '';
        $value = htmlspecialchars($this->value ?? '');
        return <<<HTML
        <input type="date" name="{$this->name}" id="field_{$this->name}" class="input" value="{$value}" {$required}>
        HTML;
    }

    public function validate($value): array {
        $errors = [];
        if ($this->required && empty($value)) {
            $errors[] = "{$this->label} is required";
        }
        // Optionally: validate date format
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
            'type' => 'date',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group
        ];
    }

    public function getSchema(): array {
        return [
            'type' => 'date',
            'required' => $this->required
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