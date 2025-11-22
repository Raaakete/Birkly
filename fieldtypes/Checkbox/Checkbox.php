<?php
namespace HaruCMS\FieldTypes\Checkbox;

use HaruCMS\FieldTypes\BaseField;

class Checkbox extends BaseField {
    public function getType(): string {
        return 'checkbox';
    }

    public function getCategory(): string {
        return 'Selection';
    }

    public function render(): string {
        $checked = $this->value ? 'checked' : '';
        return <<<HTML
        <div class="checkbox-field-wrapper">
            <label class="checkbox-switch" for="field_{$this->name}">
                <input type="checkbox" name="{$this->name}" id="field_{$this->name}" value="1" {$checked} class="checkbox-switch-input">
                <span class="checkbox-switch-slider"></span>
            </label>
        </div>
        HTML;
    }

    public function getCSS(): string {
        return <<<'CSS'
        .checkbox-field-wrapper {
            display: flex;
            align-items: center;
            min-height: 40px;
        }
        
        .checkbox-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 28px;
            cursor: pointer;
        }
        
        .checkbox-switch-input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .checkbox-switch-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.3s;
            border-radius: 28px;
        }
        
        .checkbox-switch-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .checkbox-switch-input:checked + .checkbox-switch-slider {
            background-color: var(--primary, #007bff);
        }
        
        .checkbox-switch-input:checked + .checkbox-switch-slider:before {
            transform: translateX(22px);
        }
        
        .checkbox-switch-input:focus + .checkbox-switch-slider {
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }
        CSS;
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