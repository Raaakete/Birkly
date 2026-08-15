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
            <label class="checkbox-label" for="field_{$this->name}">
                <input type="checkbox" name="{$this->name}" id="field_{$this->name}" value="1" {$checked}>
            </label>
        </div>
        HTML;
    }

    public function getCSS(): string {
        return <<<'CSS'
        .checkbox-field-wrapper {
            display: flex;
            align-items: center;
            min-height: var(--control-md, 2.5rem);
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
        $jsPath = __DIR__ . '/fieldtype.js';
        return file_exists($jsPath) ? file_get_contents($jsPath) : '';
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
        if ($value === true || $value === '1' || $value === 1 || $value === 'true') {
            return '<span class="ft-list-badge" style="background:var(--success-50, #d1fae5);color:var(--success-700, #065f46);">✓</span>';
        }
        return '<span class="ft-list-empty">–</span>';
    }

    public function getTableMinWidth(): string {
        return '60px';
    }
}
