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

if (!function_exists('admin_url')) {
    require_once __DIR__ . '/../../../core/birkly_paths.php';
    if (!function_exists('admin_url')) {
        function admin_url(string $page, array $params = []): string {
            return birkly_admin_url($page, $params);
        }
    }
}

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
$selectAllId = match ($checkboxIdPrefix) {
    'entry' => 'select-all-entries',
    'sub-entry' => 'select-all-sub-entries',
    default => 'select-all-' . $checkboxIdPrefix,
};
$checkboxClass = $options['checkbox_class'] ?? 'entry-checkbox';
$rowClass = $options['row_class'] ?? '';
$tableClass = $options['table_class'] ?? '';
$editUrlCallback = $options['edit_url_callback'] ?? null;
$titleUrlCallback = $options['title_url_callback'] ?? null;
$deleteCallback = $options['delete_callback'] ?? null;
$deleteDataAttr = $options['delete_data_attr'] ?? 'data-entry-id';
$showDuplicateAction = !empty($options['show_duplicate_action']);
$duplicateCallback = $options['duplicate_callback'] ?? 'duplicate-entry-btn';
$showRestoreAction = !empty($options['show_restore_action']);
$restoreCallback = $options['restore_callback'] ?? 'restore-entry-btn';
$deleteLabel = $options['delete_label'] ?? __('admin.delete');

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
        return admin_url('entry-detail', ['collection' => $collectionSlug, 'entry' => $entryId]);
    }
    return admin_url('entry-editor', ['collection' => $collectionSlug, 'entry' => $entryId]);
};

// Helper function to get edit URL (always goes to entry-editor)
$getEditUrl = function($entry) use ($collection, $editUrlCallback) {
    if ($editUrlCallback && is_callable($editUrlCallback)) {
        return $editUrlCallback($entry);
    }
    
    // Always use entry-editor for edit button
    $collectionSlug = $collection['slug'] ?? '';
    $entryId = $entry['id'] ?? '';
    return admin_url('entry-editor', ['collection' => $collectionSlug, 'entry' => $entryId]);
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
            <table class="smart-table">
                <thead class="table-head">
                    <tr class="head-row">
                        <?php if ($showCheckbox): ?>
                            <th class="head-cell head-checkbox" scope="col" aria-label="<?= __('admin.select_all') ?>">
                                <input type="checkbox" id="<?= htmlspecialchars($selectAllId) ?>" class="<?= $checkboxIdPrefix === 'sub-entry' ? 'select-all-sub-entries' : ($checkboxIdPrefix === 'entry' ? 'select-all-entries' : '') ?>" <?= $options['checkbox_data_attrs'] ?? '' ?> aria-label="<?= __('admin.select_all_entries') ?>">
                            </th>
                        <?php endif; ?>

                        <?php if ($showDrag): ?>
                            <th class="head-cell head-drag" scope="col" aria-label="<?= __('admin.drag_to_reorder') ?>"></th>
                        <?php endif; ?>

                        <?php if ($showSubEntries): ?>
                            <th class="head-cell head-sub-entries" scope="col" aria-label="Sub-entries count"></th>
                        <?php endif; ?>

                        <th class="head-cell head-title" scope="col"><?= __('admin.title') ?></th>
                        <th class="head-cell head-status" scope="col"><?= __('admin.status') ?></th>

                        <?php foreach ($fields as $field): ?>
                            <th class="head-cell head-field" scope="col" style="--col-field: <?= htmlspecialchars($field['width'] ?? '240px') ?>; --col-field-min: <?= htmlspecialchars($field['min_width'] ?? $field['width'] ?? '240px') ?>">
                                <?= htmlspecialchars($field['label'] ?? $field['name'] ?? '') ?>
                            </th>
                        <?php endforeach; ?>

                        <th class="head-cell head-actions" scope="col"><?= __('admin.actions') ?></th>
                    </tr>
                </thead>

                <tbody class="table-body">
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
                    <tr class="table-row <?= htmlspecialchars($rowClass) ?>" data-entry-id="<?= htmlspecialchars($entryId) ?>" <?= $options['row_data_attrs'] ?? '' ?>>
                        <?php if ($showCheckbox): ?>
                            <td class="cell cell-checkbox" aria-label="<?= __('admin.select_entry') ?>">
                                <input type="checkbox" class="<?= htmlspecialchars($checkboxClass) ?>" value="<?= htmlspecialchars($entryId) ?>" <?= $options['checkbox_data_attrs'] ?? '' ?> aria-label="<?= __('admin.select_entry') ?> <?= htmlspecialchars($title) ?>">
                            </td>
                        <?php endif; ?>

                        <?php if ($showDrag): ?>
                            <td class="cell cell-drag" draggable="true" aria-label="<?= __('admin.drag_to_reorder') ?>">
                                <?= birkly_icon('dots-six-vertical', ['size' => 16]) ?>
                            </td>
                        <?php endif; ?>

                        <?php if ($showSubEntries): ?>
                            <td class="cell cell-sub-entries">
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
                            </td>
                        <?php endif; ?>

                        <td class="cell cell-title">
                            <a href="<?= htmlspecialchars($titleUrl) ?>" class="title-text">
                                <?= htmlspecialchars($title) ?>
                            </a>
                        </td>

                        <td class="cell cell-status">
                            <span class="status-badge <?= htmlspecialchars($statusClass) ?>" data-status="<?= htmlspecialchars($status) ?>" aria-label="<?= __('admin.status') ?>: <?= htmlspecialchars($statusLabel) ?>"<?= $reviewInfo ?>>
                                <?= htmlspecialchars($statusLabel) ?>
                            </span>
                            <?php if ($status === 'pending_review' && $reviewer): ?>
                                <div class="reviewer-indicator" title="Assigned to: <?= htmlspecialchars($reviewer) ?>">
                                    <?= birkly_icon('user', ['size' => 12]) ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <?php foreach ($fields as $field): ?>
                            <td class="cell cell-field" style="--col-field: <?= htmlspecialchars($field['width'] ?? '240px') ?>; --col-field-min: <?= htmlspecialchars($field['min_width'] ?? $field['width'] ?? '240px') ?>">
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
                            </td>
                        <?php endforeach; ?>

                        <td class="cell cell-actions">
                            <div class="cell-actions-inner">
                            <a class="btn-icon btn-icon--edit" href="<?= htmlspecialchars($editUrl) ?>" aria-label="<?= __('admin.edit_entry') ?> <?= htmlspecialchars($title) ?>" title="<?= __('admin.edit_entry') ?>">
                                <?= birkly_icon('pencil-simple', ['size' => 16]) ?>
                            </a>
                            <?php if ($showDuplicateAction && $status !== 'trashed'): ?>
                                <button type="button" class="btn-icon <?= htmlspecialchars($duplicateCallback) ?>" <?= htmlspecialchars($deleteDataAttr) ?>="<?= htmlspecialchars($entryId) ?>" aria-label="Duplicate <?= htmlspecialchars($title) ?>" title="Duplicate">
                                    <?= birkly_icon('copy', ['size' => 16]) ?>
                                </button>
                            <?php endif; ?>
                            <?php if ($showRestoreAction && $status === 'trashed'): ?>
                                <button type="button" class="btn-icon <?= htmlspecialchars($restoreCallback) ?>" <?= htmlspecialchars($deleteDataAttr) ?>="<?= htmlspecialchars($entryId) ?>" aria-label="Restore <?= htmlspecialchars($title) ?>" title="Restore">
                                    <?= birkly_icon('arrow-counter-clockwise', ['size' => 16]) ?>
                                </button>
                            <?php endif; ?>
                            <?php if ($deleteCallback): ?>
                                <?php if ($status !== 'trashed'): ?>
                                    <button type="button" class="btn-icon btn-icon--danger <?= htmlspecialchars($deleteCallback) ?>" <?= htmlspecialchars($deleteDataAttr) ?>="<?= htmlspecialchars($entryId) ?>" aria-label="<?= htmlspecialchars($deleteLabel) ?> <?= htmlspecialchars($title) ?>" title="<?= htmlspecialchars($deleteLabel) ?>">
                                        <?= birkly_icon('trash', ['size' => 16]) ?>
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="empty-state empty-state--compact">
        <p class="empty-state__message"><?= __('admin.no_entries_found_in_collection') ?> <a href="<?= admin_url('entry-editor', ['collection' => $collection['slug'] ?? '']) ?>"><?= __('admin.create_first_entry') ?></a>.</p>
    </div>
<?php endif; ?>
