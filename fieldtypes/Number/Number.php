<?php
namespace HaruCMS\FieldTypes\Number;

use HaruCMS\FieldTypes\BaseField;

class Number extends BaseField {
    protected $min;
    protected $max;
    protected $step;
    protected $integer; // Whether to treat as integer

    public function __construct(array $config) {
        parent::__construct($config);
        $this->min = $config['min'] ?? null;
        $this->max = $config['max'] ?? null;
        $this->step = $config['step'] ?? 1;
        $this->integer = $config['integer'] ?? false;
    }

    public function getType(): string {
        return 'number';
    }

    public function getCategory(): string {
        return 'Numeric';
    }

    public function render(): string {
        $required = $this->required ? 'required' : '';
        $min = $this->min !== null ? "min=\"{$this->min}\"" : '';
        $max = $this->max !== null ? "max=\"{$this->max}\"" : '';
        $step = $this->step !== null ? "step=\"{$this->step}\"" : '';
        $value = htmlspecialchars($this->value ?? '');
        $placeholder = $this->min !== null && $this->max !== null 
            ? "placeholder=\"{$this->min} - {$this->max}\"" 
            : ($this->min !== null ? "placeholder=\"Min: {$this->min}\"" : ($this->max !== null ? "placeholder=\"Max: {$this->max}\"" : 'placeholder="Enter a number"'));
        return <<<HTML
        <input type="number" name="{$this->name}" id="field_{$this->name}" class="input" value="{$value}" {$required} {$min} {$max} {$step} {$placeholder}>
        HTML;
    }

    public function validate($value): array {
        $errors = [];
        if ($this->required && ($value === null || $value === '')) {
            $errors[] = "{$this->label} is required";
        }
        
        if ($value !== null && $value !== '') {
            if (!is_numeric($value)) {
                $errors[] = "{$this->label} must be a valid number";
                return $errors;
            }
            
            $numValue = $this->integer ? (int)$value : (float)$value;
            
            if ($this->min !== null && $numValue < $this->min) {
                $errors[] = "{$this->label} must be at least {$this->min}";
            }
            if ($this->max !== null && $numValue > $this->max) {
                $errors[] = "{$this->label} must be at most {$this->max}";
            }
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
            'type' => 'number',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            'min' => $this->min,
            'max' => $this->max,
            'step' => $this->step,
            'integer' => $this->integer
        ];
    }

    public function getSchema(): array {
        return [
            'type' => $this->integer ? 'integer' : 'number',
            'required' => $this->required,
            'min' => $this->min,
            'max' => $this->max,
            'step' => $this->step
        ];
    }

    public function getDefaultValue() {
        return 0;
    }

    public function processValue($value) {
        if ($value === null || $value === '') {
            return $this->integer ? 0 : 0.0;
        }
        return $this->integer ? (int)$value : (float)$value;
    }

    public function processLoadedValue($value) {
        return $value;
    }

    public function getConfigUI($fieldIndex, $existingData = []): string {
        $min = htmlspecialchars($existingData['min'] ?? '');
        $max = htmlspecialchars($existingData['max'] ?? '');
        $step = htmlspecialchars($existingData['step'] ?? 1);
        $integer = !empty($existingData['integer']);
        $integerChecked = $integer ? 'checked' : '';
        
        return <<<HTML
            <div class="field-config-row">
                <div class="field-config-field">
                    <label>Min Value</label>
                    <input type="number" name="fields[{$fieldIndex}][min]" class="input" value="{$min}" placeholder="No minimum">
                </div>
                <div class="field-config-field">
                    <label>Max Value</label>
                    <input type="number" name="fields[{$fieldIndex}][max]" class="input" value="{$max}" placeholder="No maximum">
                </div>
            </div>
            <div class="field-config-row">
                <div class="field-config-field">
                    <label>Step</label>
                    <input type="number" name="fields[{$fieldIndex}][step]" class="input" value="{$step}" min="0.01" step="0.01">
                </div>
                <div class="field-config-field">
                    <label class="checkbox-label">
                        <input type="checkbox" name="fields[{$fieldIndex}][integer]" value="1" {$integerChecked}>
                        <span class="checkmark"></span>
                        <span>Integer Only</span>
                    </label>
                </div>
            </div>
        HTML;
    }
} 