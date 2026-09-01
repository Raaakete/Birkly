<?php
namespace Birkly\FieldTypes\Radio;

use Birkly\FieldTypes\BaseField;

require_once dirname(__DIR__, 2) . '/core/content_locale.php';

class Radio extends BaseField {
    protected $options;
    protected $layout; // 'vertical' or 'horizontal'

    public function __construct(array $config) {
        parent::__construct($config);
        $lang = isset($config['language']) ? (string) $config['language'] : null;
        $this->options = birkly_selection_resolve_options($config['options'] ?? [], $lang);
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
            color: var(--text-primary, #20221D) !important;
        }
        
        .radio-input:focus + .radio-label {
            outline: 2px solid var(--primary-500, #3b82f6) !important;
            outline-offset: 2px !important;
            border-radius: var(--radius-xs, 0.125rem) !important;
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

    public function renderListCell($value, array $context = []): string {
        if (empty($value)) {
            return '<span class="ft-list-empty">–</span>';
        }
        
        // Find the label for this value
        $lang = isset($context['language']) ? (string) $context['language'] : null;
        $rawOptions = is_array($context['field']['options'] ?? null)
            ? $context['field']['options']
            : $this->options;
        $options = birkly_selection_resolve_options($rawOptions, $lang);
        $label = $value;
        foreach ($options as $opt) {
            if (($opt['value'] ?? '') === $value) {
                $label = $opt['label'] ?? $value;
                break;
            }
        }
        
        return '<span class="ft-list-text">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    public function getTableMinWidth(): string {
        return '150px'; // Radio fields need space for option label display
    }

    public function getConfigUI($fieldIndex, $existingData = []): string {
        $options = $existingData['options'] ?? [];
        $languages = $existingData['_multilanguage_languages'] ?? [];
        if (!is_array($languages)) {
            $languages = [];
        }
        $optionsHtml = birkly_selection_options_config_rows($fieldIndex, $options, $languages);
        $layout = $existingData['layout'] ?? 'vertical';
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
                <small class="form-text">Default label is fallback. Add translated labels per language when multilanguage is enabled.</small>
            </div>
            <div class="field-config-section">
                <label>Layout</label>
                <select name="fields[{$fieldIndex}][layout]" class="input">
                    <option value="vertical" {$layoutVertical}>Vertical</option>
                    <option value="horizontal" {$layoutHorizontal}>Horizontal</option>
                </select>
            </div>
        HTML;
    }
}

