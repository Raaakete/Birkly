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

    public function getIcon(): string {
        return 'toggle';
    }

    public function getDescription(): string {
        return 'An on or off switch';
    }

    public function render(): string {
        $checked = $this->value ? 'checked' : '';

        return <<<HTML
        <div class="toggle-field-wrapper">
            <label class="switch" for="field_{$this->name}">
                <input type="checkbox" name="{$this->name}" id="field_{$this->name}" value="1" {$checked}>
                <span class="switch__slider"></span>
            </label>
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
            min-height: var(--control);
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
            return '<span class="ft-list-badge is-on">On</span>';
        }

        return '<span class="ft-list-empty">Off</span>';
    }

    public function getTableMinWidth(): string {
        return '70px';
    }

    public function getConfigUI($fieldIndex, $existingData = []): string {
        return '';
    }

    public function outputsTrustedHtml(): bool
    {
        return true;
    }

    public function formatValueForTemplate($value, array $fieldConfig = [], array $context = []): string
    {
        $on = $value === true || $value === '1' || $value === 1 || $value === 'true' || $value === 'yes';

        return $on
            ? '<span class="birkly-bool birkly-bool--on">On</span>'
            : '<span class="birkly-bool birkly-bool--off">Off</span>';
    }
}
