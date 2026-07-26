<?php
namespace Birkly\FieldTypes\Checkbox;

use Birkly\FieldTypes\BaseField;

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
            <span class="checkbox-switch">
                <input type="checkbox" name="{$this->name}" id="field_{$this->name}" value="1" {$checked} class="checkbox-switch-input">
                <span class="checkbox-switch-slider"></span>
            </span>
        </div>
        HTML;
    }

    public function getCSS(): string {
        return <<<'CSS'
        /* Checkbox Fieldtype - Uses system CSS variables */
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
            position: absolute;
            inset: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            margin: 0;
            cursor: pointer;
            z-index: 1;
        }
        
        .checkbox-switch-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--border-medium, #e5e7eb);
            transition: var(--transition-base, 0.3s ease);
            border-radius: 28px;
            pointer-events: none;
        }
        
        .checkbox-switch-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: var(--bg-primary, #ffffff);
            transition: var(--transition-base, 0.3s ease);
            border-radius: 50%;
            box-shadow: var(--shadow-sm, 0 2px 4px rgba(0,0,0,0.2));
        }
        
        .checkbox-switch-input:checked + .checkbox-switch-slider {
            background-color: var(--success-500, #22c55e);
        }
        
        .checkbox-switch-input:checked + .checkbox-switch-slider:before {
            transform: translateX(22px);
        }
        
        .checkbox-switch-input:focus + .checkbox-switch-slider {
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.2);
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

    public function renderListCell($value, array $context = []): string {
        // Checkbox shows Yes/No or checkmark
        if ($value === true || $value === '1' || $value === 1 || $value === 'true') {
            return '<span class="ft-list-badge" style="background:var(--success-50, #d1fae5);color:var(--success-700, #065f46);">✓</span>';
        }
        return '<span class="ft-list-empty">–</span>';
    }

    public function getTableMinWidth(): string {
        return '60px'; // Checkbox fields are very narrow (just Yes/No)
    }
} 