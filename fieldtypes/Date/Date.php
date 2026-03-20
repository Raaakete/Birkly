<?php
namespace Birkly\FieldTypes\Date;

use Birkly\FieldTypes\BaseField;

class Date extends BaseField {
    public function getType(): string {
        return 'date';
    }

    public function getCategory(): string {
        return 'Date & Time';
    }

    public function render(): string {
        $required = $this->required ? 'required' : '';
        $value = htmlspecialchars($this->value ?? '');
        return <<<HTML
        <input type="date" name="{$this->name}" id="field_{$this->name}" class="input" value="{$value}" {$required}>
        HTML;
    }

    public function validate($value): array {
        $errors = [];
        if ($this->required && empty($value)) {
            $errors[] = "{$this->label} is required";
        }
        
        if (!empty($value)) {
            // Validate date format (YYYY-MM-DD)
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $errors[] = "{$this->label} must be a valid date (YYYY-MM-DD)";
            } else {
                $date = \DateTime::createFromFormat('Y-m-d', $value);
                if (!$date || $date->format('Y-m-d') !== $value) {
                    $errors[] = "{$this->label} must be a valid date";
                }
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
            'type' => 'date',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group
        ];
    }

    public function getSchema(): array {
        return [
            'type' => 'date',
            'required' => $this->required
        ];
    }

    public function getDefaultValue() {
        return '';
    }

    public function processValue($value) {
        return $value;
    }

    public function processLoadedValue($value) {
        return $value;
    }

    public function renderListCell($value, array $context = []): string {
        if (empty($value)) {
            return '<span class="ft-list-empty">–</span>';
        }
        
        // Format date for display
        $date = $value;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            // Already in YYYY-MM-DD format, use as-is
            return '<span class="ft-list-text">' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8') . '</span>';
        }
        
        // Try to parse and format
        try {
            $dateObj = new \DateTime($date);
            return '<span class="ft-list-text">' . htmlspecialchars($dateObj->format('Y-m-d'), ENT_QUOTES, 'UTF-8') . '</span>';
        } catch (\Exception $e) {
            return '<span class="ft-list-text">' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8') . '</span>';
        }
    }

    public function getTableMinWidth(): string {
        return '100px'; // Date fields need less space (YYYY-MM-DD format)
    }

    public function getConfigUI($fieldIndex, $existingData = []): string {
        $format = $existingData['format'] ?? 'Y-m-d';
        $formatSelected = [
            'Y-m-d' => $format === 'Y-m-d' ? 'selected' : '',
            'd/m/Y' => $format === 'd/m/Y' ? 'selected' : '',
            'm/d/Y' => $format === 'm/d/Y' ? 'selected' : '',
            'd.m.Y' => $format === 'd.m.Y' ? 'selected' : '',
            'Y/m/d' => $format === 'Y/m/d' ? 'selected' : '',
        ];
        
        return <<<HTML
            <div class="field-config-section">
                <label>Date Format</label>
                <select name="fields[{$fieldIndex}][format]" class="input">
                    <option value="Y-m-d" {$formatSelected['Y-m-d']}>YYYY-MM-DD (ISO)</option>
                    <option value="d/m/Y" {$formatSelected['d/m/Y']}>DD/MM/YYYY</option>
                    <option value="m/d/Y" {$formatSelected['m/d/Y']}>MM/DD/YYYY</option>
                    <option value="d.m.Y" {$formatSelected['d.m.Y']}>DD.MM.YYYY</option>
                    <option value="Y/m/d" {$formatSelected['Y/m/d']}>YYYY/MM/DD</option>
                </select>
                <small class="form-text">Display format for the date field</small>
            </div>
        HTML;
    }
} 