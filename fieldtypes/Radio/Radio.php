<?php
namespace HaruCMS\FieldTypes\Radio;

use HaruCMS\FieldTypes\BaseField;

class Radio extends BaseField {
    protected $options;
    protected $layout; // 'vertical' or 'horizontal'

    public function __construct(array $config) {
        parent::__construct($config);
        $this->options = $config['options'] ?? [];
        $this->layout = $config['layout'] ?? 'vertical';
    }

    public function getType(): string {
        return 'radio';
    }

    public function getCategory(): string {
        return 'Selection';
    }

    public function render(): string {
        $optionsHtml = '';
        $layoutClass = $this->layout === 'horizontal' ? 'radio-group-horizontal' : 'radio-group-vertical';
        
        foreach ($this->options as $opt) {
            $value = $opt['value'] ?? '';
            $label = $opt['label'] ?? $value;
            $checked = ($this->value == $value) ? 'checked' : '';
            $valueEscaped = htmlspecialchars($value);
            $labelEscaped = htmlspecialchars($label);
            $id = "field_{$this->name}_{$valueEscaped}";
            
            $optionsHtml .= <<<HTML
            <div class="radio-option">
                <input type="radio" 
                    name="{$this->name}" 
                    id="{$id}"
                    value="{$valueEscaped}"
                    {$checked}
                    class="radio-input"
                >
                <label for="{$id}" class="radio-label">{$labelEscaped}</label>
            </div>
            HTML;
        }
        
        return <<<HTML
        <div class="radio-field {$layoutClass}" id="field_{$this->name}">
            {$optionsHtml}
        </div>
        HTML;
    }

    public function validate($value): array {
        $errors = [];
        
        if ($this->required && empty($value)) {
            $errors[] = "{$this->label} is required";
            return $errors;
        }
        
        if ($value && !in_array($value, array_column($this->options, 'value'))) {
            $errors[] = "Invalid value for {$this->label}";
        }
        
        return $errors;
    }

    public function getJavaScript(): string {
        return '';
    }

    public function getCSS(): string {
        return <<<'CSS'
        .radio-field {
            display: flex !important;
            flex-direction: column !important;
            gap: 0.4375rem !important;
        }
        
        .radio-group-horizontal {
            flex-direction: row !important;
            flex-wrap: wrap !important;
            gap: 0.875rem !important;
        }
        
        .radio-option {
            display: flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
        }
        
        .radio-input {
            width: 16px !important;
            height: 16px !important;
            cursor: pointer !important;
            margin: 0 !important;
            flex-shrink: 0 !important;
            accent-color: var(--primary, #007bff) !important;
        }
        
        .radio-label {
            cursor: pointer !important;
            user-select: none !important;
            font-size: 0.875rem !important;
            line-height: 1.4 !important;
            color: #333 !important;
        }
        
        .radio-input:focus + .radio-label {
            outline: 2px solid var(--primary, #007bff) !important;
            outline-offset: 2px !important;
            border-radius: 2px !important;
        }
        CSS;
    }

    public function getAdminConfig(): array {
        return [
            'type' => 'radio',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            'options' => $this->options,
            'layout' => $this->layout
        ];
    }

    public function getSchema(): array {
        return [
            'type' => 'string',
            'required' => $this->required,
            'options' => $this->options
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

    public function getConfigUI($fieldIndex, $existingData = []): string {
        $options = $existingData['options'] ?? [];
        $layout = $existingData['layout'] ?? 'vertical';
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
        
        $layoutVertical = $layout === 'vertical' ? 'selected' : '';
        $layoutHorizontal = $layout === 'horizontal' ? 'selected' : '';
        
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
                <small class="form-text">Add options for this radio field</small>
            </div>
            <div class="field-config-section">
                <label>Layout</label>
                <select name="fields[{$fieldIndex}][layout]" class="input">
                    <option value="vertical" {$layoutVertical}>Vertical</option>
                    <option value="horizontal" {$layoutHorizontal}>Horizontal</option>
                </select>
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

