<?php
namespace Birkly\FieldTypes\Select;

use Birkly\FieldTypes\BaseField;

require_once dirname(__DIR__, 2) . '/core/content_locale.php';

class Select extends BaseField {
    protected $options;

    public function __construct(array $config) {
        parent::__construct($config);
        $lang = isset($config['language']) ? (string) $config['language'] : null;
        $this->options = birkly_selection_resolve_options(
            self::normalizeOptions($config['options'] ?? []),
            $lang
        );
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

    public function getIcon(): string {
        return 'chev-down';
    }

    public function getDescription(): string {
        return 'One choice from a dropdown';
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
        return '150px'; // Select fields need moderate space for option labels
    }

    public function getConfigUI($fieldIndex, $existingData = []): string {
        $options = $existingData['options'] ?? [];
        $languages = $existingData['_multilanguage_languages'] ?? [];
        if (!is_array($languages)) {
            $languages = [];
        }
        $optionsHtml = birkly_selection_options_config_rows($fieldIndex, $options, $languages);
        
        return <<<HTML
            <div class="field-config-section">
                <label>Options</label>
                <div class="options-list" id="options_list_{$fieldIndex}">
                    {$optionsHtml}
                </div>
                <div class="add-option-controls">
                    <input type="text" class="input new-option-label" placeholder="Option label" id="new_option_label_{$fieldIndex}">
                    <input type="text" class="input new-option-value" placeholder="Option value (optional)" id="new_option_value_{$fieldIndex}">
                    <button type="button" class="button add-option-btn" data-field="{$fieldIndex}">Add Option</button>
                </div>
                <small class="form-text">Default label is used as fallback. When multilanguage is enabled, add translated labels per language below each option.</small>
            </div>
        HTML;
    }
}