<?php
namespace HaruCMS\FieldTypes\DynamicItems;

use HaruCMS\FieldTypes\BaseField;

class DynamicItems extends BaseField {
    public function getType(): string {
        return 'dynamicitems';
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
        $allowedBlocks = $this->settings['allowedBlocks'] ?? ['text', 'textarea', 'quote', 'number', 'date', 'checkbox'];
        $configJson = htmlspecialchars(json_encode([
            'name' => $this->name,
            'allowedBlocks' => $allowedBlocks
        ]), ENT_QUOTES, 'UTF-8');
        
        // Debug logging
        error_log("DynamicItems render - name: " . $this->name);
        error_log("DynamicItems render - settings: " . json_encode($this->settings ?? []));
        
        return <<<HTML
        <div class="block-field" id="field_{$this->name}" data-field-config="{$configJson}">
            <div class="block-items" ondragover="event.preventDefault()">
                <!-- Blocks will be rendered by JavaScript -->
            </div>
            <div class="block-controls">
                <select class="input" id="add_block_type_{$this->name}">
                    <option value="text">Text</option>
                    <option value="textarea">Textarea</option>
                    <option value="quote">Quote</option>
                    <option value="number">Number</option>
                    <option value="date">Date</option>
                    <option value="checkbox">Checkbox</option>
                </select>
                <button type="button" class="btn btn-secondary" onclick="window.HaruFieldtypes['dynamicitems'].addBlock('{$this->name}')">
                    Add Block
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
        // Return a sample config for the admin UI
        return [
            'allowedBlocks' => ['text', 'textarea', 'quote', 'number', 'date', 'checkbox']
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
            border-radius: 4px;
            padding: 16px;
            background: #f8f9fa;
            margin-bottom: 1rem;
        }
        
        .block-items {
            margin-bottom: 16px;
            min-height: 60px;
            background: #fff;
            border: 2px dashed #dee2e6;
            border-radius: 4px;
            padding: 12px;
        }
        
        .block-items:empty::before {
            content: "Drag blocks here or use the Add Block button below";
            color: #6c757d;
            font-style: italic;
            text-align: center;
            display: block;
            padding: 20px;
        }
        
        .block-item {
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 12px;
            margin-bottom: 12px;
            background: #fff;
            cursor: move;
            transition: all 0.2s;
        }
        
        .block-item:hover {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-color: #007bff;
        }
        
        .block-item:last-child {
            margin-bottom: 0;
        }
        
        .block-type-select {
            margin-bottom: 8px;
            width: 150px;
        }
        
        .block-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .block-controls select {
            min-width: 150px;
        }
        
        .button {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .button:hover {
            background: #f8f9fa;
            border-color: #007bff;
        }
        
        .button-danger {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
        }
        
        .button-danger:hover {
            background: #c82333;
            border-color: #bd2130;
        }
        
        /* Drag and drop feedback */
        .block-item.dragging {
            opacity: 0.5;
        }
        
        .block-item.drag-over {
            border-color: #007bff;
            background: #e3f2fd;
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