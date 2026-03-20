<?php
namespace Birkly\FieldTypes\DateTime;

use Birkly\FieldTypes\BaseField;

// Prevent fatal "Cannot redeclare class" errors if this file is included twice
// (can happen on case-insensitive filesystems when referenced as Datetime/Datetime.php and DateTime/DateTime.php)
if (class_exists(__NAMESPACE__ . '\\DateTime', false)) {
    return;
}

class DateTime extends BaseField {
    public function getType(): string {
        return 'datetime';
    }

    public function getCategory(): string {
        return 'Date & Time';
    }

    public function render(): string {
        $required = $this->required ? 'required' : '';
        $value = htmlspecialchars($this->value ?? '');
        return <<<HTML
        <input type="datetime-local" name="{$this->name}" id="field_{$this->name}" class="input" value="{$value}" {$required}>
        HTML;
    }

    public function validate($value): array {
        $errors = [];
        if ($this->required && empty($value)) {
            $errors[] = "{$this->label} is required";
        }
        
        if (!empty($value)) {
            // Validate datetime-local format (YYYY-MM-DDTHH:mm)
            if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $value)) {
                $errors[] = "{$this->label} must be a valid date and time";
            } else {
                // Try to parse the datetime
                $datetime = \DateTime::createFromFormat('Y-m-d\TH:i', $value);
                if (!$datetime) {
                    $datetime = \DateTime::createFromFormat('Y-m-d\TH:i:s', $value);
                }
                if (!$datetime) {
                    $errors[] = "{$this->label} must be a valid date and time";
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
            'type' => 'datetime',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group
        ];
    }

    public function getSchema(): array {
        return [
            'type' => 'datetime',
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
        
        // Format datetime for display
        try {
            // Handle datetime-local format (YYYY-MM-DDTHH:mm)
            if (preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/', $value, $matches)) {
                return '<span class="ft-list-text">' . htmlspecialchars($matches[1] . ' ' . $matches[2], ENT_QUOTES, 'UTF-8') . '</span>';
            }
            
            $dateObj = new \DateTime($value);
            return '<span class="ft-list-text">' . htmlspecialchars($dateObj->format('Y-m-d H:i'), ENT_QUOTES, 'UTF-8') . '</span>';
        } catch (\Exception $e) {
            return '<span class="ft-list-text">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</span>';
        }
    }

    public function getTableMinWidth(): string {
        return '140px'; // DateTime fields need moderate space (YYYY-MM-DD HH:mm)
    }

    public function getConfigUI($fieldIndex, $existingData = []): string {
        $format = $existingData['format'] ?? 'Y-m-d H:i';
        $formatSelected = [
            'Y-m-d H:i' => $format === 'Y-m-d H:i' ? 'selected' : '',
            'd/m/Y H:i' => $format === 'd/m/Y H:i' ? 'selected' : '',
            'm/d/Y H:i' => $format === 'm/d/Y H:i' ? 'selected' : '',
            'Y-m-d g:i A' => $format === 'Y-m-d g:i A' ? 'selected' : '',
        ];
        
        return <<<HTML
            <div class="field-config-section">
                <label>Date/Time Format</label>
                <select name="fields[{$fieldIndex}][format]" class="input">
                    <option value="Y-m-d H:i" {$formatSelected['Y-m-d H:i']}>YYYY-MM-DD HH:mm</option>
                    <option value="d/m/Y H:i" {$formatSelected['d/m/Y H:i']}>DD/MM/YYYY HH:mm</option>
                    <option value="m/d/Y H:i" {$formatSelected['m/d/Y H:i']}>MM/DD/YYYY HH:mm</option>
                    <option value="Y-m-d g:i A" {$formatSelected['Y-m-d g:i A']}>YYYY-MM-DD hh:mm AM/PM</option>
                </select>
            </div>
        HTML;
    }
} 