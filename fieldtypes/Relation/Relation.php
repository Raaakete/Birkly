<?php
namespace HaruCMS\FieldTypes\Relation;

use HaruCMS\FieldTypes\BaseField;

class Relation extends BaseField {
    protected $collection;
    protected $multiple;
    protected $options;
    
    public function __construct(array $config) {
        parent::__construct($config);
        $this->collection = $config['collection'] ?? '';
        $this->multiple = $config['multiple'] ?? false;
        $this->options = $config['options'] ?? [];
    }
    
    public function getType(): string { return 'relation'; }

    public function getCategory(): string {
        return 'Misc';
    }
    public function render(): string {
        $value = $this->value ?? [];
        
        // Handle case where value is already a JSON string
        if (is_string($value) && (str_starts_with($value, '[') || str_starts_with($value, '{'))) {
            try {
                $decoded = json_decode($value, true);
                if ($decoded !== null) {
                    $value = $decoded;
                }
            } catch (Exception $e) {
                // If JSON decode fails, use as is
            }
        }
        
        // For the hidden input, we need to store it as JSON string
        $valueJson = json_encode($value);
        // Properly escape for HTML attribute
        $valueJsonEscaped = htmlspecialchars($valueJson, ENT_QUOTES, 'UTF-8');
        $isMultiple = $this->multiple;
        $selectText = $isMultiple ? 'Add Relations' : 'Select Entry';
        
        // Get filter and display options
        $filterOptions = json_encode($this->options['filters'] ?? []);
        $displayOptions = json_encode($this->options['display'] ?? ['mode' => 'list']);
        $sortOptions = json_encode($this->options['sort'] ?? []);
        $limit = $this->options['limit'] ?? 0;
        
        return <<<HTML
        <div class="relation-field" id="relation_field_{$this->name}" 
             data-filters='{$filterOptions}' 
             data-display='{$displayOptions}' 
             data-sort='{$sortOptions}' 
             data-limit="{$limit}">
            <div class="relation-list" id="relation_list_{$this->name}">
                <div class="text-muted">No relations selected</div>
            </div>
            <input type="hidden" name="{$this->name}" id="field_{$this->name}_value" value="{$valueJsonEscaped}">
            <div class="relation-controls">
                <button type="button" class="btn btn-primary select-relation-btn" data-field="{$this->name}" data-multiple="{$isMultiple}">
                    <i class="icon-plus"></i> {$selectText}
                </button>
                <button type="button" class="btn btn-danger remove-all-relation-btn" data-field="{$this->name}" style="display:none;">
                    <i class="icon-trash"></i> Remove All
                </button>
            </div>
        </div>
        HTML;
    }
    public function validate($value): array { return []; }
    public function getJavaScript(): string {
        $jsPath = __DIR__ . '/fieldtype.js';
        if (file_exists($jsPath)) {
            $js = file_get_contents($jsPath);
            
            // Add initialization function and expose it globally
            $js .= "\n\n// Auto-initialize relation fields\n";
            $js .= "function initRelationField(fieldName) {\n";
            $js .= "    console.log('[Relation Field] initRelationField called for:', fieldName);\n";
            $js .= "    const field = document.getElementById('relation_field_' + fieldName);\n";
            $js .= "    if (!field) {\n";
            $js .= "        console.error('[Relation Field] Field not found:', 'relation_field_' + fieldName);\n";
            $js .= "        return;\n";
            $js .= "    }\n";
            $js .= "    \n";
            $js .= "    // Load available collections\n";
            $js .= "    loadRelationCollections(fieldName);\n";
            $js .= "    \n";
            $js .= "    const hiddenInput = field.querySelector('input[type=\"hidden\"]');\n";
            $js .= "    if (hiddenInput && hiddenInput.value) {\n";
            $js .= "        console.log('[Relation Field] Hidden input value:', hiddenInput.value);\n";
            $js .= "        try {\n";
            $js .= "            const value = JSON.parse(hiddenInput.value);\n";
            $js .= "            console.log('[Relation Field] Parsed value:', value);\n";
            $js .= "            if (value && (Array.isArray(value) ? value.length > 0 : value)) {\n";
            $js .= "                console.log('[Relation Field] Calling setValue with:', value);\n";
            $js .= "                window.HaruFieldtypes['relation'].setValue(fieldName, value);\n";
            $js .= "            }\n";
            $js .= "        } catch (e) {\n";
            $js .= "            console.error('[Relation Field] Failed to parse JSON:', e);\n";
            $js .= "            // If not JSON, try as single value\n";
            $js .= "            if (hiddenInput.value.trim()) {\n";
            $js .= "                console.log('[Relation Field] Using as string value:', hiddenInput.value);\n";
            $js .= "                window.HaruFieldtypes['relation'].setValue(fieldName, hiddenInput.value);\n";
            $js .= "            }\n";
            $js .= "        }\n";
            $js .= "    } else {\n";
            $js .= "        console.log('[Relation Field] No hidden input or no value');\n";
            $js .= "    }\n";
            $js .= "}\n";
            
            // Add collection loading function
            $js .= "\n// Load available collections for relation field\n";
            $js .= "async function loadRelationCollections(fieldName) {\n";
            $js .= "    const select = document.getElementById('relation_collection_' + fieldName);\n";
            $js .= "    if (!select) return;\n";
            $js .= "    \n";
            $js .= "    try {\n";
            $js .= "        const response = await fetch('/api/content.php?action=list_collections');\n";
            $js .= "        const data = await response.json();\n";
            $js .= "        \n";
            $js .= "        if (data.status === 'ok' && data.data && data.data.collections) {\n";
            $js .= "            select.innerHTML = '<option value=\"\">Select Collection...</option>';\n";
            $js .= "            data.data.collections.forEach(collection => {\n";
            $js .= "                select.innerHTML += `<option value=\"\${collection.id}\">\${collection.title} (\${collection.slug})</option>`;\n";
            $js .= "            });\n";
            $js .= "        }\n";
            $js .= "    } catch (error) {\n";
            $js .= "        console.error('Failed to load collections:', error);\n";
            $js .= "    }\n";
            $js .= "}\n";
            
            // Expose the function globally
            $js .= "\n// Expose initialization function globally\n";
            $js .= "window.initRelationField = initRelationField;\n";
            $js .= "console.log('[Relation Field] initRelationField exposed globally');\n";
            
            return $js;
        }
        return '';
    }
    public function getCSS(): string {
        return <<<'CSS'
        .relation-field {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 16px;
            background: #f8f9fa;
            margin-bottom: 1rem;
        }
        
        .relation-list {
            margin-bottom: 16px;
            min-height: 60px;
            padding: 12px;
            background: #fff;
            border: 2px dashed #dee2e6;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .relation-list:empty::before {
            content: "No relations selected";
            color: #6c757d;
            font-style: italic;
            text-align: center;
            display: block;
            padding: 20px;
        }
        
        .relation-entry {
            padding: 12px 16px;
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 6px;
            margin-bottom: 8px;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
        }
        
        .relation-entry:hover {
            background: #d1ecf1;
            border-color: #a8d8e0;
        }
        
        .relation-entry:last-child {
            margin-bottom: 0;
        }
        
        .relation-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .relation-title {
            font-weight: 500;
            color: #2c3e50;
        }
        
        .collection-info {
            font-size: 12px;
            color: #6c757d;
            font-style: italic;
        }
        
        .relation-controls {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .select-relation-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            font-weight: 500;
        }
        
        .remove-all-relation-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
        }
        
        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
            border-radius: 4px;
        }
        
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }
        
        .card-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 16px 20px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .card-footer {
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
            padding: 16px 20px;
        }
        
        .relation-picker-item {
            cursor: pointer;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 8px;
            border: 1px solid #ddd;
            transition: all 0.2s;
            background: #fff;
        }
        
        .relation-picker-item:hover {
            border-color: #007bff;
            background: #f8f9ff;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .relation-picker-item.selected {
            background: #e0e7ff;
            border-color: #007bff;
            box-shadow: 0 2px 8px rgba(0,123,255,0.2);
        }
        
        .relation-title {
            font-weight: 500;
            margin-bottom: 4px;
        }
        
        .relation-meta {
            font-size: 12px;
            color: #666;
        }
        
        .flex {
            display: flex;
        }
        
        .justify-between {
            justify-content: space-between;
        }
        
        .align-center {
            align-items: center;
        }
        
        .gap-2 {
            gap: 8px;
        }
        
        .gap-8 {
            gap: 8px;
        }
        
        .h3 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }
        
        .m-0 {
            margin: 0;
        }
        
        .btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn:hover {
            background: #f8f9fa;
            border-color: #007bff;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .btn-primary:hover {
            background: #0056b3;
            border-color: #0056b3;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            border-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #545b62;
            border-color: #545b62;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
        }
        
        .btn-danger:hover {
            background: #c82333;
            border-color: #bd2130;
        }
        
        .input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        
        .text-muted {
            color: #6c757d;
        }
        
        .text-danger {
            color: #dc3545;
        }
        
        /* Icon styles */
        .icon-plus::before {
            content: "+";
            font-weight: bold;
        }
        
        .icon-trash::before {
            content: "🗑";
        }
        
        .icon-close::before {
            content: "×";
            font-weight: bold;
            font-size: 16px;
        }
        CSS;
    }
    public function getAdminConfig(): array {
        return [
            'collection' => [
                'label' => 'Default Target Collection',
                'type' => 'text',
                'required' => false
            ],
            'multiple' => [
                'label' => 'Allow Multiple',
                'type' => 'checkbox',
                'required' => false
            ],
            'filters' => [
                'label' => 'Filter Options',
                'type' => 'json',
                'description' => 'JSON object with filter options (e.g., {"status": "published", "date_from": "2024-01-01"})',
                'required' => false
            ],
            'display' => [
                'label' => 'Display Mode',
                'type' => 'select',
                'options' => [
                    'list' => 'List',
                    'grid' => 'Grid',
                    'compact' => 'Compact'
                ],
                'required' => false
            ],
            'sort' => [
                'label' => 'Sort Options',
                'type' => 'json',
                'description' => 'JSON object with sort options (e.g., {"field": "created_at", "direction": "desc"})',
                'required' => false
            ],
            'limit' => [
                'label' => 'Limit Results',
                'type' => 'number',
                'description' => 'Maximum number of entries to show (0 = unlimited)',
                'required' => false
            ]
        ];
    }
    public function getSchema(): array { return []; }
    public function getDefaultValue() { return null; }
    public function processValue($value) { return $value; }
    public function processLoadedValue($value) { return $value; }

    public function getConfigUI($fieldIndex, $existingData = []): string {
        $collection = htmlspecialchars($existingData['collection'] ?? '');
        $multiple = !empty($existingData['multiple']);
        $multipleChecked = $multiple ? 'checked' : '';
        
        return <<<HTML
            <div class="field-config-section">
                <label>Target Collection</label>
                <input type="text" name="fields[{$fieldIndex}][collection]" class="input" value="{$collection}" placeholder="Collection slug">
                <small class="form-text">Slug of the collection to relate to</small>
            </div>
            <div class="field-config-section">
                <label class="checkbox-label">
                    <input type="checkbox" name="fields[{$fieldIndex}][multiple]" value="1" {$multipleChecked}>
                    <span class="checkmark"></span>
                    <span>Allow Multiple Relations</span>
                </label>
            </div>
        HTML;
    }
} 