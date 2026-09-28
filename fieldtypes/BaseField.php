<?php
/**
 * BaseField for Birkly Fieldtypes
 *
 * All fieldtypes must be self-contained in their own folder and use admin CSS for styling.
 * 
 * CSS Development Guidelines:
 * - Use system CSS variables from core/variables.css (never hardcode colors/spacing)
 * - Use system classes from components/ (buttons, forms, etc.) when possible
 * - Only add custom CSS for fieldtype-specific functionality
 * - Scope all styles to your fieldtype wrapper class
 * - See docs/FIELDTYPE_CSS_GUIDE.md for complete guidelines
 */
namespace Birkly\FieldTypes;

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

    /**
     * Get the preferred control id for the field label's "for" attribute.
     * Fieldtypes can override this when the primary interactive control is not
     * the default field_{name} input.
     */
    public function getLabelForId(): string {
        return 'field_' . $this->name;
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

    // Birkly icon name shown in the collection builder (core/icon_data.php).
    // Empty string lets the admin fall back to the generic fieldtype icon.
    public function getIcon(): string {
        return '';
    }

    // Sentence-case type name for the admin. Empty string falls back to the folder name.
    public function getTypeLabel(): string {
        return '';
    }

    // One-line, sentence-case hint shown in the Add a field dialog.
    public function getDescription(): string {
        return '';
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
     * Whether this fieldtype stores a value on the entry JSON file.
     * Virtual fieldtypes (e.g. plugin-backed relationship panels) return false.
     */
    public function persistsInEntry(): bool
    {
        return true;
    }

    /**
     * Resolve display value for virtual fieldtypes when loading an entry.
     *
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $fieldConfig
     * @param array<string, mixed> $context
     */
    public function resolveValueForEntry(array $entry, array $fieldConfig, array $context = [])
    {
        $name = (string) ($fieldConfig['name'] ?? $this->name);
        return $entry[$name] ?? null;
    }

    /**
     * Optional hook after a non-virtual entry save completes.
     *
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $fieldConfig
     * @param array<string, mixed> $context
     */
    public function onEntrySaved(array $entry, array $fieldConfig, array $context = []): void
    {
    }

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

        // Truncate long strings for list view with "..." at the end
        $max = $context['truncate'] ?? 80;
        if (mb_strlen($string) > $max) {
            $string = mb_substr($string, 0, $max - 3) . '...';
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

    /**
     * Minimum width for this fieldtype's table cell.
     * Fieldtypes can override to provide their minimum required width.
     * This is used for CSS min-width to ensure cells don't shrink too much.
     */
    public function getTableMinWidth(): string {
        // Default to the same as getListColumnWidth for backward compatibility
        return $this->getListColumnWidth();
    }

    /**
     * Whether template output for this field type is trusted HTML (not escaped).
     */
    public function outputsTrustedHtml(): bool
    {
        return false;
    }

    /**
     * Whether values must never be exposed via public API / MCP reads.
     */
    public function isSensitive(): bool
    {
        return false;
    }

    /**
     * Mask a stored value for API/MCP consumers.
     *
     * @param array<string, mixed> $fieldConfig
     */
    public function maskValueForApiRead($value, array $fieldConfig = [])
    {
        return $value;
    }

    /**
     * Format a field value for direct template tags like {'field_name'} when the
     * stored value is structured (arrays, objects).
     *
     * @param array<string, mixed> $fieldConfig
     * @param array<string, mixed> $context
     */
    public function formatValueForTemplate($value, array $fieldConfig = [], array $context = []): string
    {
        if ($value === null) {
            return '';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                if (is_scalar($item) || $item === null) {
                    $parts[] = (string) $item;
                }
            }
            return implode(', ', array_filter($parts, static fn ($p) => $p !== ''));
        }

        return '';
    }
} 