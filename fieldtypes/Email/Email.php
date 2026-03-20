<?php
namespace Birkly\FieldTypes\Email;

use Birkly\FieldTypes\BaseField;

class Email extends BaseField {
    protected $placeholder;
    protected $multiple; // Allow multiple emails

    public function __construct(array $config) {
        parent::__construct($config);
        $this->placeholder = $config['placeholder'] ?? '';
        $this->multiple = $config['multiple'] ?? false;
    }

    public function getType(): string {
        return 'email';
    }

    public function getCategory(): string {
        return 'Content & Text';
    }

    public function render(): string {
        $required = $this->required ? 'required' : '';
        $multiple = $this->multiple ? 'multiple' : '';
        $placeholderText = $this->placeholder ?: 'email@example.com';
        $placeholder = "placeholder=\"" . htmlspecialchars($placeholderText) . "\"";
        $value = htmlspecialchars($this->value ?? '');
        
        return <<<HTML
        <input type="email" 
            name="{$this->name}" 
            id="field_{$this->name}"
            value="{$value}"
            {$required}
            {$multiple}
            {$placeholder}
            class="input"
        >
        HTML;
    }

    public function validate($value): array {
        $errors = [];

        if ($this->required && empty($value)) {
            $errors[] = "{$this->label} is required";
            return $errors;
        }

        if (!empty($value)) {
            if ($this->multiple) {
                $emails = array_map('trim', explode(',', $value));
                foreach ($emails as $email) {
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "{$this->label} contains invalid email: {$email}";
                    }
                }
            } else {
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "{$this->label} must be a valid email address";
                }
            }
        }

        return $errors;
    }

    public function getJavaScript(): string {
        return <<<'JS'
        // Email field validation
        function validateEmailField(field) {
            const value = field.value;
            const errors = [];
            const multiple = field.hasAttribute('multiple');
            
            if (field.required && !value) {
                errors.push('This field is required');
            }
            
            if (value) {
                if (multiple) {
                    const emails = value.split(',').map(e => e.trim());
                    emails.forEach(email => {
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailRegex.test(email)) {
                            errors.push(`Invalid email: ${email}`);
                        }
                    });
                } else {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(value)) {
                        errors.push('Invalid email format');
                    }
                }
            }
            
            return errors;
        }
        JS;
    }

    public function getCSS(): string {
        return <<<'CSS'
        .email-field {
            width: 100%;
            padding: var(--space-2, 0.5rem);
            border: 1px solid var(--border-medium, #e5e7eb);
            border-radius: var(--radius-sm, 0.375rem);
            font-size: var(--font-size-base, 1rem);
            background: var(--bg-primary, #ffffff);
            color: var(--text-primary, #20221D);
        }
        
        .email-field:focus {
            border-color: var(--primary-500, #3b82f6);
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .email-field.error {
            border-color: var(--error-500, #ef4444);
        }
        CSS;
    }

    public function getAdminConfig(): array {
        return [
            'type' => 'email',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            'placeholder' => $this->placeholder,
            'multiple' => $this->multiple
        ];
    }

    public function getSchema(): array {
        return [
            'type' => 'string',
            'format' => 'email',
            'required' => $this->required,
            'multiple' => $this->multiple
        ];
    }

    public function getDefaultValue() {
        return '';
    }

    public function processValue($value) {
        if ($this->multiple && !empty($value)) {
            return array_map('trim', explode(',', $value));
        }
        return trim($value);
    }

    public function processLoadedValue($value) {
        if (is_array($value)) {
            return implode(', ', $value);
        }
        return $value;
    }

    public function renderListCell($value, array $context = []): string {
        if (empty($value)) {
            return '<span class="ft-list-empty">–</span>';
        }
        
        // Truncate long email addresses
        $email = (string)$value;
        $max = 40;
        if (mb_strlen($email) > $max) {
            $email = mb_substr($email, 0, $max - 3) . '...';
        }
        
        return '<span class="ft-list-text">' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    public function getTableMinWidth(): string {
        return '200px'; // Email fields need moderate space for full addresses
    }

    public function getConfigUI($fieldIndex, $existingData = []): string {
        $placeholder = htmlspecialchars($existingData['placeholder'] ?? '');
        $multiple = !empty($existingData['multiple']);
        $multipleChecked = $multiple ? 'checked' : '';
        
        return <<<HTML
            <div class="field-config-section">
                <label>Placeholder</label>
                <input type="text" name="fields[{$fieldIndex}][placeholder]" class="input" value="{$placeholder}" placeholder="e.g., email@example.com">
            </div>
            <div class="field-config-section">
                <label class="checkbox-label">
                    <input type="checkbox" name="fields[{$fieldIndex}][multiple]" value="1" {$multipleChecked}>
                    <span class="checkmark"></span>
                    <span>Allow Multiple Emails</span>
                </label>
            </div>
        HTML;
    }
}

