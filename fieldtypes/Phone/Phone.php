<?php
namespace Birkly\FieldTypes\Phone;

use Birkly\FieldTypes\BaseField;

class Phone extends BaseField {
    protected $placeholder;

    public function __construct(array $config) {
        parent::__construct($config);
        $this->placeholder = $config['placeholder'] ?? '+1 555 123 4567';
    }

    public function getType(): string {
        return 'phone';
    }

    public function getCategory(): string {
        return 'Content & Text';
    }

    public function getIcon(): string {
        return 'phone';
    }

    public function getDescription(): string {
        return 'A phone number';
    }

    public function render(): string {
        $required = $this->required ? 'required' : '';
        $placeholder = htmlspecialchars($this->placeholder, ENT_QUOTES, 'UTF-8');
        $value = htmlspecialchars((string)($this->value ?? ''), ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <input type="tel"
            name="{$this->name}"
            id="field_{$this->name}"
            value="{$value}"
            {$required}
            inputmode="tel"
            placeholder="{$placeholder}"
            class="input"
        >
        HTML;
    }

    public function validate($value): array {
        $errors = [];
        $value = trim((string)$value);

        if ($this->required && $value === '') {
            $errors[] = "{$this->label} is required";
            return $errors;
        }

        if ($value !== '' && !$this->isValidPhone($value)) {
            $errors[] = "{$this->label} must be a valid phone number";
        }

        return $errors;
    }

    public function getJavaScript(): string {
        return file_get_contents(__DIR__ . '/fieldtype.js');
    }

    public function getCSS(): string {
        return <<<'CSS'
        .phone-field-invalid {
            border-color: var(--error-500, #ef4444);
        }
        CSS;
    }

    public function getAdminConfig(): array {
        return [
            'type' => 'phone',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            'placeholder' => $this->placeholder,
        ];
    }

    public function getSchema(): array {
        return [
            'type' => 'string',
            'format' => 'phone',
            'required' => $this->required,
        ];
    }

    public function getDefaultValue() {
        return '';
    }

    public function processValue($value) {
        return preg_replace('/\s+/', ' ', trim((string)$value));
    }

    public function processLoadedValue($value) {
        return (string)$value;
    }

    public function getTableMinWidth(): string {
        return '160px';
    }

    public function getConfigUI($fieldIndex, $existingData = []): string {
        $placeholder = htmlspecialchars($existingData['placeholder'] ?? '+1 555 123 4567', ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <div class="field-config-section">
                <label>Placeholder</label>
                <input type="text" name="fields[{$fieldIndex}][placeholder]" class="input" value="{$placeholder}" placeholder="+1 555 123 4567">
            </div>
        HTML;
    }

    private function isValidPhone(string $value): bool {
        $normalized = preg_replace('/[^0-9+]/', '', $value);
        if ($normalized === null || $normalized === '') {
            return false;
        }

        if (substr_count($normalized, '+') > 1 || (strpos($normalized, '+') !== false && strpos($normalized, '+') !== 0)) {
            return false;
        }

        return (bool)preg_match('/^\+?[0-9]{7,15}$/', $normalized);
    }
}
