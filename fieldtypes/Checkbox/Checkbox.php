<?php
namespace HaruCMS\FieldTypes\Checkbox;

use HaruCMS\FieldTypes\BaseField;

class Checkbox extends BaseField {
    public function getType(): string {
        return 'checkbox';
    }

    public function render(): string {
        $checked = $this->value ? 'checked' : '';
        return <<<HTML
        <input type="checkbox" name="{$this->name}" id="field_{$this->name}" value="1" {$checked}>
        HTML;
    }

    public function validate($value): array {
        $errors = [];
        if ($this->required && !$value) {
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
            'type' => 'checkbox',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group
        ];
    }

    public function getSchema(): array {
        return [
            'type' => 'boolean',
            'required' => $this->required
        ];
    }

    public function getDefaultValue() {
        return false;
    }

    public function processValue($value) {
        return (bool)$value;
    }

    public function processLoadedValue($value) {
        return (bool)$value;
    }
} 