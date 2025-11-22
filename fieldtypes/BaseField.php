<?php
/**
 * BaseField for HaruCMS Fieldtypes
 *
 * All fieldtypes must be self-contained in their own folder and use admin CSS for styling.
 */
namespace HaruCMS\FieldTypes;

abstract class BaseField {
    protected $name;
    protected $label;
    protected $required;
    protected $help;
    protected $group;
    protected $value;
    protected $validationRules = [];

    public function __construct(array $config) {
        $this->name = $config['name'] ?? '';
        $this->label = $config['label'] ?? $this->name;
        $this->required = $config['required'] ?? false;
        $this->help = $config['help'] ?? '';
        $this->group = $config['group'] ?? 'Content';
        $this->value = $config['value'] ?? null;
        
        // Set validation rules
        if ($this->required) {
            $this->validationRules[] = 'required';
        }
    }

    // Get field type name
    abstract public function getType(): string;

    // Render the field's HTML
    abstract public function render(): string;

    // Validate field value
    abstract public function validate($value): array;

    // Get field's JavaScript
    abstract public function getJavaScript(): string;

    // Get field's CSS
    abstract public function getCSS(): string;

    // Get field's configuration for the admin panel
    abstract public function getAdminConfig(): array;

    // Get field's value
    public function getValue() {
        return $this->value;
    }

    // Set field's value
    public function setValue($value) {
        $this->value = $value;
    }

    // Get field's name
    public function getName(): string {
        return $this->name;
    }

    // Get field's label
    public function getLabel(): string {
        return $this->label;
    }

    // Check if field is required
    public function isRequired(): bool {
        return $this->required;
    }

    // Get field's help text
    public function getHelp(): string {
        return $this->help;
    }

    // Get field's group
    public function getGroup(): string {
        return $this->group;
    }

    // Get field's category for grouping in UI
    // Fieldtypes should override this to return their category
    public function getCategory(): string {
        return 'Misc'; // Default category
    }

    // Get field's configuration UI HTML for the collection editor
    // Fieldtypes should override this to provide their own configuration UI
    // Parameters:
    //   - $fieldIndex: The index of the field in the collection
    //   - $existingData: Existing field configuration data (if editing)
    // Returns: HTML string for the configuration UI
    public function getConfigUI($fieldIndex, $existingData = []): string {
        return ''; // Default: no configuration UI
    }

    // Get field's validation rules
    public function getValidationRules(): array {
        return $this->validationRules;
    }

    // Get field's schema for database
    abstract public function getSchema(): array;

    // Get field's default value
    abstract public function getDefaultValue();

    // Process field value before saving
    abstract public function processValue($value);

    // Process field value after loading
    abstract public function processLoadedValue($value);

    /**
     * Render a compact list/table cell representation for this field's value
     * Fieldtypes can override to provide richer summaries (e.g., thumbnails)
     */
    public function renderListCell($value, array $context = []): string {
        if (is_array($value)) {
            $count = count($value);
            return '<span class="ft-list-badge">' . $count . ' item' . ($count === 1 ? '' : 's') . '</span>';
        }

        if (is_object($value)) {
            return '<span class="ft-list-badge">object</span>';
        }

        $string = (string)($value ?? '');
        $string = trim($string);
        if ($string === '') {
            return '<span class="ft-list-empty">–</span>';
        }

        // Truncate long strings for list view
        $max = $context['truncate'] ?? 80;
        if (mb_strlen($string) > $max) {
            $string = mb_substr($string, 0, $max - 1) . '…';
        }
        // Basic escaping; allow minimal safe HTML via htmlspecialchars
        return '<span class="ft-list-text">' . htmlspecialchars($string, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    /**
     * Default list column width for this fieldtype in entries table.
     * Fieldtypes can override to fine-tune their preferred width.
     */
    public function getListColumnWidth(): string {
        return '240px';
    }
} 