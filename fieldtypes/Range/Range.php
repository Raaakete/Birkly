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

    public function getIcon(): string {
        return 'sliders';
    }

    public function getDescription(): string {
        return 'A number picked on a slider';
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
        /* Track is a well, the thumb is ink; the value sits in a mono well. */
        .range-field-wrapper {
            display: flex;
            flex-direction: column;
            gap: var(--s-1);
        }
        .range-input-group {
            display: flex;
            align-items: center;
            gap: var(--s-3);
        }
        .range-slider {
            flex: 1;
            height: 4px;
            margin: 0;
            border-radius: var(--r-pixel);
            background: var(--surface-3);
            outline: none;
            -webkit-appearance: none;
            appearance: none;
            cursor: pointer;
        }
        .range-slider::-webkit-slider-runnable-track {
            height: 4px;
            border-radius: var(--r-pixel);
            background: var(--surface-3);
        }
        .range-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 14px;
            height: 14px;
            margin-top: -5px;
            border: 2px solid var(--surface);
            border-radius: var(--r-inset);
            background: var(--text);
            cursor: pointer;
        }
        .range-slider::-moz-range-track {
            height: 4px;
            border: 0;
            border-radius: var(--r-pixel);
            background: var(--surface-3);
        }
        .range-slider::-moz-range-thumb {
            width: 14px;
            height: 14px;
            border: 2px solid var(--surface);
            border-radius: var(--r-inset);
            background: var(--text);
            cursor: pointer;
        }
        .range-slider:focus-visible { outline: var(--focus-outline); outline-offset: 4px; }
        .range-value {
            min-width: 44px;
            padding: 2px var(--s-2);
            border-radius: var(--r-inset);
            background: var(--surface-2);
            color: var(--text);
            font: var(--weight-medium) var(--size-mono) / var(--lh-mono) var(--font-mono);
            text-align: center;
        }
        .range-labels {
            display: flex;
            justify-content: space-between;
            color: var(--text-3);
            font: var(--weight-regular) var(--size-caption) / var(--lh-caption) var(--font-mono);
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

    public function formatValueForTemplate($value, array $fieldConfig = [], array $context = []): string
    {
        require_once dirname(__DIR__, 2) . '/core/fieldtype_presentation.php';
        birkly_fieldtype_presentation_boot();
        $rangeHtml = function_exists('get_template_function') ? get_template_function('range_html') : null;
        if ($rangeHtml === null) {
            return is_scalar($value) ? (string) $value : '';
        }
        $min = $fieldConfig['min'] ?? null;
        $max = $fieldConfig['max'] ?? null;

        return (string) $rangeHtml($value, $min, $max);
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

