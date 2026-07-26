<?php
namespace Birkly\FieldTypes\Toggle;

use Birkly\FieldTypes\BaseField;

class Toggle extends BaseField {
    public function getType(): string {
        return 'toggle';
    }

    public function getCategory(): string {
        return 'Selection';
    }

    public function render(): string {
        $checked = $this->value ? 'checked' : '';

        return <<<HTML
        <div class="toggle-field-wrapper">
            <span class="toggle-switch">
                <input type="checkbox" name="{$this->name}" id="field_{$this->name}" value="1" {$checked} class="toggle-switch-input">
                <span class="toggle-switch-slider"></span>
            </span>
        </div>
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
        return file_get_contents(__DIR__ . '/fieldtype.js');
    }

    public function getCSS(): string {
        return <<<'CSS'
        .toggle-field-wrapper {
            display: flex;
            align-items: center;
            min-height: 40px;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 52px;
            height: 30px;
            cursor: pointer;
        }

        .toggle-switch-input {
            position: absolute;
            inset: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            margin: 0;
            cursor: pointer;
            z-index: 1;
        }

        .toggle-switch-slider {
            position: absolute;
            inset: 0;
            background: var(--border-medium, #e5e7eb);
            border-radius: 999px;
            transition: var(--transition-base, 0.3s ease);
            pointer-events: none;
        }

        .toggle-switch-slider:before {
            content: "";
            position: absolute;
            width: 22px;
            height: 22px;
            left: 4px;
            top: 4px;
            border-radius: 50%;
            background: var(--bg-primary, #ffffff);
            box-shadow: var(--shadow-sm, 0 2px 4px rgba(0,0,0,0.15));
            transition: var(--transition-base, 0.3s ease);
        }

        .toggle-switch-input:checked + .toggle-switch-slider {
            background: var(--primary-500, #3b82f6);
        }

        .toggle-switch-input:checked + .toggle-switch-slider:before {
            transform: translateX(22px);
        }

        .toggle-switch-input:focus + .toggle-switch-slider {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.18);
        }
        CSS;
    }

    public function getAdminConfig(): array {
        return [
            'type' => 'toggle',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
        ];
    }

    public function getSchema(): array {
        return [
            'type' => 'boolean',
            'required' => $this->required,
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
        if ($value === true || $value === '1' || $value === 1 || $value === 'true') {
            return '<span class="ft-list-badge" style="background:var(--success-50, #d1fae5);color:var(--success-700, #065f46);">On</span>';
        }

        return '<span class="ft-list-empty">Off</span>';
    }

    public function getTableMinWidth(): string {
        return '70px';
    }

    public function getConfigUI($fieldIndex, $existingData = []): string {
        return '';
    }
}
