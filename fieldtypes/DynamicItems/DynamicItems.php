<?php
namespace HaruCMS\FieldTypes\DynamicItems;

use HaruCMS\FieldTypes\BaseField;

class DynamicItems extends BaseField {
    public function getType(): string {
        return 'dynamicitems';
    }

    public function getCategory(): string {
        return 'Misc';
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
        
        $valueJson = htmlspecialchars(json_encode($value), ENT_QUOTES, 'UTF-8');
        
        // Get all available fieldtypes with full metadata
        $availableFieldtypes = [];
        $fieldtypesDir = __DIR__ . '/..';
        
        if (is_dir($fieldtypesDir)) {
            $dirs = glob($fieldtypesDir . '/*', GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                $fieldtypeName = basename($dir);
                
                // Skip DynamicItems itself and complex types
                $excluded = ['DynamicItems', 'Gallery', 'Map', 'Relation', 'Wysiwyg', 'Editor', 'ContentEditor'];
                if (in_array($fieldtypeName, $excluded)) {
                    continue;
                }
                
                // Check if PHP file exists
                $phpFile = $dir . '/' . $fieldtypeName . '.php';
                if (!file_exists($phpFile)) {
                    continue;
                }
                
                // Load the class file
                $className = "HaruCMS\\FieldTypes\\{$fieldtypeName}\\{$fieldtypeName}";
                if (!class_exists($className)) {
                    require_once $phpFile;
                }
                
                // Verify class exists and extends BaseField
                if (class_exists($className) && is_subclass_of($className, BaseField::class)) {
                    try {
                        $tempConfig = [
                            'name' => 'temp',
                            'label' => ucfirst($fieldtypeName),
                            'required' => false
                        ];
                        $fieldtype = new $className($tempConfig);
                        $category = method_exists($fieldtype, 'getCategory') ? $fieldtype->getCategory() : 'Misc';
                        
                        $availableFieldtypes[] = [
                            'name' => strtolower($fieldtypeName),
                            'label' => ucfirst($fieldtypeName),
                            'category' => $category
                        ];
                    } catch (\Exception $e) {
                        // Skip fieldtypes that can't be instantiated
                        error_log("DynamicItems: Failed to instantiate fieldtype {$fieldtypeName}: " . $e->getMessage());
                        continue;
                    }
                }
            }
        }
        
        // Sort by category, then by label
        usort($availableFieldtypes, function($a, $b) {
            if ($a['category'] !== $b['category']) {
                return strcmp($a['category'], $b['category']);
            }
            return strcmp($a['label'], $b['label']);
        });
        
        $allowedBlocks = $this->settings['allowedBlocks'] ?? array_column($availableFieldtypes, 'name');
        $configJson = htmlspecialchars(json_encode([
            'name' => $this->name,
            'allowedBlocks' => $allowedBlocks,
            'availableFieldtypes' => $availableFieldtypes
        ]), ENT_QUOTES, 'UTF-8');
        
        return <<<HTML
        <div class="block-field" id="field_{$this->name}" data-field-config="{$configJson}">
            <div class="block-items" ondragover="event.preventDefault()">
                <!-- Blocks will be rendered by JavaScript -->
            </div>
            <div class="block-controls">
                <button type="button" class="btn btn-primary" onclick="window.HaruFieldtypes['dynamicitems'].showAddBlockModal('{$this->name}')">
                    + Add Block
                </button>
            </div>
            <input type="hidden" name="{$this->name}" id="field_{$this->name}_value" value="{$valueJson}">
        </div>
        HTML;
    }

    public function validate($value): array {
        // Basic validation: must be an array if required
        $errors = [];
        if ($this->required && (empty($value) || !is_array($value))) {
            $errors[] = $this->label . ' is required';
        }
        return $errors;
    }

    public function getAdminConfig(): array {
        // Get all available fieldtypes with full metadata (same logic as render method)
        $availableFieldtypes = [];
        $fieldtypesDir = __DIR__ . '/..';
        
        if (is_dir($fieldtypesDir)) {
            $dirs = glob($fieldtypesDir . '/*', GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                $fieldtypeName = basename($dir);
                
                // Skip DynamicItems itself and complex types
                $excluded = ['DynamicItems', 'Gallery', 'Map', 'Relation', 'Wysiwyg', 'Editor', 'ContentEditor'];
                if (in_array($fieldtypeName, $excluded)) {
                    continue;
                }
                
                // Check if PHP file exists
                $phpFile = $dir . '/' . $fieldtypeName . '.php';
                if (!file_exists($phpFile)) {
                    continue;
                }
                
                // Load the class file
                $className = "HaruCMS\\FieldTypes\\{$fieldtypeName}\\{$fieldtypeName}";
                if (!class_exists($className)) {
                    require_once $phpFile;
                }
                
                // Verify class exists and extends BaseField
                if (class_exists($className) && is_subclass_of($className, BaseField::class)) {
                    $availableFieldtypes[] = strtolower($fieldtypeName);
                }
            }
        }
        
        return [
            'allowedBlocks' => $availableFieldtypes
        ];
    }

    public static function getConfig(): array {
        return [];
    }

    public function getJavaScript(): string {
        $jsPath = __DIR__ . '/fieldtype.js';
        if (file_exists($jsPath)) {
            $js = file_get_contents($jsPath);
            
            // Add initialization function and expose it globally
            $js .= "\n\n// Auto-initialize dynamic items fields\n";
            $js .= "function initDynamicItemsField(fieldName) {\n";
            $js .= "    console.log('[DynamicItems] Initializing field:', fieldName);\n";
            $js .= "    \n";
            $js .= "    const field = document.getElementById('field_' + fieldName);\n";
            $js .= "    if (!field) {\n";
            $js .= "        console.error('[DynamicItems] Field container not found:', fieldName);\n";
            $js .= "        return;\n";
            $js .= "    }\n";
            $js .= "    \n";
            $js .= "    const hiddenInput = field.querySelector('input[type=\"hidden\"]');\n";
            $js .= "    if (hiddenInput && hiddenInput.value) {\n";
            $js .= "        try {\n";
            $js .= "            const value = JSON.parse(hiddenInput.value);\n";
            $js .= "            if (Array.isArray(value) && value.length > 0) {\n";
            $js .= "                console.log('[DynamicItems] Loading existing blocks:', value);\n";
            $js .= "                window.HaruFieldtypes['dynamicitems'].setValue(fieldName, value);\n";
            $js .= "            }\n";
            $js .= "        } catch (e) {\n";
            $js .= "            console.error('[DynamicItems] Failed to parse value:', e);\n";
            $js .= "        }\n";
            $js .= "    }\n";
            $js .= "    \n";
            $js .= "    // Update hidden input when blocks change\n";
            $js .= "    const observer = new MutationObserver(() => {\n";
            $js .= "        const blocks = window.HaruFieldtypes['dynamicitems'].getValue(fieldName);\n";
            $js .= "        if (hiddenInput) {\n";
            $js .= "            hiddenInput.value = JSON.stringify(blocks);\n";
            $js .= "        }\n";
            $js .= "    });\n";
            $js .= "    \n";
            $js .= "    const blocksContainer = field.querySelector('.block-items');\n";
            $js .= "    if (blocksContainer) {\n";
            $js .= "        observer.observe(blocksContainer, { childList: true, subtree: true });\n";
            $js .= "    }\n";
            $js .= "    \n";
            $js .= "    console.log('[DynamicItems] Field initialized successfully:', fieldName);\n";
            $js .= "}\n";
            
            // Expose the function globally
            $js .= "\n// Expose initialization function globally\n";
            $js .= "window.initDynamicItemsField = initDynamicItemsField;\n";
            
            return $js;
        }
        return '';
    }
    
    public function getCSS(): string {
        return <<<'CSS'
        .block-field {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            background: #f8f9fa;
            margin-bottom: 1rem;
        }
        
        .block-items {
            margin-bottom: 20px;
            min-height: 80px;
            background: #fff;
            border: 2px dashed #dee2e6;
            border-radius: 6px;
            padding: 16px;
        }
        
        .block-items:empty::before {
            content: "No blocks yet. Click 'Add Block' to get started.";
            color: #6c757d;
            font-style: italic;
            text-align: center;
            display: block;
            padding: 40px 20px;
        }
        
        .block-item {
            border: 1px solid #e9ecef;
            border-radius: 6px;
            margin-bottom: 16px;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.2s;
            overflow: hidden;
        }
        
        .block-item:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            border-color: #007bff;
        }
        
        .block-item:last-child {
            margin-bottom: 0;
        }
        
        .block-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .block-item-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }
        
        .block-item-label {
            font-weight: 600;
            color: #212529;
            font-size: 14px;
        }
        
        .block-item-type {
            padding: 2px 8px;
            background: #e9ecef;
            border-radius: 4px;
            font-size: 11px;
            text-transform: uppercase;
            color: #6c757d;
            font-weight: 500;
        }
        
        .block-item-name {
            padding: 2px 8px;
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            font-size: 11px;
            color: #6c757d;
            font-family: monospace;
        }
        
        .block-item-actions {
            display: flex;
            gap: 8px;
        }
        
        .block-item-content {
            padding: 16px;
        }
        
        .block-loading {
            padding: 20px;
            text-align: center;
            color: #6c757d;
            font-style: italic;
        }
        
        .block-error {
            padding: 16px;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            color: #721c24;
        }
        
        .block-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
            font-family: inherit;
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
            background: #5a6268;
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
        
        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
        }
        
        /* Modal Styles */
        .dynamicitems-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            padding: 20px;
        }
        
        .dynamicitems-modal {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .dynamicitems-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .dynamicitems-modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }
        
        .dynamicitems-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6c757d;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: all 0.2s;
        }
        
        .dynamicitems-modal-close:hover {
            background: #f8f9fa;
            color: #212529;
        }
        
        .dynamicitems-modal-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }
        
        .dynamicitems-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding: 20px;
            border-top: 1px solid #e9ecef;
            background: #f8f9fa;
        }
        
        .dynamicitems-form-group {
            margin-bottom: 20px;
        }
        
        .dynamicitems-form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #212529;
            font-size: 14px;
        }
        
        .dynamicitems-form-group label .required {
            color: #dc3545;
        }
        
        .dynamicitems-form-group small {
            display: block;
            margin-top: 4px;
            color: #6c757d;
            font-size: 12px;
        }
        
        .dynamicitems-form-group .input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .dynamicitems-form-group .input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }
        
        .dynamicitems-form-group input[type="checkbox"] {
            margin-right: 8px;
        }
        
        #block-settings-container {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        #block-settings-content {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 4px;
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
    
    /**
     * Render list cell for entries table
     */
    public function renderListCell($value, array $context = []): string {
        // Handle JSON string
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = [];
            }
        }
        
        // Ensure value is an array
        if (!is_array($value)) {
            $value = [];
        }
        
        $count = count($value);
        if ($count === 0) {
            return '<span class="ft-list-empty">–</span>';
        }
        
        // Show count and preview of first few items
        $preview = [];
        foreach (array_slice($value, 0, 3) as $item) {
            if (isset($item['value'])) {
                $itemValue = is_array($item['value']) ? json_encode($item['value']) : (string)$item['value'];
                $itemValue = mb_substr($itemValue, 0, 30);
                $preview[] = htmlspecialchars($itemValue, ENT_QUOTES, 'UTF-8');
            }
        }
        
        $previewText = implode(', ', $preview);
        if ($count > 3) {
            $previewText .= '...';
        }
        
        return '<span class="ft-list-badge">' . $count . ' item' . ($count === 1 ? '' : 's') . '</span>' .
               ($previewText ? ' <span class="ft-list-text">(' . $previewText . ')</span>' : '');
    }
} 