<?php
namespace HaruCMS\FieldTypes\MultiSelect;

use HaruCMS\FieldTypes\BaseField;

class MultiSelect extends BaseField {
    protected $options;
    protected $searchable; // Enable search functionality

    public function __construct(array $config) {
        parent::__construct($config);
        $this->options = $config['options'] ?? [];
        $this->searchable = $config['searchable'] ?? true;
    }

    public function getType(): string {
        return 'multiselect';
    }

    public function getCategory(): string {
        return 'Selection';
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
            border: 1px solid var(--border-color, #ddd);
            border-radius: 6px;
            padding: 12px;
            background: #fff;
        }
        
        .multiselect-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
            min-height: 40px;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .multiselect-badges:empty::before {
            content: "No options selected";
            color: #999;
            font-style: italic;
            font-size: 0.875rem;
        }
        
        .multiselect-badge {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            border-radius: 20px;
            padding: 6px 14px;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #1976d2;
        }
        
        .badge-remove {
            cursor: pointer;
            color: #1976d2;
            font-weight: bold;
            font-size: 18px;
            line-height: 1;
            padding: 0 4px;
            border-radius: 50%;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
        }
        
        .badge-remove:hover {
            background: #dc3545;
            color: white;
        }
        
        .multiselect-options-list {
            display: flex !important;
            flex-direction: column !important;
            gap: 0.375rem !important;
            max-height: 250px !important;
            overflow-y: auto !important;
            padding: 6px !important;
            background: #fafafa !important;
            border-radius: 4px !important;
        }
        
        .multiselect-option-item {
            background: #fff !important;
            border: 1px solid #e0e0e0 !important;
            border-radius: 3px !important;
            transition: all 0.2s !important;
        }
        
        .multiselect-option-item:hover {
            border-color: var(--primary, #007bff) !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
        }
        
        .multiselect-option-label {
            display: flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
            padding: 0.4375rem 0.625rem !important;
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
            color: #333 !important;
            user-select: none !important;
            line-height: 1.4 !important;
        }
        
        .multiselect-option-checkbox:checked + .multiselect-option-text {
            font-weight: 500;
            color: var(--primary, #007bff);
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

    public function getConfigUI($fieldIndex, $existingData = []): string {
        $options = $existingData['options'] ?? [];
        $searchable = !isset($existingData['searchable']) || !empty($existingData['searchable']);
        $searchableChecked = $searchable ? 'checked' : '';
        $optionsHtml = '';
        
        foreach ($options as $idx => $opt) {
            $label = htmlspecialchars($opt['label'] ?? $opt['value'] ?? '');
            $value = htmlspecialchars($opt['value'] ?? $opt['label'] ?? '');
            $optionsHtml .= <<<HTML
                <div class="option-item" data-option-index="{$idx}">
                    <input type="text" class="input option-label" placeholder="Label" value="{$label}" data-field="{$fieldIndex}" data-option="{$idx}">
                    <input type="text" class="input option-value" placeholder="Value" value="{$value}" data-field="{$fieldIndex}" data-option="{$idx}">
                    <button type="button" class="btn-icon remove-option-btn" data-field="{$fieldIndex}" data-option="{$idx}" title="Remove option">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
            HTML;
        }
        
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
                <small class="form-text">Add options for this multiselect field</small>
            </div>
            <div class="field-config-section">
                <label class="checkbox-label">
                    <input type="checkbox" name="fields[{$fieldIndex}][searchable]" value="1" {$searchableChecked}>
                    <span class="checkmark"></span>
                    <span>Enable Search</span>
                </label>
            </div>
            <script>
            (function() {
                const fieldIndex = '{$fieldIndex}';
                const optionsList = document.getElementById('options_list_' + fieldIndex);
                const addBtn = document.querySelector('.add-option-btn[data-field="' + fieldIndex + '"]');
                const newLabelInput = document.getElementById('new_option_label_' + fieldIndex);
                const newValueInput = document.getElementById('new_option_value_' + fieldIndex);
                
                if (!optionsList || !addBtn) return;
                
                const newAddBtn = addBtn.cloneNode(true);
                addBtn.parentNode.replaceChild(newAddBtn, addBtn);
                
                newAddBtn.addEventListener('click', function() {
                    const label = newLabelInput.value.trim();
                    if (!label) {
                        alert('Please enter an option label');
                        return;
                    }
                    
                    const value = newValueInput.value.trim() || label.toLowerCase().replace(/\s+/g, '_');
                    const optionIndex = optionsList.children.length;
                    
                    const optionHTML = '<div class="option-item" data-option-index="' + optionIndex + '">' +
                        '<input type="text" class="input option-label" placeholder="Label" value="' + label.replace(/"/g, '&quot;') + '" data-field="' + fieldIndex + '" data-option="' + optionIndex + '">' +
                        '<input type="text" class="input option-value" placeholder="Value" value="' + value.replace(/"/g, '&quot;') + '" data-field="' + fieldIndex + '" data-option="' + optionIndex + '">' +
                        '<button type="button" class="btn-icon remove-option-btn" data-field="' + fieldIndex + '" data-option="' + optionIndex + '" title="Remove option">' +
                        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                        '<line x1="18" y1="6" x2="6" y2="18"></line>' +
                        '<line x1="6" y1="6" x2="18" y2="18"></line>' +
                        '</svg></button></div>';
                    
                    optionsList.insertAdjacentHTML('beforeend', optionHTML);
                    newLabelInput.value = '';
                    newValueInput.value = '';
                    newLabelInput.focus();
                    
                    initRemoveButtons(fieldIndex);
                });
                
                function initRemoveButtons(fieldIndex) {
                    document.querySelectorAll('.remove-option-btn[data-field="' + fieldIndex + '"]').forEach(function(btn) {
                        const newBtn = btn.cloneNode(true);
                        btn.parentNode.replaceChild(newBtn, btn);
                        newBtn.addEventListener('click', function() {
                            const optionItem = this.closest('.option-item');
                            if (optionItem) {
                                optionItem.remove();
                            }
                        });
                    });
                }
                
                if (newLabelInput) {
                    newLabelInput.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            newAddBtn.click();
                        }
                    });
                }
                
                if (newValueInput) {
                    newValueInput.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            newAddBtn.click();
                        }
                    });
                }
                
                initRemoveButtons(fieldIndex);
            })();
            </script>
        HTML;
    }
}

