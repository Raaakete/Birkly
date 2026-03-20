<?php
namespace Birkly\FieldTypes\Range;

use Birkly\FieldTypes\BaseField;

class Range extends BaseField {
    protected $min;
    protected $max;
    protected $step;
    protected $showValue; // Show current value

    public function __construct(array $config) {
        parent::__construct($config);
        $this->min = $config['min'] ?? 0;
        $this->max = $config['max'] ?? 100;
        $this->step = $config['step'] ?? 1;
        $this->showValue = $config['showValue'] ?? true;
        
        // Set default value to min if not provided
        if ($this->value === null) {
            $this->value = $this->min;
        }
    }

    public function getType(): string {
        return 'range';
    }

    public function getCategory(): string {
        return 'Numeric';
    }

    public function render(): string {
        $required = $this->required ? 'required' : '';
        $value = htmlspecialchars($this->value ?? $this->min);
        $min = htmlspecialchars($this->min);
        $max = htmlspecialchars($this->max);
        $step = htmlspecialchars($this->step);
        
        $valueDisplay = $this->showValue ? "<span class=\"range-value\" id=\"range_value_{$this->name}\">{$value}</span>" : '';
        
        return <<<HTML
        <div class="range-field-wrapper">
            <div class="range-input-group">
                <input type="range" 
                    name="{$this->name}" 
                    id="field_{$this->name}"
                    value="{$value}"
                    min="{$min}"
                    max="{$max}"
                    step="{$step}"
                    {$required}
                    class="range-slider"
                >
                {$valueDisplay}
            </div>
            <div class="range-labels">
                <span class="range-label-min">{$min}</span>
                <span class="range-label-max">{$max}</span>
            </div>
        </div>
        HTML;
    }

    public function validate($value): array {
        $errors = [];

        if ($this->required && ($value === null || $value === '')) {
            $errors[] = "{$this->label} is required";
            return $errors;
        }

        if ($value !== null && $value !== '') {
            if (!is_numeric($value)) {
                $errors[] = "{$this->label} must be a valid number";
                return $errors;
            }
            
            $numValue = (float)$value;
            
            if ($numValue < $this->min) {
                $errors[] = "{$this->label} must be at least {$this->min}";
            }
            if ($numValue > $this->max) {
                $errors[] = "{$this->label} must be at most {$this->max}";
            }
        }

        return $errors;
    }

    public function getJavaScript(): string {
        $showValue = $this->showValue ? 'true' : 'false';
        return <<<JS
        // Range field JavaScript
        (function() {
            const fieldName = '{$this->name}';
            const showValue = {$showValue};
            
            function initRangeField() {
                const slider = document.getElementById('field_' + fieldName);
                const valueDisplay = document.getElementById('range_value_' + fieldName);
                
                if (!slider) return;
                
                // Update value display on input
                slider.addEventListener('input', function() {
                    if (showValue && valueDisplay) {
                        valueDisplay.textContent = this.value;
                    }
                });
                
                // Initialize display
                if (showValue && valueDisplay) {
                    valueDisplay.textContent = slider.value;
                }
            }
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initRangeField);
            } else {
                initRangeField();
            }
        })();
        JS;
    }

    public function getCSS(): string {
        return <<<'CSS'
        .range-field-wrapper {
            display: flex;
            flex-direction: column;
            gap: var(--space-3, 0.75rem);
        }
        
        .range-input-group {
            display: flex;
            align-items: center;
            gap: var(--space-4, 1rem);
        }
        
        .range-slider {
            flex: 1;
            height: 6px;
            border-radius: var(--radius-sm, 0.375rem);
            background: var(--border-medium, #e5e7eb);
            outline: none;
            -webkit-appearance: none;
            cursor: pointer;
            position: relative;
        }
        
        .range-slider::-webkit-slider-runnable-track {
            width: 100%;
            height: 6px;
            border-radius: var(--radius-sm, 0.375rem);
            background: var(--border-medium, #e5e7eb);
        }
        
        .range-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: var(--radius-full, 50%);
            background: var(--primary-500, #3b82f6);
            cursor: pointer;
            box-shadow: var(--shadow-sm, 0 1px 3px rgba(0, 0, 0, 0.1));
            border: 2px solid var(--bg-primary, #ffffff);
            margin-top: -7px;
            transition: all var(--transition-fast, 150ms ease);
        }
        
        .range-slider::-webkit-slider-thumb:hover {
            transform: scale(1.15);
            box-shadow: var(--shadow-md, 0 4px 6px -1px rgba(0, 0, 0, 0.1));
        }
        
        .range-slider::-moz-range-track {
            width: 100%;
            height: 6px;
            border-radius: var(--radius-sm, 0.375rem);
            background: var(--border-medium, #e5e7eb);
            border: none;
        }
        
        .range-slider::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: var(--radius-full, 50%);
            background: var(--primary-500, #3b82f6);
            cursor: pointer;
            border: 2px solid var(--bg-primary, #ffffff);
            box-shadow: var(--shadow-sm, 0 1px 3px rgba(0, 0, 0, 0.1));
            transition: all var(--transition-fast, 150ms ease);
        }
        
        .range-slider::-moz-range-thumb:hover {
            transform: scale(1.15);
            box-shadow: var(--shadow-md, 0 4px 6px -1px rgba(0, 0, 0, 0.1));
        }
        
        .range-value {
            min-width: 60px;
            text-align: center;
            font-weight: 600;
            font-size: var(--font-size-base, 1rem);
            color: var(--primary-500, #3b82f6);
            padding: var(--space-1, 0.25rem) var(--space-2, 0.5rem);
            background: var(--primary-50, #eff6ff);
            border-radius: var(--radius-sm, 0.375rem);
        }
        
        .range-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.875rem;
            color: var(--text-secondary, #72746F);
            padding: 0 var(--space-1, 0.25rem);
        }
        CSS;
    }

    public function getAdminConfig(): array {
        return [
            'type' => 'range',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            'min' => $this->min,
            'max' => $this->max,
            'step' => $this->step,
            'showValue' => $this->showValue
        ];
    }

    public function getSchema(): array {
        return [
            'type' => 'number',
            'required' => $this->required,
            'min' => $this->min,
            'max' => $this->max,
            'step' => $this->step
        ];
    }

    public function getDefaultValue() {
        return $this->min;
    }

    public function processValue($value) {
        if ($value === null || $value === '') {
            return $this->min;
        }
        return (float)$value;
    }

    public function processLoadedValue($value) {
        return $value;
    }

    public function renderListCell($value, array $context = []): string {
        if ($value === null || $value === '') {
            return '<span class="ft-list-empty">–</span>';
        }
        
        // Show numeric value
        $number = is_numeric($value) ? (float)$value : $value;
        if (is_float($number) && $number == (int)$number) {
            $number = (int)$number;
        }
        
        return '<span class="ft-list-text">' . htmlspecialchars((string)$number, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    public function getTableMinWidth(): string {
        return '80px'; // Range fields are narrow (just numeric value)
    }

    public function getConfigUI($fieldIndex, $existingData = []): string {
        $min = htmlspecialchars($existingData['min'] ?? 0);
        $max = htmlspecialchars($existingData['max'] ?? 100);
        $step = htmlspecialchars($existingData['step'] ?? 1);
        $showValue = !isset($existingData['showValue']) || !empty($existingData['showValue']);
        $showValueChecked = $showValue ? 'checked' : '';
        
        return <<<HTML
            <div class="field-config-row">
                <div class="field-config-field">
                    <label>Min Value</label>
                    <input type="number" name="fields[{$fieldIndex}][min]" class="input" value="{$min}">
                </div>
                <div class="field-config-field">
                    <label>Max Value</label>
                    <input type="number" name="fields[{$fieldIndex}][max]" class="input" value="{$max}">
                </div>
            </div>
            <div class="field-config-row">
                <div class="field-config-field">
                    <label>Step</label>
                    <input type="number" name="fields[{$fieldIndex}][step]" class="input" value="{$step}" min="0.01" step="0.01">
                </div>
                <div class="field-config-field">
                    <label class="checkbox-label">
                        <input type="checkbox" name="fields[{$fieldIndex}][showValue]" value="1" {$showValueChecked}>
                        <span class="checkmark"></span>
                        <span>Show Current Value</span>
                    </label>
                </div>
            </div>
        HTML;
    }
}

