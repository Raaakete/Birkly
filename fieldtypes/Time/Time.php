<?php
namespace Birkly\FieldTypes\Time;

use Birkly\FieldTypes\BaseField;

class Time extends BaseField {
    protected $format; // 12 or 24 hour format
    protected $step; // Minutes step (e.g., 15 for 15-minute intervals)

    public function __construct(array $config) {
        parent::__construct($config);
        $this->format = $config['format'] ?? '24';
        $this->step = $config['step'] ?? 60; // Default to 1 minute
    }

    public function getType(): string {
        return 'time';
    }

    public function getCategory(): string {
        return 'Date & Time';
    }

    public function getIcon(): string {
        return 'clock';
    }

    public function getDescription(): string {
        return 'A time of day';
    }

    public function render(): string {
        $required = $this->required ? 'required' : '';
        $value = htmlspecialchars($this->value ?? '');
        $step = $this->step ? "step=\"{$this->step}\"" : '';
        
        return <<<HTML
        <input type="time" 
            name="{$this->name}" 
            id="field_{$this->name}"
            value="{$value}"
            {$required}
            {$step}
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
            // Validate time format (HH:mm or HH:mm:ss)
            if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) {
                $errors[] = "{$this->label} must be a valid time (HH:mm)";
            } else {
                $parts = explode(':', $value);
                $hour = (int)$parts[0];
                $minute = (int)$parts[1];
                
                if ($hour < 0 || $hour > 23) {
                    $errors[] = "{$this->label} hour must be between 0 and 23";
                }
                if ($minute < 0 || $minute > 59) {
                    $errors[] = "{$this->label} minute must be between 0 and 59";
                }
            }
        }

        return $errors;
    }

    public function getJavaScript(): string {
        return <<<'JS'
        // Time field validation
        function validateTimeField(field) {
            const value = field.value;
            const errors = [];
            
            if (field.required && !value) {
                errors.push('This field is required');
            }
            
            if (value) {
                const timeRegex = /^([01]\d|2[0-3]):([0-5]\d)(:([0-5]\d))?$/;
                if (!timeRegex.test(value)) {
                    errors.push('Invalid time format (use HH:mm)');
                }
            }
            
            return errors;
        }
        JS;
    }

    public function getCSS(): string {
        return <<<'CSS'
        .time-field {
            width: 100%;
            padding: var(--space-2, 0.5rem);
            border: 1px solid var(--border-medium, #e5e7eb);
            border-radius: var(--radius-sm, 0.375rem);
            font-size: var(--font-size-base, 1rem);
            background: var(--bg-primary, #ffffff);
            color: var(--text-primary, #20221D);
        }
        
        .time-field:focus {
            border-color: var(--primary-500, #3b82f6);
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        CSS;
    }

    public function getAdminConfig(): array {
        return [
            'type' => 'time',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            'format' => $this->format,
            'step' => $this->step
        ];
    }

    public function getSchema(): array {
        return [
            'type' => 'string',
            'format' => 'time',
            'required' => $this->required
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

    public function renderListCell($value, array $context = []): string {
        if (empty($value)) {
            return '<span class="ft-list-empty">–</span>';
        }
        
        // Format time for display (HH:mm)
        if (preg_match('/^(\d{2}:\d{2})/', $value, $matches)) {
            return '<span class="ft-list-text">' . htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8') . '</span>';
        }
        
        return '<span class="ft-list-text">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    public function getTableMinWidth(): string {
        return '80px'; // Time fields are narrow (HH:mm format)
    }

    public function getConfigUI($fieldIndex, $existingData = []): string {
        $format = $existingData['format'] ?? '24';
        $step = htmlspecialchars($existingData['step'] ?? 60);
        $format24 = $format === '24' ? 'selected' : '';
        $format12 = $format === '12' ? 'selected' : '';
        
        return <<<HTML
            <div class="field-config-section">
                <label>Time Format</label>
                <select name="fields[{$fieldIndex}][format]" class="input">
                    <option value="24" {$format24}>24-hour (HH:mm)</option>
                    <option value="12" {$format12}>12-hour (hh:mm AM/PM)</option>
                </select>
            </div>
            <div class="field-config-section">
                <label>Step (minutes)</label>
                <input type="number" name="fields[{$fieldIndex}][step]" class="input" value="{$step}" min="1" max="60">
                <small class="form-text">Minute intervals (e.g., 15 for 15-minute steps)</small>
            </div>
        HTML;
    }
}

