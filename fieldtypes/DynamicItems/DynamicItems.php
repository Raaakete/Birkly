<?php
namespace Birkly\FieldTypes\DynamicItems;

use Birkly\FieldTypes\BaseField;

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
                $className = "Birkly\\FieldTypes\\{$fieldtypeName}\\{$fieldtypeName}";
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
                <button type="button" class="btn btn-primary" onclick="window.HaruFieldtypes['dynamicitems'].showAddBlockModalFromButton(this)">
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
                $className = "Birkly\\FieldTypes\\{$fieldtypeName}\\{$fieldtypeName}";
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
            return (string) file_get_contents($jsPath);
        }
        return '';
    }
    
    public function getCSS(): string {
        return <<<'CSS'
        .block-field {
            border: 1px solid var(--border-medium, #e5e7eb);
            border-radius: var(--radius-md, 0.5rem);
            padding: var(--space-5, 1.25rem);
            background: var(--bg-secondary, #fafafa);
            margin-bottom: var(--space-4, 1rem);
        }
        
        .block-items {
            margin-bottom: var(--space-5, 1.25rem);
            min-height: 80px;
            background: var(--bg-primary, #ffffff);
            border: 2px dashed var(--border-light, #f3f4f6);
            border-radius: var(--radius-md, 0.5rem);
            padding: var(--space-4, 1rem);
        }
        
        .block-items:empty::before {
            content: "No blocks yet. Click 'Add Block' to get started.";
            color: var(--text-secondary, #72746F);
            font-style: italic;
            text-align: center;
            display: block;
            padding: var(--space-10, 2.5rem) var(--space-5, 1.25rem);
        }
        
        .block-item {
            border: 1px solid var(--border-light, #f3f4f6);
            border-radius: var(--radius-md, 0.5rem);
            margin-bottom: var(--space-4, 1rem);
            background: var(--bg-primary, #ffffff);
            box-shadow: var(--shadow-sm, 0 1px 3px rgba(0, 0, 0, 0.1));
            transition: all var(--transition-fast, 150ms ease);
            overflow: hidden;
        }
        
        .block-item:hover {
            box-shadow: var(--shadow-md, 0 4px 6px -1px rgba(0, 0, 0, 0.1));
            border-color: var(--primary-500, #3b82f6);
        }
        
        .block-item:last-child {
            margin-bottom: 0;
        }
        
        .block-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--space-3, 0.75rem) var(--space-4, 1rem);
            background: var(--bg-secondary, #fafafa);
            border-bottom: 1px solid var(--border-light, #f3f4f6);
        }
        
        .block-item-info {
            display: flex;
            align-items: center;
            gap: var(--space-3, 0.75rem);
            flex: 1;
        }
        
        .block-item-label {
            font-weight: 600;
            color: var(--text-primary, #20221D);
            font-size: 0.875rem;
        }
        
        .block-item-type {
            padding: var(--space-1, 0.25rem) var(--space-2, 0.5rem);
            background: var(--bg-hover, #f3f4f6);
            border-radius: var(--radius-sm, 0.375rem);
            font-size: 0.6875rem;
            text-transform: uppercase;
            color: var(--text-secondary, #72746F);
            font-weight: 500;
        }
        
        .block-item-name {
            padding: var(--space-1, 0.25rem) var(--space-2, 0.5rem);
            background: var(--bg-primary, #ffffff);
            border: 1px solid var(--border-light, #f3f4f6);
            border-radius: var(--radius-sm, 0.375rem);
            font-size: 0.6875rem;
            color: var(--text-secondary, #72746F);
            font-family: var(--font-family-mono, monospace);
        }
        
        .block-item-actions {
            display: flex;
            gap: var(--space-2, 0.5rem);
        }
        
        .block-item-content {
            padding: var(--space-4, 1rem);
        }
        
        .block-loading {
            padding: var(--space-5, 1.25rem);
            text-align: center;
            color: var(--text-secondary, #72746F);
            font-style: italic;
        }
        
        .block-error {
            padding: var(--space-4, 1rem);
            background: var(--error-50, #fef2f2);
            border: 1px solid var(--error-200, #fecaca);
            border-radius: var(--radius-sm, 0.375rem);
            color: var(--error-700, #b91c1c);
        }
        
        .block-controls {
            display: flex;
            align-items: center;
            gap: var(--space-2, 0.5rem);
        }
        
        /* Buttons use system button classes - no custom styles needed */
        
        /* Modal Styles */
        .dynamicitems-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--bg-overlay, rgba(0, 0, 0, 0.5));
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: var(--z-index-modal, 10000);
            padding: var(--space-5, 1.25rem);
        }
        
        .dynamicitems-modal {
            background: var(--bg-primary, #ffffff);
            border-radius: var(--radius-md, 0.5rem);
            box-shadow: var(--shadow-xl, 0 20px 25px -5px rgba(0, 0, 0, 0.1));
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
            padding: var(--space-5, 1.25rem);
            border-bottom: 1px solid var(--border-light, #f3f4f6);
        }
        
        .dynamicitems-modal-header h3 {
            margin: 0;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary, #20221D);
        }
        
        .dynamicitems-modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary, #72746F);
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-sm, 0.375rem);
            transition: all var(--transition-fast, 150ms ease);
        }
        
        .dynamicitems-modal-close:hover {
            background: var(--bg-secondary, #fafafa);
            color: var(--text-primary, #20221D);
        }
        
        .dynamicitems-modal-body {
            padding: var(--space-5, 1.25rem);
            overflow-y: auto;
            flex: 1;
        }
        
        .dynamicitems-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: var(--space-3, 0.75rem);
            padding: var(--space-5, 1.25rem);
            border-top: 1px solid var(--border-light, #f3f4f6);
            background: var(--bg-secondary, #fafafa);
        }
        
        .dynamicitems-form-group {
            margin-bottom: var(--space-5, 1.25rem);
        }
        
        .dynamicitems-form-group label {
            display: block;
            margin-bottom: var(--space-2, 0.5rem);
            font-weight: 500;
            color: var(--text-primary, #20221D);
            font-size: 0.875rem;
        }
        
        .dynamicitems-form-group label .required {
            color: var(--error-500, #ef4444);
        }
        
        .dynamicitems-form-group small {
            display: block;
            margin-top: var(--space-1, 0.25rem);
            color: var(--text-secondary, #72746F);
            font-size: 0.75rem;
        }
        
        .dynamicitems-form-group .input {
            width: 100%;
            padding: var(--space-2, 0.5rem) var(--space-3, 0.75rem);
            border: 1px solid var(--border-medium, #e5e7eb);
            border-radius: var(--radius-sm, 0.375rem);
            font-size: 0.875rem;
            font-family: inherit;
            background: var(--bg-primary, #ffffff);
            color: var(--text-primary, #20221D);
        }
        
        .dynamicitems-form-group .input:focus {
            outline: none;
            border-color: var(--primary-500, #3b82f6);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .dynamicitems-form-group input[type="checkbox"] {
            margin-right: var(--space-2, 0.5rem);
        }
        
        #block-settings-container {
            margin-top: var(--space-5, 1.25rem);
            padding-top: var(--space-5, 1.25rem);
            border-top: 1px solid var(--border-light, #f3f4f6);
        }
        
        #block-settings-content {
            background: var(--bg-secondary, #fafafa);
            padding: var(--space-4, 1rem);
            border-radius: var(--radius-sm, 0.375rem);
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
    
    public function getTableMinWidth(): string {
        return '250px'; // DynamicItems fields need space for item count display
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
        
        // Show count badge and preview of item types
        $itemTypes = [];
        $typeCounts = [];
        
        foreach ($value as $item) {
            $itemType = $item['type'] ?? 'unknown';
            if (!isset($typeCounts[$itemType])) {
                $typeCounts[$itemType] = 0;
            }
            $typeCounts[$itemType]++;
        }
        
        // Build preview text showing item types
        $previewParts = [];
        foreach ($typeCounts as $type => $typeCount) {
            $typeLabel = ucfirst(str_replace('_', ' ', $type));
            if ($typeCount === 1) {
                $previewParts[] = $typeLabel;
            } else {
                $previewParts[] = $typeLabel . ' (' . $typeCount . ')';
            }
        }
        
        $previewText = implode(', ', array_slice($previewParts, 0, 2));
        if (count($previewParts) > 2) {
            $previewText .= '...';
        }
        
        return '<span class="ft-list-badge">' . $count . ' item' . ($count === 1 ? '' : 's') . '</span>' .
               ($previewText ? ' <span class="ft-list-text" style="font-size:11px;color:var(--text-tertiary, #9ca3af);">(' . htmlspecialchars($previewText, ENT_QUOTES, 'UTF-8') . ')</span>' : '');
    }
} 