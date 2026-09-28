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

    public function getIcon(): string {
        return 'users';
    }

    public function getDescription(): string {
        return 'Links to entries in another collection';
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
        $selectText = $isMultiple ? 'Add entries' : 'Choose entry';
        $plusIcon = function_exists('birkly_icon') ? birkly_icon('plus', ['size' => 13]) : '';
        
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
                <div class="relation-empty">No linked entries yet</div>
            </div>
            <input type="hidden" name="{$this->name}" id="field_{$this->name}_value" value="{$valueJsonEscaped}">
            <div class="relation-controls">
                <button type="button" class="btn btn-secondary btn-sm select-relation-btn" data-field="{$this->name}" data-multiple="{$isMultiple}">{$plusIcon}{$selectText}</button>
                <button type="button" class="btn btn-ghost btn-sm remove-all-relation-btn" data-field="{$this->name}" hidden>Remove all</button>
            </div>
        </div>
        HTML;
    }
    public function validate($value): array { return []; }
    public function getJavaScript(): string {
        $jsPath = __DIR__ . '/fieldtype.js';
        return file_exists($jsPath) ? file_get_contents($jsPath) : '';
    }
    public function getCSS(): string {
        return <<<'CSS'
        /* Linked entries are wells 4px apart; add and remove-all are quiet buttons. */
        .relation-field {
            display: flex;
            flex-direction: column;
            gap: var(--s-2);
        }
        .relation-list {
            display: flex;
            flex-direction: column;
            gap: var(--s-1);
        }
        .relation-empty {
            color: var(--text-3);
            font-size: var(--size-small);
            line-height: var(--lh-small);
        }
        .relation-entry {
            display: flex;
            align-items: center;
            gap: var(--s-3);
            min-height: 36px;
            padding: var(--s-1) var(--s-1) var(--s-1) var(--s-3);
            border-radius: var(--r);
            background: var(--surface-2);
        }
        .relation-info {
            display: flex;
            flex: 1 1 auto;
            align-items: baseline;
            gap: var(--s-2);
            min-width: 0;
        }
        .relation-entry .relation-title {
            overflow: hidden;
            margin: 0;
            color: var(--text);
            font: var(--weight-medium) var(--size-body) / var(--lh-body) var(--font-sans);
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .collection-info {
            flex: none;
            color: var(--text-3);
            font-size: var(--size-small);
        }
        .relation-remove {
            display: inline-flex;
            flex: none;
            align-items: center;
            justify-content: center;
            width: var(--control-sm);
            height: var(--control-sm);
            border: 0;
            border-radius: var(--r);
            background: none;
            color: var(--text-3);
            cursor: pointer;
        }
        .relation-remove:hover { background: var(--surface-3); color: var(--danger); }
        .relation-controls {
            display: flex;
            align-items: center;
            gap: var(--s-1);
        }
        .relation-controls .remove-all-relation-btn:hover { color: var(--danger); }
        .relation-field [hidden] { display: none; }

        /* Picker: a float over the scrim. Scoped to #relationPickerModal — never style
           the global .modal / .card here (P62). Rows are slabs; selected is a blue ring. */
        #relationPickerModal.relation-picker-modal {
            position: fixed;
            inset: 0;
            z-index: var(--z-modal, 400);
            display: none;
            align-items: center;
            justify-content: center;
            padding: var(--s-5);
            background: var(--scrim);
        }
        #relationPickerModal.relation-picker-modal.is-open { display: flex; }
        #relationPickerModal .relation-picker-panel {
            width: 100%;
            max-width: 640px;
            max-height: 90vh;
        }
        .relation-picker-body {
            display: flex;
            flex-direction: column;
            gap: var(--s-3);
            min-height: 0;
        }
        .relation-picker-bar {
            display: grid;
            grid-template-columns: minmax(0, 220px) minmax(0, 1fr);
            gap: var(--s-2);
        }
        .relation-grid {
            display: flex;
            flex-direction: column;
            gap: var(--s-1);
            max-height: 50vh;
            overflow-y: auto;
        }
        .relation-grid.is-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: var(--s-2);
        }
        .relation-picker-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
            padding: var(--s-2) var(--s-3);
            border: 0;
            border-radius: var(--r);
            background: var(--surface);
            color: var(--text);
            text-align: start;
            cursor: pointer;
        }
        .relation-picker-item:hover { box-shadow: 0 0 0 1px var(--accent-line); }
        .relation-picker-item:focus-visible { outline: var(--focus-outline); outline-offset: 1px; }
        .relation-picker-item.selected { box-shadow: 0 0 0 2px var(--accent); }
        .relation-picker-item .relation-title {
            font: var(--weight-medium) var(--size-body) / var(--lh-body) var(--font-sans);
        }
        .relation-meta {
            color: var(--text-3);
            font-size: var(--size-small);
            line-height: var(--lh-small);
        }
        .relation-grid.is-compact .relation-meta { display: none; }
        .relation-picker-empty {
            padding: var(--s-6) var(--s-4);
            border: 1px dashed var(--line-strong);
            border-radius: var(--r);
            color: var(--text-3);
            font-size: var(--size-small);
            text-align: center;
        }
        .relation-picker-empty.is-error { border-color: var(--danger); color: var(--danger); }
        .relation-picker-count {
            margin-inline-end: auto;
            color: var(--text-3);
            font-size: var(--size-small);
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
        $text = $previewText ? '<span class="ft-list-muted">' . $previewText . '</span>' : '';
        
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