<?php
namespace HaruCMS\FieldTypes\Text;

use HaruCMS\FieldTypes\BaseField;

class Text extends BaseField {
    protected $placeholder;
    protected $minLength;
    protected $maxLength;
    protected $pattern;

    public function __construct(array $config) {
        parent::__construct($config);
        
        $this->placeholder = $config['placeholder'] ?? '';
        $this->minLength = $config['minLength'] ?? null;
        $this->maxLength = $config['maxLength'] ?? null;
        $this->pattern = $config['pattern'] ?? null;

        // Add validation rules
        if ($this->minLength !== null) {
            $this->validationRules[] = ['minLength', $this->minLength];
        }
        if ($this->maxLength !== null) {
            $this->validationRules[] = ['maxLength', $this->maxLength];
        }
        if ($this->pattern !== null) {
            $this->validationRules[] = ['pattern', $this->pattern];
        }
    }

    public function getType(): string {
        return 'text';
    }

    public function getCategory(): string {
        return 'Content & Text';
    }

    public function render(): string {
        $required = $this->required ? 'required' : '';
        $minLength = $this->minLength ? "minlength=\"{$this->minLength}\"" : '';
        $maxLength = $this->maxLength ? "maxlength=\"{$this->maxLength}\"" : '';
        $pattern = $this->pattern ? "pattern=\"{$this->pattern}\"" : '';
        $placeholderText = $this->placeholder ?: 'Enter ' . strtolower($this->label ?: 'text');
        $placeholder = "placeholder=\"" . htmlspecialchars($placeholderText) . "\"";
        $value = htmlspecialchars($this->value ?? '');

        return <<<HTML
        <input type="text" 
            name="{$this->name}" 
            id="field_{$this->name}"
            value="{$value}"
            {$required}
            {$minLength}
            {$maxLength}
            {$pattern}
            {$placeholder}
            class="input"
        >
        HTML;
    }

    public function validate($value): array {
        $errors = [];

        if ($this->required && empty($value)) {
            $errors[] = "{$this->label} is required";
        }

        if ($this->minLength !== null && strlen($value) < $this->minLength) {
            $errors[] = "{$this->label} must be at least {$this->minLength} characters";
        }

        if ($this->maxLength !== null && strlen($value) > $this->maxLength) {
            $errors[] = "{$this->label} must not exceed {$this->maxLength} characters";
        }

        if ($this->pattern !== null && !preg_match($this->pattern, $value)) {
            $errors[] = "{$this->label} has an invalid format";
        }

        return $errors;
    }

    public function getJavaScript(): string {
        return <<<'JS'
        // Text field validation
        function validateTextField(field) {
            const value = field.value;
            const errors = [];
            
            if (field.required && !value) {
                errors.push('This field is required');
            }
            
            if (field.minLength && value.length < field.minLength) {
                errors.push(`Must be at least ${field.minLength} characters`);
            }
            
            if (field.maxLength && value.length > field.maxLength) {
                errors.push(`Must not exceed ${field.maxLength} characters`);
            }
            
            if (field.pattern && !new RegExp(field.pattern).test(value)) {
                errors.push('Invalid format');
            }
            
            return errors;
        }
        JS;
    }

    public function getCSS(): string {
        return <<<CSS
        .text-field {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .text-field:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 2px var(--primary-light);
        }
        
        .text-field.error {
            border-color: var(--danger);
        }
        
        .text-field.error:focus {
            box-shadow: 0 0 0 2px var(--danger-light);
        }
        CSS;
    }

    public function getAdminConfig(): array {
        return [
            'type' => 'text',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            'placeholder' => $this->placeholder,
            'minLength' => $this->minLength,
            'maxLength' => $this->maxLength,
            'pattern' => $this->pattern,
            'validationRules' => $this->validationRules
        ];
    }

    public function getSchema(): array {
        return [
            'type' => 'string',
            'required' => $this->required,
            'minLength' => $this->minLength,
            'maxLength' => $this->maxLength,
            'pattern' => $this->pattern
        ];
    }

    public function getDefaultValue() {
        return '';
    }

    public function processValue($value) {
        return trim($value);
    }

    public function processLoadedValue($value) {
        return $value;
    }

    public function getConfigUI($fieldIndex, $existingData = []): string {
        $placeholder = htmlspecialchars($existingData['placeholder'] ?? '');
        $minLength = htmlspecialchars($existingData['minLength'] ?? '');
        $maxLength = htmlspecialchars($existingData['maxLength'] ?? '');
        
        return <<<HTML
            <div class="field-config-section">
                <label>Placeholder</label>
                <input type="text" name="fields[{$fieldIndex}][placeholder]" class="input" value="{$placeholder}" placeholder="Enter placeholder text">
            </div>
            <div class="field-config-row">
                <div class="field-config-field">
                    <label>Min Length</label>
                    <input type="number" name="fields[{$fieldIndex}][minLength]" class="input" value="{$minLength}" min="0" placeholder="No minimum">
                </div>
                <div class="field-config-field">
                    <label>Max Length</label>
                    <input type="number" name="fields[{$fieldIndex}][maxLength]" class="input" value="{$maxLength}" min="0" placeholder="No maximum">
                </div>
            </div>
        HTML;
    }
} 