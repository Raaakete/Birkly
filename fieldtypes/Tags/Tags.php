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
        
        $valueJson = json_encode($value);
        
        // Generate existing tag chips
        $existingChips = '';
        if (is_array($value)) {
            foreach ($value as $tag) {
                $tagHtml = htmlspecialchars($tag, ENT_QUOTES, 'UTF-8');
                $existingChips .= "<span class=\"tag-chip\">{$tagHtml}<span class=\"tag-remove\" onclick=\"window.HaruFieldtypes['tags'].removeTag('{$this->name}', '{$tagHtml}')\">×</span></span>";
            }
        }
        
        return <<<HTML
        <div class="tags-field-container" id="field_{$this->name}">
            <div class="tags-field" id="tags_field_{$this->name}">
                <div class="tags-chips">{$existingChips}</div>
                <input type="text" class="tags-field-input" id="field_{$this->name}_input" placeholder="Add tag and press Enter">
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
        /* Tags Fieldtype - Uses system CSS variables */
        .tags-field-container {
            margin-bottom: var(--space-4, 1rem);
        }
        
        .tags-field {
            border: 1px solid var(--border-control, var(--border-light, #e5e7eb));
            border-radius: var(--radius-md, 0.5rem);
            padding: var(--space-2, 0.5rem) var(--space-3, 0.75rem);
            background: var(--surface, var(--bg-primary, #ffffff));
            min-height: var(--control-md, 2.5rem);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: var(--space-2, 0.5rem);
        }
        
        .tags-field:focus-within {
            border-color: var(--accent, var(--primary-500, #3b82f6));
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent, #3b82f6) 15%, transparent);
        }
        
        .tags-chips {
            display: contents;
        }
        
        .tag-chip {
            background: var(--bg-secondary, #fafafa);
            border: 1px solid var(--border-medium, #e5e7eb);
            border-radius: var(--radius-round, 9999px);
            padding: var(--space-1, 0.25rem) var(--space-3, 0.75rem);
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: var(--space-2, 0.5rem);
            max-width: 200px;
            color: var(--text-primary, #20221D);
        }
        
        .tag-remove {
            cursor: pointer;
            color: var(--text-secondary, #72746F);
            font-weight: bold;
            font-size: 1rem;
            line-height: 1;
            padding: 0 var(--space-1, 0.25rem);
            border-radius: 50%;
            transition: all var(--transition-fast, 150ms ease);
        }
        
        .tag-remove:hover {
            background: var(--error-500, #ef4444);
            color: var(--text-inverse, #ffffff);
        }
        
        .tags-field-input,
        .tags-field input {
            border: none;
            outline: none;
            background: transparent;
            flex: 1 1 8rem;
            min-width: 8rem;
            width: auto;
            height: auto;
            min-height: 0;
            padding: var(--space-1, 0.25rem) 0;
            margin: 0;
            box-shadow: none;
            color: var(--text-primary, #20221D);
            font-size: var(--text-base, 1rem);
            line-height: var(--leading-snug, 1.375);
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
        $html = '<div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">';
        foreach ($tags as $tag) {
            $tagText = htmlspecialchars($tag, ENT_QUOTES, 'UTF-8');
            $html .= '<span class="ft-list-badge" style="font-size:12px;padding:4px 8px;">' . $tagText . '</span>';
        }
        if ($count > 3) {
            $html .= '<span class="ft-list-badge" style="font-size:12px;padding:4px 8px;background:var(--color-neutral-200, #f3f4f6);">+' . ($count - 3) . '</span>';
        }
        $html .= '</div>';
        
        return $html;
    }

    public function getTableMinWidth(): string {
        return '200px'; // Tags fields need space for multiple tag display
    }
} 