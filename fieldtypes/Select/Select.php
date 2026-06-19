<?php
namespace Birkly\FieldTypes\Select;

use Birkly\FieldTypes\BaseField;

class Select extends BaseField {
    protected $options;

    public function __construct(array $config) {
        parent::__construct($config);
        $this->options = self::normalizeOptions($config['options'] ?? []);
    }

    /**
     * Accept label/value objects or flat strings (legacy comma-separated saves).
     *
     * @param array<int, string|array{label?: string, value?: string}> $options
     * @return array<int, array{label: string, value: string}>
     */
    public static function normalizeOptions(array $options): array {
        $normalized = [];
        foreach ($options as $opt) {
            if (is_string($opt)) {
                $opt = trim($opt);
                if ($opt === '') {
                    continue;
                }
                $normalized[] = [
                    'label' => $opt,
                    'value' => strtolower(preg_replace('/[^a-z0-9]+/', '_', $opt)),
                ];
                continue;
            }
            if (!is_array($opt)) {
                continue;
            }
            $value = (string)($opt['value'] ?? $opt['label'] ?? '');
            $label = (string)($opt['label'] ?? $value);
            if ($value === '' && $label === '') {
                continue;
            }
            $normalized[] = ['label' => $label, 'value' => $value !== '' ? $value : $label];
        }
        return $normalized;
    }

    public function getType(): string {
        return 'select';
    }

    public function getCategory(): string {
        return 'Selection';
    }

    public function render(): string {
        $required = $this->required ? 'required' : '';
        $optionsHtml = '<option value="">Select ' . htmlspecialchars($this->label) . '</option>';
        foreach ($this->options as $opt) {
            $selected = ($this->value == $opt['value']) ? 'selected' : '';
            $optionsHtml .= '<option value="' . htmlspecialchars($opt['value']) . '" ' . $selected . '>' . htmlspecialchars($opt['label']) . '</option>';
        }
        return <<<HTML
        <select name="{$this->name}" id="field_{$this->name}" class="input" {$required}>
            {$optionsHtml}
        </select>
        HTML;
    }

    public function validate($value): array {
        $errors = [];
        if ($this->required && empty($value)) {
            $errors[] = "{$this->label} is required";
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
        return '';
    }

    public function getAdminConfig(): array {
        return [
            'type' => 'select',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            'options' => $this->options
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

    public function renderListCell($value, array $context = []): string {
        // Select shows the selected option label
        if (empty($value)) {
            return '<span class="ft-list-empty">–</span>';
        }
        
        // Try to find the label for this value
        $label = $value;
        foreach ($this->options as $opt) {
            if (($opt['value'] ?? '') === $value) {
                $label = $opt['label'] ?? $value;
                break;
            }
        }
        
        return '<span class="ft-list-text">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    public function getTableMinWidth(): string {
        return '150px'; // Select fields need moderate space for option labels
    }

    public function getConfigUI($fieldIndex, $existingData = []): string {
        $options = $existingData['options'] ?? [];
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
                <small class="form-text">Add options for this select field</small>
            </div>
            <script>
            (function() {
                const fieldIndex = '{$fieldIndex}';
                const optionsList = document.getElementById('options_list_' + fieldIndex);
                const addBtn = document.querySelector('.add-option-btn[data-field="' + fieldIndex + '"]');
                const newLabelInput = document.getElementById('new_option_label_' + fieldIndex);
                const newValueInput = document.getElementById('new_option_value_' + fieldIndex);
                
                if (!optionsList || !addBtn) return;
                
                // Remove existing listeners by cloning
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