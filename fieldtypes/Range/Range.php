<?php
namespace HaruCMS\FieldTypes\Range;

use HaruCMS\FieldTypes\BaseField;

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
            gap: 0.75rem;
        }
        
        .range-input-group {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .range-slider {
            flex: 1;
            height: 6px;
            border-radius: 3px;
            background: #ddd;
            outline: none;
            -webkit-appearance: none;
            cursor: pointer;
            position: relative;
        }
        
        .range-slider::-webkit-slider-runnable-track {
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: #ddd;
        }
        
        .range-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary, #007bff);
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            border: 2px solid #fff;
            margin-top: -7px;
            transition: all 0.2s;
        }
        
        .range-slider::-webkit-slider-thumb:hover {
            transform: scale(1.15);
            box-shadow: 0 3px 6px rgba(0,0,0,0.3);
        }
        
        .range-slider::-moz-range-track {
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: #ddd;
            border: none;
        }
        
        .range-slider::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary, #007bff);
            cursor: pointer;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            transition: all 0.2s;
        }
        
        .range-slider::-moz-range-thumb:hover {
            transform: scale(1.15);
            box-shadow: 0 3px 6px rgba(0,0,0,0.3);
        }
        
        .range-value {
            min-width: 60px;
            text-align: center;
            font-weight: 600;
            font-size: 1rem;
            color: var(--primary, #007bff);
            padding: 0.25rem 0.5rem;
            background: #f0f7ff;
            border-radius: 4px;
        }
        
        .range-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.875rem;
            color: #6c757d;
            padding: 0 0.25rem;
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

