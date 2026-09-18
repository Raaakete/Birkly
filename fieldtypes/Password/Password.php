<?php
namespace Birkly\FieldTypes\Password;

use Birkly\FieldTypes\BaseField;

class Password extends BaseField {
    protected $minLength;
    protected $autocomplete;

    public function __construct(array $config) {
        parent::__construct($config);
        $this->minLength = max(0, (int) ($config['min_length'] ?? 8));
        $this->autocomplete = (string) ($config['autocomplete'] ?? 'new-password');
    }

    public function getType(): string {
        return 'password';
    }

    public function getCategory(): string {
        return 'Misc';
    }

    public function isSensitive(): bool {
        return true;
    }

    public function render(): string {
        $required = $this->required ? 'required' : '';
        $min = $this->minLength > 0 ? 'minlength="' . $this->minLength . '"' : '';
        $autocomplete = htmlspecialchars($this->autocomplete, ENT_QUOTES, 'UTF-8');
        $placeholder = htmlspecialchars((string) ($this->help ?: 'Enter password'), ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <div class="password-field" data-password-field>
            <input
                type="password"
                name="{$this->name}"
                id="field_{$this->name}"
                class="input password-field-input"
                autocomplete="{$autocomplete}"
                placeholder="{$placeholder}"
                {$required}
                {$min}
                value=""
            >
            <p class="password-field-hint text-muted">Stored as a one-way hash. Leave blank when editing to keep the current password.</p>
        </div>
        HTML;
    }

    public function validate($value): array {
        $errors = [];
        $plain = is_string($value) ? $value : '';

        if ($this->required && $plain === '') {
            $errors[] = "{$this->label} is required";
            return $errors;
        }

        if ($plain !== '' && $this->minLength > 0 && strlen($plain) < $this->minLength) {
            $errors[] = "{$this->label} must be at least {$this->minLength} characters";
        }

        return $errors;
    }

    public function getJavaScript(): string {
        $jsPath = __DIR__ . '/fieldtype.js';
        return file_exists($jsPath) ? (string) file_get_contents($jsPath) : '';
    }

    public function getCSS(): string {
        return <<<'CSS'
        .password-field {
            display: grid;
            gap: var(--space-2, 0.5rem);
        }
        .password-field-hint {
            margin: 0;
            font-size: 0.8125rem;
        }
        CSS;
    }

    public function getAdminConfig(): array {
        return [
            'type' => 'password',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            'min_length' => $this->minLength,
        ];
    }

    public function getSchema(): array {
        return [
            'type' => 'string',
            'format' => 'password-hash',
            'required' => $this->required,
        ];
    }

    public function getDefaultValue() {
        return '';
    }

    public function processValue($value) {
        $plain = is_string($value) ? trim($value) : '';
        if ($plain === '') {
            return null;
        }
        if (str_starts_with($plain, '$2y$') || str_starts_with($plain, '$argon2')) {
            return $plain;
        }

        return password_hash($plain, PASSWORD_DEFAULT);
    }

    public function processLoadedValue($value) {
        return is_string($value) ? $value : '';
    }

    public function maskValueForApiRead($value, array $fieldConfig = []) {
        if (!is_string($value) || $value === '') {
            return '';
        }

        return '[redacted]';
    }

    public function formatValueForTemplate($value, array $fieldConfig = [], array $context = []): string {
        if (!is_string($value) || $value === '') {
            return '';
        }

        return '••••••';
    }

    public function renderListCell($value, array $context = []): string {
        return '<span class="ft-list-badge">••••••</span>';
    }

    public function getConfigUI($fieldIndex, $existingData = []): string {
        $min = htmlspecialchars((string) ($existingData['min_length'] ?? 8), ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <div class="field-config-section">
                <label>Minimum length</label>
                <input type="number" name="fields[{$fieldIndex}][min_length]" class="input" value="{$min}" min="0" max="128">
            </div>
        HTML;
    }
}
