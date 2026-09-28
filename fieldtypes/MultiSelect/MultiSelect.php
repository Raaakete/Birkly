<?php
namespace Birkly\FieldTypes\MultiSelect;

use Birkly\FieldTypes\BaseField;

require_once dirname(__DIR__, 2) . '/core/content_locale.php';

class MultiSelect extends BaseField {
    protected $options;
    protected $searchable; // Enable search functionality

    public function __construct(array $config) {
        parent::__construct($config);
        $lang = isset($config['language']) ? (string) $config['language'] : null;
        $this->options = birkly_selection_resolve_options($config['options'] ?? [], $lang);
        $this->searchable = $config['searchable'] ?? true;
    }

    public function getType(): string {
        return 'multiselect';
    }

    public function getCategory(): string {
        return 'Selection';
    }

    public function getTypeLabel(): string {
        return 'Multi-select';
    }

    public function getIcon(): string {
        return 'list';
    }

    public function getDescription(): string {
        return 'Several choices from a list';
    }

    public function render(): string {
        $value = $this->value ?? [];
        
        // Handle case where value might be a JSON string
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = [];
            }
        }
        
        if (!is_array($value)) {
            $value = [];
        }
        
        $valueJson = json_encode($value);
        $searchable = $this->searchable ? 'true' : 'false';
        
        // Build options HTML
        $optionsHtml = '';
        foreach ($this->options as $opt) {
            $optValue = $opt['value'] ?? '';
            $optLabel = $opt['label'] ?? $optValue;
            $selected = in_array($optValue, $value) ? 'selected' : '';
            $valueEscaped = htmlspecialchars($optValue);
            $labelEscaped = htmlspecialchars($optLabel);
            
            $optionsHtml .= "<option value=\"{$valueEscaped}\" {$selected}>{$labelEscaped}</option>";
        }
        
        // Build selected badges
        $badgesHtml = '';
        foreach ($value as $val) {
            $opt = array_filter($this->options, function($o) use ($val) {
                return ($o['value'] ?? '') === $val;
            });
            $opt = reset($opt);
            $label = $opt ? ($opt['label'] ?? $val) : $val;
            $valEscaped = htmlspecialchars($val);
            $labelEscaped = htmlspecialchars($label);
            
            $badgesHtml .= <<<HTML
            <span class="multiselect-badge" data-value="{$valEscaped}">
                {$labelEscaped}
                <span class="badge-remove" onclick="window.HaruFieldtypes['multiselect'].removeValue('{$this->name}', '{$valEscaped}')">×</span>
            </span>
            HTML;
        }
        
        // Build options list HTML
        $optionsListHtml = '';
        foreach ($this->options as $opt) {
            $optValue = $opt['value'] ?? '';
            $optLabel = $opt['label'] ?? $optValue;
            $checked = in_array($optValue, $value) ? 'checked' : '';
            $valueEscaped = htmlspecialchars($optValue);
            $labelEscaped = htmlspecialchars($optLabel);
            $id = "multiselect_option_{$this->name}_{$valueEscaped}";
            
            $optionsListHtml .= <<<HTML
                <div class="multiselect-option-item">
                    <label for="{$id}" class="multiselect-option-label">
                        <input type="checkbox" 
                            id="{$id}"
                            value="{$valueEscaped}"
                            class="multiselect-option-checkbox"
                            {$checked}
                            data-field="{$this->name}"
                        >
                        <span class="multiselect-option-text">{$labelEscaped}</span>
                    </label>
                </div>
            HTML;
        }
        
        return <<<HTML
        <div class="multiselect-field" id="multiselect_field_{$this->name}">
            <div class="multiselect-badges" id="multiselect_badges_{$this->name}">
                {$badgesHtml}
            </div>
            <div class="multiselect-options-list" id="multiselect_options_{$this->name}">
                {$optionsListHtml}
            </div>
            <input type="hidden" name="{$this->name}" id="field_{$this->name}_value" value="{$valueJson}">
        </div>
        HTML;
    }

    public function validate($value): array {
        $errors = [];
        
        if ($this->required && (empty($value) || !is_array($value) || count($value) === 0)) {
            $errors[] = "{$this->label} is required";
            return $errors;
        }
        
        if (is_array($value)) {
            $validValues = array_column($this->options, 'value');
            foreach ($value as $val) {
                if (!in_array($val, $validValues)) {
                    $errors[] = "Invalid value in {$this->label}: {$val}";
                }
            }
        }
        
        return $errors;
    }

    public function getJavaScript(): string {
        $jsPath = __DIR__ . '/fieldtype.js';
        if (file_exists($jsPath)) {
            return file_get_contents($jsPath);
        }
        return '';
    }

    public function getCSS(): string {
        return <<<'CSS'
        .multiselect-field {
            border: 1px solid var(--border-medium, #e5e7eb);
            border-radius: var(--radius-md, 0.5rem);
            padding: var(--space-3, 0.75rem);
            background: var(--bg-primary, #ffffff);
        }
        
        .multiselect-badges {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2, 0.5rem);
            margin-bottom: var(--space-3, 0.75rem);
            min-height: 40px;
            padding: var(--space-2, 0.5rem);
            background: var(--bg-secondary, #fafafa);
            border-radius: var(--radius-sm, 0.375rem);
        }
        
        .multiselect-badges:empty::before {
            content: "No options selected";
            color: var(--text-tertiary, #9ca3af);
            font-style: italic;
            font-size: 0.875rem;
        }
        
        .multiselect-badge {
            background: var(--primary-50, #eff6ff);
            border: 1px solid var(--primary-200, #bfdbfe);
            border-radius: var(--radius-full, 9999px);
            padding: var(--space-2, 0.5rem) var(--space-4, 1rem);
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: var(--space-2, 0.5rem);
            color: var(--primary-700, #1d4ed8);
        }
        
        .badge-remove {
            cursor: pointer;
            color: var(--primary-700, #1d4ed8);
            font-weight: bold;
            font-size: 1.125rem;
            line-height: 1;
            padding: 0 var(--space-1, 0.25rem);
            border-radius: var(--radius-full, 50%);
            transition: all var(--transition-fast, 150ms ease);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
        }
        
        .badge-remove:hover {
            background: var(--error-500, #ef4444);
            color: var(--text-inverse, #ffffff);
        }
        
        .multiselect-options-list {
            display: flex !important;
            flex-direction: column !important;
            gap: var(--space-2, 0.5rem) !important;
            max-height: 250px !important;
            overflow-y: auto !important;
            padding: var(--space-2, 0.5rem) !important;
            background: var(--bg-secondary, #fafafa) !important;
            border-radius: var(--radius-sm, 0.375rem) !important;
        }
        
        .multiselect-option-item {
            background: var(--bg-primary, #ffffff) !important;
            border: 1px solid var(--border-light, #f3f4f6) !important;
            border-radius: var(--radius-sm, 0.375rem) !important;
            transition: all var(--transition-fast, 150ms ease) !important;
        }
        
        .multiselect-option-item:hover {
            border-color: var(--primary-500, #3b82f6) !important;
            box-shadow: var(--shadow-sm, 0 1px 3px rgba(0, 0, 0, 0.1)) !important;
        }
        
        .multiselect-option-label {
            display: flex !important;
            align-items: center !important;
            gap: var(--space-2, 0.5rem) !important;
            padding: var(--space-2, 0.5rem) var(--space-3, 0.75rem) !important;
            cursor: pointer !important;
            margin: 0 !important;
        }
        
        .multiselect-option-checkbox {
            width: 16px !important;
            height: 16px !important;
            cursor: pointer !important;
            flex-shrink: 0 !important;
            margin: 0 !important;
        }
        
        .multiselect-option-text {
            font-size: 0.875rem !important;
            color: var(--text-primary, #20221D) !important;
            user-select: none !important;
            line-height: 1.4 !important;
        }
        
        .multiselect-option-checkbox:checked + .multiselect-option-text {
            font-weight: 500;
            color: var(--primary-500, #3b82f6);
        }
        CSS;
    }

    public function getAdminConfig(): array {
        return [
            'type' => 'multiselect',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            'options' => $this->options,
            'searchable' => $this->searchable
        ];
    }

    public function getSchema(): array {
        return [
            'type' => 'array',
            'required' => $this->required,
            'items' => [
                'type' => 'string'
            ]
        ];
    }

    public function getDefaultValue() {
        return [];
    }

    public function processValue($value) {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        
        if (is_array($value)) {
            return array_values($value); // Re-index array
        }
        
        return [];
    }

    public function processLoadedValue($value) {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        
        return is_array($value) ? $value : [];
    }

    public function renderListCell($value, array $context = []): string {
        // Handle case where value might be a JSON string
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = [];
            }
        }
        
        if (!is_array($value)) {
            $value = [];
        }
        
        $count = count($value);
        if ($count === 0) {
            return '<span class="ft-list-empty">–</span>';
        }
        
        // Show selected option labels
        $lang = isset($context['language']) ? (string) $context['language'] : null;
        $rawOptions = is_array($context['field']['options'] ?? null)
            ? $context['field']['options']
            : $this->options;
        $options = birkly_selection_resolve_options($rawOptions, $lang);
        $labels = [];
        foreach (array_slice($value, 0, 3) as $val) {
            foreach ($options as $opt) {
                if (($opt['value'] ?? '') === $val) {
                    $labels[] = $opt['label'] ?? $val;
                    break;
                }
            }
        }
        
        $previewText = implode(', ', $labels);
        if ($count > 3) {
            $previewText .= '...';
        }
        
        return '<span class="ft-list-badge">' . $count . ' selected</span> <span class="ft-list-text" style="font-size:11px;color:var(--text-tertiary, #9ca3af);margin-left:4px;">(' . htmlspecialchars($previewText, ENT_QUOTES, 'UTF-8') . ')</span>';
    }

    public function getTableMinWidth(): string {
        return '180px'; // MultiSelect fields need space for multiple selected items
    }

    public function getConfigUI($fieldIndex, $existingData = []): string {
        $options = $existingData['options'] ?? [];
        $languages = $existingData['_multilanguage_languages'] ?? [];
        if (!is_array($languages)) {
            $languages = [];
        }
        $optionsHtml = birkly_selection_options_config_rows($fieldIndex, $options, $languages);
        $searchable = !isset($existingData['searchable']) || !empty($existingData['searchable']);
        $searchableChecked = $searchable ? 'checked' : '';
        
        return <<<HTML
            <div class="field-config-section">
                <label>Options</label>
                <div class="options-list" id="options_list_{$fieldIndex}">
                    {$optionsHtml}
                </div>
                <div class="add-option-controls">
                    <input type="text" class="input new-option-label" placeholder="Option label" id="new_option_label_{$fieldIndex}">
                    <input type="text" class="input new-option-value" placeholder="Option value (optional)" id="new_option_value_{$fieldIndex}">
                    <button type="button" class="btn btn-secondary add-option-btn" data-field="{$fieldIndex}">Add Option</button>
                </div>
                <small class="form-text">Default label is fallback. Add translated labels per language when multilanguage is enabled.</small>
            </div>
            <div class="field-config-section">
                <label class="checkbox-label">
                    <input type="checkbox" name="fields[{$fieldIndex}][searchable]" value="1" {$searchableChecked}>
                    <span class="checkmark"></span>
                    <span>Enable Search</span>
                </label>
            </div>
        HTML;
    }
}

