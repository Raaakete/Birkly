<?php
namespace Birkly\FieldTypes\Tags;

use Birkly\FieldTypes\BaseField;

class Tags extends BaseField {
    public function getType(): string {
        return 'tags';
    }

    public function getCategory(): string {
        return 'Content & Text';
    }

    public function getIcon(): string {
        return 'tag';
    }

    public function getDescription(): string {
        return 'Free keywords for grouping';
    }

    public function getLabelForId(): string {
        // The visible, focusable control is the inline tag input.
        return 'field_' . $this->name . '_input';
    }

    public function render(): string {
        $value = $this->value ?? [];
        
        // Handle case where value might be a JSON string from admin engine
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = [];
            }
        }
        
        // Ensure value is always an array
        if (!is_array($value)) {
            $value = [];
        }
        
        $valueJson = htmlspecialchars((string) json_encode($value), ENT_QUOTES, 'UTF-8');
        
        // Generate existing tag chips
        $existingChips = '';
        if (is_array($value)) {
            foreach ($value as $tag) {
                $tagHtml = htmlspecialchars($tag, ENT_QUOTES, 'UTF-8');
                $fieldAttr = htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8');
                $existingChips .= "<span class=\"tag-chip\">{$tagHtml}<button type=\"button\" class=\"tag-remove\" data-tags-field=\"{$fieldAttr}\" data-tag=\"{$tagHtml}\" aria-label=\"Remove {$tagHtml}\">×</button></span>";
            }
        }
        
        return <<<HTML
        <div class="tags-field-container" id="field_{$this->name}">
            <div class="tags-field" id="tags_field_{$this->name}">
                <div class="tags-chips">{$existingChips}</div>
                <input type="text" class="tags-field-input" id="field_{$this->name}_input" placeholder="Add a tag">
            </div>
            <input type="hidden" name="{$this->name}" id="field_{$this->name}_value" value="{$valueJson}">
        </div>
        HTML;
    }

    public function validate($value): array {
        $errors = [];
        if ($this->required && (empty($value) || !is_array($value) || count($value) === 0)) {
            $errors[] = $this->label . ' is required';
        }
        return $errors;
    }

    public function getAdminConfig(): array {
        return [];
    }

    public static function getConfig(): array {
        return [];
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
        /* Tags are wells in a row; the add input is a small grey field at the end. */
        .tags-field {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: var(--s-1);
            min-height: var(--control);
        }
        .tags-chips { display: contents; }
        .tag-chip {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            height: 24px;
            max-width: 200px;
            padding: 0 2px 0 var(--s-2);
            border-radius: var(--r-inset);
            background: var(--surface-2);
            color: var(--text);
            font: var(--weight-medium) var(--size-small) / 1 var(--font-sans);
        }
        .tag-remove {
            padding: 0;
            border: 0;
            background: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            border-radius: var(--r-inset);
            color: var(--text-3);
            font-size: var(--size-body);
            line-height: 1;
            cursor: pointer;
        }
        .tag-remove:hover { background: var(--surface-3); color: var(--danger); }
        .tags-field-input,
        .tags-field input {
            flex: 0 1 12rem;
            width: auto;
            min-width: 8rem;
            height: 24px;
            margin: 0;
            padding: 0 var(--s-2);
            border: 1px solid transparent;
            border-radius: var(--r-inset);
            background: var(--surface-2);
            box-shadow: none;
            color: var(--text);
            font: var(--weight-regular) var(--size-small) / 1 var(--font-sans);
        }
        .tags-field-input::placeholder { color: var(--text-3); }
        .tags-field-input:focus {
            outline: none;
            border-color: var(--accent);
            background: var(--surface);
        }
        CSS;
    }
    public function getSchema(): array {
        return [];
    }
    public function getDefaultValue() {
        return [];
    }
    public function processValue($value) {
        // Handle JSON strings from form submission
        if (is_string($value)) {
            // Try to decode JSON
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
            // If not JSON, treat as single tag (backward compatibility)
            $trimmed = trim($value);
            if ($trimmed !== '') {
                return [$trimmed];
            }
        }
        
        // Ensure we return an array
        if (is_array($value)) {
            return array_values(array_filter($value, function($tag) {
                return !empty(trim($tag));
            }));
        }
        
        return [];
    }
    public function processLoadedValue($value) {
        return $value;
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
        
        // Show tags as badges (larger size)
        $tags = array_slice($value, 0, 3);
        $html = '<span class="ft-list-row">';
        foreach ($tags as $tag) {
            $tagText = htmlspecialchars($tag, ENT_QUOTES, 'UTF-8');
            $html .= '<span class="ft-list-badge">' . $tagText . '</span>';
        }
        if ($count > 3) {
            $html .= '<span class="ft-list-muted">+' . ($count - 3) . '</span>';
        }
        $html .= '</span>';
        
        return $html;
    }

    public function getTableMinWidth(): string {
        return '200px'; // Tags fields need space for multiple tag display
    }
} 