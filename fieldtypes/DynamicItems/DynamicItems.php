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

    public function getTypeLabel(): string {
        return 'Dynamic items';
    }

    public function getIcon(): string {
        return 'layers';
    }

    public function getDescription(): string {
        return 'Repeatable blocks of fields';
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
                
                // Skip DynamicItems itself and types that cannot nest safely
                $excluded = ['DynamicItems', 'Gallery', 'Map', 'Relation'];
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
                            'label' => $fieldtype->getTypeLabel() ?: ucfirst($fieldtypeName),
                            'icon' => $fieldtype->getIcon(),
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
        
        $plusIcon = function_exists('birkly_icon') ? birkly_icon('plus', ['size' => 13]) : '+';

        return <<<HTML
        <div class="block-field" id="field_{$this->name}" data-field-config="{$configJson}">
            <div class="block-items">
                <!-- Blocks will be rendered by JavaScript -->
            </div>
            <div class="block-controls">
                <button type="button" class="add-row dynamicitems-add-block-btn" data-dynamicitems-action="add-block">{$plusIcon}Add block</button>
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
                
                // Skip DynamicItems itself and types that cannot nest safely
                $excluded = ['DynamicItems', 'Gallery', 'Map', 'Relation'];
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
        /* Blocks are wells 4px apart inside the field slab; fields in a block are white
           (a field takes the opposite of what it sits on). Add block is a dashed row. */
        .block-field {
            display: flex;
            flex-direction: column;
            gap: var(--s-1);
        }
        .block-items {
            display: flex;
            flex-direction: column;
            gap: var(--s-1);
        }
        .block-items:empty { display: none; }
        .block-item {
            padding: var(--s-3);
            border-radius: var(--r);
            background: var(--surface-2);
        }
        .block-item .input { border-color: transparent; background: var(--surface); }
        .block-item .input:focus { border-color: var(--accent); }
        .block-item-header {
            display: flex;
            align-items: center;
            gap: var(--s-2);
            margin-block-end: var(--s-2);
        }
        .block-item-info {
            display: flex;
            flex: 1 1 auto;
            align-items: center;
            gap: var(--s-2);
            min-width: 0;
        }
        .block-item-label {
            overflow: hidden;
            color: var(--text);
            font: var(--weight-medium) var(--size-small) / var(--lh-small) var(--font-sans);
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .block-item-type {
            flex: none;
            padding: 0 6px;
            border-radius: var(--r-inset);
            background: var(--surface);
            color: var(--text-2);
            font: var(--weight-medium) var(--size-caption) / 18px var(--font-sans);
        }
        .block-item-name {
            flex: none;
            color: var(--text-3);
            font: var(--weight-regular) var(--size-mono) / var(--lh-mono) var(--font-mono);
        }
        .block-item-actions {
            display: flex;
            flex: none;
            gap: 2px;
        }
        .block-item-actions .btn { border: 0; background: none; color: var(--text-2); }
        .block-item-actions .btn:hover { background: var(--surface-3); color: var(--text); }
        .block-item-actions [data-dynamicitems-action="remove-block"]:hover { color: var(--danger); }
        .block-loading,
        .block-error {
            color: var(--text-3);
            font-size: var(--size-small);
        }
        .block-error { color: var(--danger); }
        .block-controls { display: flex; }
        .dynamicitems-add-block-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--s-2);
            width: 100%;
            min-height: 40px;
            padding: 0 var(--s-3);
            border-radius: var(--r);
            border: 1px dashed var(--line-strong);
            background: none;
            color: var(--text-2);
            font: var(--weight-medium) var(--size-body) / 1 var(--font-sans);
            cursor: pointer;
        }
        .dynamicitems-add-block-btn:hover { border-color: var(--text-3); color: var(--text); }

        /* Add or edit a block: a float */
        .dynamicitems-modal-overlay {
            position: fixed;
            inset: 0;
            z-index: var(--z-modal, 400);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--s-5);
            background: var(--overlay, rgb(0 0 0 / 0.4));
        }
        .dynamicitems-modal {
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 560px;
            max-height: 90vh;
            overflow: hidden;
            border-radius: var(--r);
            background: var(--bg);
            box-shadow: var(--shadow-sheet);
        }
        .dynamicitems-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--s-5) var(--s-5) var(--s-3);
        }
        .dynamicitems-modal-header h3 {
            margin: 0;
            color: var(--text);
            font: var(--weight-semibold) var(--size-title-l) / var(--lh-title-l) var(--font-sans);
        }
        .dynamicitems-modal-close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: var(--control-sm);
            height: var(--control-sm);
            padding: 0;
            border: 0;
            border-radius: var(--r);
            background: none;
            color: var(--text-3);
            font-size: var(--size-title-l);
            cursor: pointer;
        }
        .dynamicitems-modal-close:hover { background: var(--surface-2); color: var(--text); }
        .dynamicitems-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 0 var(--s-5) var(--s-4);
        }
        .dynamicitems-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: var(--s-2);
            padding: var(--s-4) var(--s-5) var(--s-5);
        }
        .dynamicitems-form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-block-end: var(--s-4);
        }
        .dynamicitems-form-group label {
            color: var(--text);
            font: var(--weight-medium) var(--size-small) / var(--lh-small) var(--font-sans);
        }
        .dynamicitems-form-group label .required { color: var(--text-3); }
        .dynamicitems-form-group small {
            color: var(--text-3);
            font-size: var(--size-small);
        }
        .dynamicitems-form-group input[type="checkbox"] { margin-inline-end: var(--s-2); }
        #block-settings-content {
            padding: var(--s-4);
            border-radius: var(--r);
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
               ($previewText ? '<span class="ft-list-muted">' . htmlspecialchars($previewText, ENT_QUOTES, 'UTF-8') . '</span>' : '');
    }
} 