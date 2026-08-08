<?php
namespace Birkly\FieldTypes\Relation;

use Birkly\FieldTypes\BaseField;

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
            border: 1px solid var(--border-medium, #e5e7eb);
            border-radius: var(--radius-md, 0.5rem);
            padding: var(--space-4, 1rem);
            background: var(--bg-secondary, #fafafa);
            margin-bottom: var(--space-4, 1rem);
        }
        
        .relation-list {
            margin-bottom: var(--space-4, 1rem);
            min-height: 60px;
            padding: var(--space-3, 0.75rem);
            background: var(--bg-primary, #ffffff);
            border: 2px dashed var(--border-light, #f3f4f6);
            border-radius: var(--radius-md, 0.5rem);
            transition: all var(--transition-fast, 150ms ease);
        }
        
        .relation-list:empty::before {
            content: "No relations selected";
            color: var(--text-secondary, #72746F);
            font-style: italic;
            text-align: center;
            display: block;
            padding: var(--space-5, 1.25rem);
        }
        
        .relation-entry {
            padding: var(--space-3, 0.75rem) var(--space-4, 1rem);
            background: var(--primary-50, #eff6ff);
            border: 1px solid var(--primary-200, #bfdbfe);
            border-radius: var(--radius-md, 0.5rem);
            margin-bottom: var(--space-2, 0.5rem);
            font-size: 0.875rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all var(--transition-fast, 150ms ease);
        }
        
        .relation-entry:hover {
            background: var(--primary-100, #dbeafe);
            border-color: var(--primary-300, #93c5fd);
        }
        
        .relation-entry:last-child {
            margin-bottom: 0;
        }
        
        .relation-info {
            display: flex;
            flex-direction: column;
            gap: var(--space-1, 0.25rem);
        }
        
        .relation-title {
            font-weight: var(--font-medium, 500);
            color: var(--text-primary, #20221D);
        }
        
        .collection-info {
            font-size: 0.75rem;
            color: var(--text-secondary, #72746F);
            font-style: italic;
        }
        
        .relation-controls {
            display: flex;
            gap: var(--space-3, 0.75rem);
            align-items: center;
        }
        
        .select-relation-btn {
            display: flex;
            align-items: center;
            gap: var(--space-2, 0.5rem);
            padding: var(--space-2, 0.5rem) var(--space-4, 1rem);
            font-weight: var(--font-medium, 500);
        }
        
        .remove-all-relation-btn {
            display: flex;
            align-items: center;
            gap: var(--space-2, 0.5rem);
            padding: var(--space-2, 0.5rem) var(--space-4, 1rem);
        }
        
        /* Buttons use system button classes - .btn-sm is defined in system buttons.css */

        /* P62 follow-up: NEVER style global .modal / .card here — that forced
           every admin modal (incl. Send feedback) to display:flex on pages that
           load Relation (e.g. all-fieldtypes collections). Scope to the picker. */
        #relationPickerModal.relation-picker-modal {
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            background: var(--overlay, var(--bg-overlay, rgba(0, 0, 0, 0.5)));
            display: none;
            align-items: center;
            justify-content: center;
            z-index: var(--z-modal, 400);
            padding: var(--space-5, 1.25rem);
        }

        #relationPickerModal.relation-picker-modal.is-open {
            display: flex;
        }

        #relationPickerModal .card {
            background: var(--surface, var(--bg-primary, #ffffff));
            border-radius: var(--radius-md, 0.5rem);
            box-shadow: var(--elevation-3, var(--shadow-xl, 0 20px 25px -5px rgba(0, 0, 0, 0.1)));
            overflow: hidden;
        }

        #relationPickerModal .card-header {
            background: var(--surface-sunken, var(--bg-secondary, #fafafa));
            border-bottom: 1px solid var(--border-subtle, var(--border-light, #f3f4f6));
            padding: var(--space-4, 1rem) var(--space-5, 1.25rem);
        }

        #relationPickerModal .card-body {
            padding: var(--space-5, 1.25rem);
        }

        #relationPickerModal .card-footer {
            background: var(--surface-sunken, var(--bg-secondary, #fafafa));
            border-top: 1px solid var(--border-subtle, var(--border-light, #f3f4f6));
            padding: var(--space-4, 1rem) var(--space-5, 1.25rem);
        }
        
        .relation-picker-item {
            cursor: pointer;
            padding: var(--space-3, 0.75rem) var(--space-4, 1rem);
            border-radius: var(--radius-md, 0.5rem);
            margin-bottom: var(--space-2, 0.5rem);
            border: 1px solid var(--border-medium, #e5e7eb);
            transition: all var(--transition-fast, 150ms ease);
            background: var(--bg-primary, #ffffff);
        }
        
        .relation-picker-item:hover {
            border-color: var(--primary-500, #3b82f6);
            background: var(--primary-50, #eff6ff);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm, 0 1px 3px rgba(0, 0, 0, 0.1));
        }
        
        .relation-picker-item.selected {
            background: var(--primary-100, #dbeafe);
            border-color: var(--primary-500, #3b82f6);
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.2);
        }
        
        .relation-title {
            font-weight: var(--font-medium, 500);
            margin-bottom: var(--space-1, 0.25rem);
        }
        
        .relation-meta {
            font-size: 0.75rem;
            color: var(--text-secondary, #72746F);
        }
        
        #relationPickerModal .flex {
            display: flex;
        }

        #relationPickerModal .justify-between {
            justify-content: space-between;
        }

        #relationPickerModal .align-center {
            align-items: center;
        }

        #relationPickerModal .gap-2 {
            gap: var(--space-2, 0.5rem);
        }

        #relationPickerModal .gap-8 {
            gap: var(--space-2, 0.5rem);
        }

        #relationPickerModal .h3 {
            font-size: 1.125rem;
            font-weight: 600;
            margin: 0;
        }

        #relationPickerModal .m-0 {
            margin: 0;
        }

        /* Buttons use system button classes - no custom styles needed */

        #relationPickerModal .input {
            width: 100%;
            padding: var(--space-2, 0.5rem) var(--space-3, 0.75rem);
            border: 1px solid var(--border-medium, #e5e7eb);
            border-radius: var(--radius-sm, 0.375rem);
            font-size: 0.875rem;
            background: var(--bg-primary, #ffffff);
            color: var(--text-primary, #20221D);
        }

        #relationPickerModal .input:focus {
            outline: none;
            border-color: var(--primary-500, #3b82f6);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        #relationPickerModal .text-muted {
            color: var(--text-secondary, #72746F);
        }

        #relationPickerModal .text-danger {
            color: var(--error-500, #ef4444);
        }

        /* Icon styles */
        .relation-field .icon-plus::before,
        #relationPickerModal .icon-plus::before {
            content: "+";
            font-weight: bold;
        }

        .relation-field .icon-trash::before,
        #relationPickerModal .icon-trash::before {
            content: "🗑";
        }

        .relation-field .icon-close::before,
        #relationPickerModal .icon-close::before {
            content: "×";
            font-weight: bold;
            font-size: 1rem;
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

    public function renderListCell($value, array $context = []): string {
        // Handle case where value is already a JSON string
        if (is_string($value) && (str_starts_with($value, '[') || str_starts_with($value, '{'))) {
            try {
                $decoded = json_decode($value, true);
                if ($decoded !== null) {
                    $value = $decoded;
                }
            } catch (\Exception $e) {
                // If JSON decode fails, use as is
            }
        }
        
        // Ensure value is an array
        if (!is_array($value)) {
            $value = empty($value) ? [] : [$value];
        }
        
        $count = count($value);
        if ($count === 0) {
            return '<span class="ft-list-empty">–</span>';
        }
        
        // Extract entry IDs from relation objects (they may be stored as objects with id/collection)
        $entryIds = [];
        foreach ($value as $item) {
            if (is_array($item) && isset($item['id'])) {
                // New format: object with id, collection, etc.
                $entryIds[] = $item['id'];
            } elseif (is_object($item) && isset($item->id)) {
                // Object format
                $entryIds[] = $item->id;
            } elseif (is_string($item) || is_numeric($item)) {
                // Old format: just an ID string
                $entryIds[] = (string)$item;
            }
        }
        
        // Try to get entry titles from context or by loading entries
        $titles = [];
        $field = $context['field'] ?? [];
        // Get collection from field config or from fieldtype's own config
        $collectionSlug = $field['collection'] ?? $this->collection ?? '';
        
        // Try to get titles from context entry data if available
        $entry = $context['entry'] ?? null;
        if ($entry && isset($entry['__relation_titles_' . $this->name])) {
            $titles = $entry['__relation_titles_' . $this->name];
        } else {
            // Fallback: try to load entry titles from filesystem
            // For relation objects, we might have different collections per entry
            $contentDir = __DIR__ . '/../../content';
            $collectionsDir = $contentDir . '/collections';
            
            if (is_dir($collectionsDir)) {
                // Try to find collection directory
                $collectionDir = null;
                if ($collectionSlug) {
                    $dirs = scandir($collectionsDir);
                    foreach ($dirs as $dir) {
                        if ($dir === '.' || $dir === '..') continue;
                        $metaFile = "$collectionsDir/$dir/collection.json";
                        if (file_exists($metaFile)) {
                            $meta = json_decode(file_get_contents($metaFile), true);
                            if ($meta && ($meta['slug'] === $collectionSlug || $meta['id'] === $collectionSlug)) {
                                $collectionDir = $dir;
                                break;
                            }
                        }
                    }
                }
                
                // Load titles for each entry ID
                foreach (array_slice($entryIds, 0, 3) as $entryId) {
                    if (empty($entryId)) continue;
                    
                    $found = false;
                    
                    // If we have a specific collection, try that first
                    if ($collectionDir) {
                        $entryFile = "$collectionsDir/$collectionDir/entries/$entryId.json";
                        if (file_exists($entryFile)) {
                            $entryData = json_decode(file_get_contents($entryFile), true);
                            if ($entryData) {
                                $title = $entryData['title'] ?? $entryId;
                                $titles[] = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
                                $found = true;
                            }
                        }
                    }
                    
                    // If not found, search all collections
                    if (!$found) {
                        $dirs = scandir($collectionsDir);
                        foreach ($dirs as $dir) {
                            if ($dir === '.' || $dir === '..') continue;
                            $entryFile = "$collectionsDir/$dir/entries/$entryId.json";
                            if (file_exists($entryFile)) {
                                $entryData = json_decode(file_get_contents($entryFile), true);
                                if ($entryData) {
                                    $title = $entryData['title'] ?? $entryId;
                                    $titles[] = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
                                    $found = true;
                                    break;
                                }
                            }
                        }
                    }
                    
                    // If still not found, just show the ID
                    if (!$found) {
                        $titles[] = htmlspecialchars($entryId, ENT_QUOTES, 'UTF-8');
                    }
                }
            } else {
                // Can't find collections directory, just show IDs
                foreach (array_slice($entryIds, 0, 3) as $entryId) {
                    $titles[] = htmlspecialchars($entryId, ENT_QUOTES, 'UTF-8');
                }
            }
        }
        
        // Build preview
        $previewText = implode(', ', $titles);
        if ($count > 3) {
            $previewText .= '...';
        }
        
        $badge = '<span class="ft-list-badge">' . $count . ' related</span>';
        $text = $previewText ? '<span class="ft-list-text" style="font-size:11px;color:var(--text-tertiary, #9ca3af);margin-left:4px;">(' . $previewText . ')</span>' : '';
        
        return $badge . $text;
    }

    public function getTableMinWidth(): string {
        return '200px'; // Relation fields need space for related entry titles
    }

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