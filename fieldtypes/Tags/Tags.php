<?php
namespace HaruCMS\FieldTypes\Tags;

use HaruCMS\FieldTypes\BaseField;

class Tags extends BaseField {
    public function getType(): string {
        return 'tags';
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
                $existingChips .= "<div class=\"tag-chip\">{$tagHtml}<span class=\"tag-remove\" onclick=\"window.HaruFieldtypes['tags'].removeTag('{$this->name}', '{$tagHtml}')\">×</span></div>";
            }
        }
        
        return <<<HTML
        <div class="tags-field-container" id="field_{$this->name}">
            <div class="tags-field" id="tags_field_{$this->name}">
                <div class="tags-chips">{$existingChips}</div>
                <input type="text" class="input" id="field_{$this->name}_input" placeholder="Add tag and press Enter">
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
        .tags-field-container {
            margin-bottom: 1rem;
        }
        
        .tags-field {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px;
            background: #fff;
            min-height: 40px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
        }
        
        .tags-field:focus-within {
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }
        
        .tags-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            flex: 1;
        }
        
        .tag-chip {
            background: #e9ecef;
            border: 1px solid #ced4da;
            border-radius: 16px;
            padding: 4px 12px;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            max-width: 200px;
        }
        
        .tag-remove {
            cursor: pointer;
            color: #6c757d;
            font-weight: bold;
            font-size: 16px;
            line-height: 1;
            padding: 0 2px;
            border-radius: 50%;
            transition: all 0.2s;
        }
        
        .tag-remove:hover {
            background: #dc3545;
            color: white;
        }
        
        .tags-field input {
            border: none;
            outline: none;
            background: transparent;
            flex: 1;
            min-width: 120px;
            padding: 4px 0;
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
        return $value;
    }
    public function processLoadedValue($value) {
        return $value;
    }
} 