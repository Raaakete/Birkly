<?php
/**
 * Smart Table Component
 * Reusable component for displaying entries in a smart table format
 * 
 * This component expects the following variables to be available in scope:
 * - $entries: Array of entries to display
 * - $fields: Array of field definitions
 * - $collection: Collection data object
 * - $options: Array of options (optional)
 * 
 * Options:
 *   - show_checkbox: bool (default: false)
 *   - show_drag: bool (default: false)
 *   - show_sub_entries: bool (default: false)
 *   - checkbox_id_prefix: string (default: 'entry')
 *   - checkbox_class: string (default: 'entry-checkbox')
 *   - row_class: string (default: '')
 *   - table_class: string (default: '')
 *   - edit_url_callback: callable (optional) - function(entry) returns edit URL
 *   - title_url_callback: callable (optional) - function(entry) returns title link URL (for nested sub-entries)
 *   - delete_callback: string (optional) - JavaScript function name for delete
 *   - delete_data_attr: string (optional) - data attribute name for delete (default: 'data-entry-id')
 *   - table_data_attrs: string (optional) - additional data attributes for table wrapper
 *   - checkbox_data_attrs: string (optional) - additional data attributes for checkboxes
 *   - row_data_attrs: string (optional) - additional data attributes for rows
 */

// Ensure variables exist (they should be available from extract() in template parser)
$entries = $entries ?? [];
$fields = $fields ?? [];
$collection = $collection ?? [];
$options = $options ?? [];

// Set defaults
$showCheckbox = $options['show_checkbox'] ?? false;
$showDrag = $options['show_drag'] ?? false;
$showSubEntries = $options['show_sub_entries'] ?? false;
$checkboxIdPrefix = $options['checkbox_id_prefix'] ?? 'entry';
$checkboxClass = $options['checkbox_class'] ?? 'entry-checkbox';
$rowClass = $options['row_class'] ?? '';
$tableClass = $options['table_class'] ?? '';
$editUrlCallback = $options['edit_url_callback'] ?? null;
$titleUrlCallback = $options['title_url_callback'] ?? null;
$deleteCallback = $options['delete_callback'] ?? null;
$deleteDataAttr = $options['delete_data_attr'] ?? 'data-entry-id';

// Helper function to get title URL (links to detail page if sub-collections exist, otherwise editor)
$getTitleUrl = function($entry) use ($collection, $titleUrlCallback) {
    // Use custom callback if provided (for nested sub-entries with parent context)
    if ($titleUrlCallback && is_callable($titleUrlCallback)) {
        return $titleUrlCallback($entry);
    }
    
    $collectionSlug = $collection['slug'] ?? '';
    $entryId = $entry['id'] ?? '';
    $subEntryCount = $entry['__sub_entry_count'] ?? 0;
    $hasSubCollections = !empty($collection['sub_collections']);
    
    // If entry has sub-entries or collection has sub-collections, link to detail page
    if ($subEntryCount > 0 || $hasSubCollections) {
        return "/admin/index.php?page=entry-detail&collection=" . urlencode($collectionSlug) . "&entry=" . urlencode($entryId);
    } else {
        return "/admin/index.php?page=entry-editor&collection=" . urlencode($collectionSlug) . "&entry=" . urlencode($entryId);
    }
};

// Helper function to get edit URL (always goes to entry-editor)
$getEditUrl = function($entry) use ($collection, $editUrlCallback) {
    if ($editUrlCallback && is_callable($editUrlCallback)) {
        return $editUrlCallback($entry);
    }
    
    // Always use entry-editor for edit button
    $collectionSlug = $collection['slug'] ?? '';
    $entryId = $entry['id'] ?? '';
    return "/admin/index.php?page=entry-editor&collection=" . urlencode($collectionSlug) . "&entry=" . urlencode($entryId);
};

// Helper function to get entry title
$getTitle = function($entry) {
    if (isset($entry['__cells']['__title'])) {
        return $entry['__cells']['__title'];
    }
    return $entry['title'] ?? $entry['id'] ?? '';
};

// Helper function to get entry status
$getStatus = function($entry) {
    return $entry['__cells']['__status'] ?? $entry['status'] ?? 'draft';
};

// Helper function to get sub-entry count
$getSubEntryCount = function($entry) {
    return $entry['__sub_entry_count'] ?? 0;
};
?>

<?php 
// Debug: Check if component is being loaded
// Uncomment for debugging:
// error_log('Smart Table Component: entries count = ' . count($entries));
// error_log('Smart Table Component: fields count = ' . count($fields));
// error_log('Smart Table Component: collection = ' . ($collection['slug'] ?? 'N/A'));

if (!empty($entries)): ?>
    <div class="entries-smart-table <?= htmlspecialchars($tableClass) ?>" <?= $options['table_data_attrs'] ?? '' ?>>
        <div class="table-viewport">
            <div class="table-head">
                <div class="head-row" role="row">
                    <?php if ($showCheckbox): ?>
                        <div class="head-cell head-checkbox" role="columnheader" aria-label="<?= __('admin.select_all') ?>">
                            <input type="checkbox" id="select-all-<?= htmlspecialchars($checkboxIdPrefix) ?>s" class="<?= $checkboxIdPrefix === 'sub-entry' ? 'select-all-sub-entries' : '' ?>" <?= $options['checkbox_data_attrs'] ?? '' ?> aria-label="<?= __('admin.select_all_entries') ?>">
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($showDrag): ?>
                        <div class="head-cell head-drag" role="columnheader" aria-label="<?= __('admin.drag_to_reorder') ?>"></div>
                    <?php endif; ?>
                    
                    <?php if ($showSubEntries): ?>
                        <div class="head-cell head-sub-entries" role="columnheader" style="width: 60px;" aria-label="Sub-entries count"></div>
                    <?php endif; ?>
                    
                    <div class="head-cell head-title" role="columnheader"><?= __('admin.title') ?></div>
                    <div class="head-cell head-status" role="columnheader"><?= __('admin.status') ?></div>
                    
                    <div class="fields-head-region">
                        <?php foreach ($fields as $field): ?>
                            <div class="head-cell head-field" role="columnheader" style="--col-field: <?= htmlspecialchars($field['width'] ?? '240px') ?>; --col-field-min: <?= htmlspecialchars($field['min_width'] ?? $field['width'] ?? '240px') ?>">
                                <?= htmlspecialchars($field['name'] ?? $field['label'] ?? '') ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="head-cell head-actions" role="columnheader"><?= __('admin.actions') ?></div>
                </div>
            </div>
            
            <div class="table-body">
                <?php foreach ($entries as $entry): ?>
                    <?php
                    $entryId = $entry['id'] ?? '';
                    $title = $getTitle($entry);
                    $status = $getStatus($entry);
                    $subEntryCount = $getSubEntryCount($entry);
                    $titleUrl = $getTitleUrl($entry);
                    $editUrl = $getEditUrl($entry);
                    
                    // Status badge logic
                    // Map old 'draft' status to 'unpublished' for backward compatibility
                    if ($status === 'draft') {
                        $status = 'unpublished';
                    }
                    $statusClass = $status;
                    if (in_array($status, ['pending_review', 'published', 'unpublished', 'scheduled'])) {
                        // Use translation if available, otherwise use formatted label
                        $translated = __('admin.' . $status);
                        $statusLabel = ($translated && $translated !== 'admin.' . $status) ? $translated : ucfirst(str_replace('_', ' ', $status));
                    } else {
                        $statusLabel = ucfirst(str_replace('_', ' ', $status));
                    }
                    
                    if (in_array($status, ['pending_review', 'approved', 'rejected'])) {
                        $statusClass .= ' review-status';
                        if ($status === 'pending_review') {
                            $statusClass .= ' pending';
                        } elseif ($status === 'approved') {
                            $statusClass .= ' approved';
                        } elseif ($status === 'rejected') {
                            $statusClass .= ' rejected';
                        }
                    }
                    
                    $reviewer = $entry['reviewer'] ?? '';
                    $reviewedBy = $entry['reviewed_by'] ?? '';
                    $reviewInfo = '';
                    if ($reviewer) {
                        $reviewInfo = ' title="Assigned to: ' . htmlspecialchars($reviewer) . '"';
                    } elseif ($reviewedBy) {
                        $reviewInfo = ' title="Reviewed by: ' . htmlspecialchars($reviewedBy) . '"';
                    }
                    ?>
                    <div class="table-row <?= htmlspecialchars($rowClass) ?>" role="row" data-entry-id="<?= htmlspecialchars($entryId) ?>" <?= $options['row_data_attrs'] ?? '' ?>>
                        <?php if ($showCheckbox): ?>
                            <div class="cell cell-checkbox" role="cell" aria-label="<?= __('admin.select_entry') ?>">
                                <input type="checkbox" class="<?= htmlspecialchars($checkboxClass) ?>" value="<?= htmlspecialchars($entryId) ?>" <?= $options['checkbox_data_attrs'] ?? '' ?> aria-label="<?= __('admin.select_entry') ?> <?= htmlspecialchars($title) ?>">
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($showDrag): ?>
                            <div class="cell cell-drag" role="cell" draggable="true" aria-label="<?= __('admin.drag_to_reorder') ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <circle cx="9" cy="12" r="1"></circle>
                                    <circle cx="9" cy="5" r="1"></circle>
                                    <circle cx="9" cy="19" r="1"></circle>
                                    <circle cx="20" cy="12" r="1"></circle>
                                    <circle cx="20" cy="5" r="1"></circle>
                                    <circle cx="20" cy="19" r="1"></circle>
                                </svg>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($showSubEntries): ?>
                            <div class="cell cell-sub-entries" role="cell" style="width: 60px; text-align: center;">
                                <?php if ($subEntryCount > 0): ?>
                                    <a href="<?= htmlspecialchars($titleUrl) ?>" 
                                       class="sub-entry-count-badge" 
                                       title="<?= $subEntryCount ?> sub-entries"
                                       style="display: inline-flex; align-items: center; justify-content: center; min-width: 32px; height: 24px; padding: 0 8px; background: var(--primary-100, #e0e7ff); color: var(--primary-700, #4338ca); border-radius: 12px; font-size: 12px; font-weight: 600; text-decoration: none; transition: all 0.2s;">
                                        <span style="margin-right: 2px;">+</span><?= $subEntryCount ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: var(--text-tertiary, #9ca3af); font-size: 12px;">–</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="cell cell-title" role="cell">
                            <a href="<?= htmlspecialchars($titleUrl) ?>" class="title-text">
                                <?= htmlspecialchars($title) ?>
                            </a>
                        </div>
                        
                        <div class="cell cell-status" role="cell">
                            <span class="status-badge <?= htmlspecialchars($statusClass) ?>" aria-label="<?= __('admin.status') ?>: <?= htmlspecialchars($statusLabel) ?>"<?= $reviewInfo ?>>
                                <?= htmlspecialchars($statusLabel) ?>
                            </span>
                            <?php if ($status === 'pending_review' && $reviewer): ?>
                                <div class="reviewer-indicator" title="Assigned to: <?= htmlspecialchars($reviewer) ?>">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="fields-region">
                            <?php foreach ($fields as $field): ?>
                                <div class="cell cell-field" role="cell" style="--col-field: <?= htmlspecialchars($field['width'] ?? '240px') ?>; --col-field-min: <?= htmlspecialchars($field['min_width'] ?? $field['width'] ?? '240px') ?>">
                                    <?php
                                    $fieldName = $field['name'] ?? '';
                                    $fieldValue = '';
                                    if (isset($entry['__cells'][$fieldName . '_html'])) {
                                        $fieldValue = $entry['__cells'][$fieldName . '_html'];
                                    } elseif (isset($entry['__cells'][$fieldName])) {
                                        $fieldValue = htmlspecialchars($entry['__cells'][$fieldName]);
                                    } elseif (isset($entry[$fieldName])) {
                                        $fieldValue = htmlspecialchars($entry[$fieldName]);
                                    }
                                    echo $fieldValue ?: '<span class="ft-list-empty" aria-label="' . __('admin.no_value') . '">–</span>';
                                    ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="cell cell-actions" role="cell">
                            <a class="btn mr-s" href="<?= htmlspecialchars($editUrl) ?>" aria-label="<?= __('admin.edit_entry') ?> <?= htmlspecialchars($title) ?>">
                                <?= __('admin.edit_entry') ?>
                            </a>
                            <?php if ($deleteCallback): ?>
                                <button type="button" class="btn btn-danger <?= htmlspecialchars($deleteCallback) ?>" <?= htmlspecialchars($deleteDataAttr) ?>="<?= htmlspecialchars($entryId) ?>" aria-label="<?= __('admin.delete') ?> <?= htmlspecialchars($title) ?>">
                                    <?= __('admin.delete') ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="empty-state">
        <p><?= __('admin.no_entries_found_in_collection') ?> <a href="<?= "/admin/index.php?page=entry-editor&collection=" . urlencode($collection['slug'] ?? '') ?>"><?= __('admin.create_first_entry') ?></a>.</p>
    </div>
<?php endif; ?>
