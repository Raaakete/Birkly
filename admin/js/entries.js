/**
 * Entries Page - Bulk Operations
 * Handles bulk selection, actions, and operations
 */

(function() {
    'use strict';
    
    function apiUrl(path) {
        return (window.Birkly && typeof Birkly.url === 'function') ? Birkly.url(path) : path;
    }

    // Get collection slug from URL
    const urlParams = new URLSearchParams(window.location.search);
    const collectionSlug = urlParams.get('collection') || '';
    
    if (!collectionSlug) {
        return; // No collection, exit
    }
    
    // DOM elements
    const selectAllCheckbox = document.getElementById('select-all-entries');
    const entryCheckboxes = document.querySelectorAll('.entry-checkbox');
    const bulkActionsToolbar = document.getElementById('bulk-actions-toolbar');
    const bulkSelectedCount = document.getElementById('bulk-selected-count');
    const bulkPublishBtn = document.getElementById('bulk-publish-btn');
    const bulkUnpublishBtn = document.getElementById('bulk-unpublish-btn');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
    const bulkCancelBtn = document.getElementById('bulk-cancel-btn');
    
    // State
    let selectedEntries = new Set();
    
    /**
     * Update selected count and toolbar visibility
     */
    function updateBulkUI() {
        const count = selectedEntries.size;
        bulkSelectedCount.textContent = count;
        
        if (count > 0) {
            bulkActionsToolbar.style.display = 'block';
        } else {
            bulkActionsToolbar.style.display = 'none';
        }
        
        // Update select all checkbox state
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = count === entryCheckboxes.length && entryCheckboxes.length > 0;
            selectAllCheckbox.indeterminate = count > 0 && count < entryCheckboxes.length;
        }
        
        // Update row visual state
        entryCheckboxes.forEach(checkbox => {
            const row = checkbox.closest('.table-row');
            if (row) {
                if (selectedEntries.has(checkbox.value)) {
                    row.setAttribute('data-entry-selected', 'true');
                } else {
                    row.removeAttribute('data-entry-selected');
                }
            }
        });
    }
    
    /**
     * Get CSRF token
     */
    function getCSRFToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }
    
    /**
     * Perform bulk operation
     */
    async function performBulkOperation(action, entryIds) {
        if (!entryIds || entryIds.length === 0) {
            return { status: 'error', message: 'No entries selected' };
        }
        
        try {
            const response = await fetch(apiUrl('/api/admin_entries.php?action=bulk'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: action,
                    collection: collectionSlug,
                    entry_ids: Array.from(entryIds),
                    _csrf: getCSRFToken()
                })
            });
            
            const responseText = await response.text();
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error('[Bulk Operation] Failed to parse JSON:', e);
                return { status: 'error', message: 'Invalid JSON response: ' + responseText.substring(0, 100) };
            }
            
            return data;
        } catch (error) {
            console.error('Bulk operation error:', error);
            return { status: 'error', message: error.message || 'An error occurred' };
        }
    }
    
    /**
     * Show confirmation dialog
     */
    function confirmAction(message, actionText) {
        return confirm(`${message}\n\nThis will ${actionText} ${selectedEntries.size} entr${selectedEntries.size === 1 ? 'y' : 'ies'}.\n\nAre you sure?`);
    }
    
    /**
     * Show progress indicator
     */
    function showProgress(message) {
        // Simple progress indicator - could be enhanced with a modal
        const progressDiv = document.createElement('div');
        progressDiv.className = 'bulk-progress';
        progressDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; background: var(--bg-elevated); padding: var(--space-3); border-radius: var(--border-radius-s); box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000;';
        progressDiv.textContent = message;
        document.body.appendChild(progressDiv);
        return progressDiv;
    }
    
    /**
     * Handle bulk publish
     */
    async function handleBulkPublish() {
        if (selectedEntries.size === 0) return;
        
        if (!confirmAction('Publish selected entries?', 'publish')) {
            return;
        }
        
        const progress = showProgress(`Publishing ${selectedEntries.size} entr${selectedEntries.size === 1 ? 'y' : 'ies'}...`);
        
        const result = await performBulkOperation('publish', Array.from(selectedEntries));
        
        progress.remove();
        
        if (result.status === 'ok') {
            alert(`Successfully published ${result.data?.count || selectedEntries.size} entr${selectedEntries.size === 1 ? 'y' : 'ies'}.`);
            // Reload page to show updated status
            window.location.reload();
        } else {
            alert(`Error: ${result.message || 'Failed to publish entries'}`);
        }
    }
    
    /**
     * Handle bulk unpublish
     */
    async function handleBulkUnpublish() {
        if (selectedEntries.size === 0) return;
        
        if (!confirmAction('Unpublish selected entries?', 'unpublish')) {
            return;
        }
        
        const progress = showProgress(`Unpublishing ${selectedEntries.size} entr${selectedEntries.size === 1 ? 'y' : 'ies'}...`);
        
        const result = await performBulkOperation('unpublish', Array.from(selectedEntries));
        
        progress.remove();
        
        if (result.status === 'ok') {
            alert(`Successfully unpublished ${result.data?.count || selectedEntries.size} entr${selectedEntries.size === 1 ? 'y' : 'ies'}.`);
            // Reload page to show updated status
            window.location.reload();
        } else {
            alert(`Error: ${result.message || 'Failed to unpublish entries'}`);
        }
    }
    
    /**
     * Handle bulk delete
     */
    async function handleBulkDelete() {
        if (selectedEntries.size === 0) return;
        
        if (!confirmAction('Delete selected entries?', 'permanently delete')) {
            return;
        }
        
        // Double confirmation for delete
        if (!confirm('⚠️ WARNING: This action cannot be undone!\n\nAre you absolutely sure you want to delete these entries?')) {
            return;
        }
        
        const progress = showProgress(`Deleting ${selectedEntries.size} entr${selectedEntries.size === 1 ? 'y' : 'ies'}...`);
        
        const result = await performBulkOperation('delete', Array.from(selectedEntries));
        
        progress.remove();
        
        if (result.status === 'ok') {
            alert(`Successfully deleted ${result.data?.count || selectedEntries.size} entr${selectedEntries.size === 1 ? 'y' : 'ies'}.`);
            // Reload page to show updated list
            window.location.reload();
        } else {
            alert(`Error: ${result.message || 'Failed to delete entries'}`);
        }
    }
    
    /**
     * Handle cancel selection
     */
    function handleBulkCancel() {
        selectedEntries.clear();
        entryCheckboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        }
        updateBulkUI();
    }
    
    /**
     * Handle select all checkbox
     */
    function handleSelectAll() {
        if (selectAllCheckbox.checked) {
            // Select all
            entryCheckboxes.forEach(checkbox => {
                selectedEntries.add(checkbox.value);
                checkbox.checked = true;
            });
        } else {
            // Deselect all
            selectedEntries.clear();
            entryCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
        }
        updateBulkUI();
    }
    
    /**
     * Handle individual entry checkbox
     */
    function handleEntryCheckbox(checkbox) {
        if (checkbox.checked) {
            selectedEntries.add(checkbox.value);
        } else {
            selectedEntries.delete(checkbox.value);
        }
        updateBulkUI();
    }
    
    // Event listeners
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', handleSelectAll);
    }
    
    entryCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            handleEntryCheckbox(this);
        });
    });
    
    if (bulkPublishBtn) {
        bulkPublishBtn.addEventListener('click', handleBulkPublish);
    }
    
    if (bulkUnpublishBtn) {
        bulkUnpublishBtn.addEventListener('click', handleBulkUnpublish);
    }
    
    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', handleBulkDelete);
    }
    
    if (bulkCancelBtn) {
        bulkCancelBtn.addEventListener('click', handleBulkCancel);
    }
    
    // Handle individual entry deletion
    document.addEventListener('click', function(e) {
        if (e.target.closest('.delete-entry-btn')) {
            const btn = e.target.closest('.delete-entry-btn');
            const entryId = btn.dataset.entryId;
            if (entryId) {
                deleteEntry(entryId);
            }
        }
    });
    
    /**
     * Delete a single entry
     */
    async function deleteEntry(entryId) {
        if (!confirm('Are you sure you want to delete this entry? This action cannot be undone.')) {
            return;
        }
        
        // Use collection ID if available, otherwise fall back to slug
        const collectionIdentifier = window.collectionId || collectionSlug;
        
        if (!collectionIdentifier) {
            alert('Error: Collection information not available. Please refresh the page and try again.');
            return;
        }
        
        try {
            const response = await fetch(apiUrl('/api/content.php'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'delete_entry',
                    collection: collectionIdentifier,
                    id: entryId,
                    _csrf: getCSRFToken()
                })
            });
            
            const result = await response.json();
            
            if (result.status === 'ok') {
                // Remove the entry row from the DOM
                const row = document.querySelector(`.table-row[data-entry-id="${entryId}"]`);
                if (row) {
                    row.remove();
                    // Update bulk UI in case this entry was selected
                    selectedEntries.delete(entryId);
                    updateBulkUI();
                } else {
                    // If row not found, reload the page
                    window.location.reload();
                }
            } else {
                alert('Error: ' + (result.message || 'Failed to delete entry'));
            }
        } catch (error) {
            alert('Error: ' + error.message);
            console.error('Delete entry error:', error);
        }
    }
    
    // Initialize UI
    updateBulkUI();
    
    // Status filter functionality
    const statusFilter = document.getElementById('status-filter');
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            const filterValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('.table-row');
            
            rows.forEach(row => {
                const statusBadge = row.querySelector('.status-badge');
                if (!statusBadge) return;
                
                let status = statusBadge.textContent.trim().toLowerCase().replace(/\s+/g, '_');
                // Map "draft" to "unpublished" for backward compatibility
                if (status === 'draft') {
                    status = 'unpublished';
                }
                // Map "pending for review" to "pending_review"
                if (status === 'pending_for_review') {
                    status = 'pending_review';
                }
                
                if (!filterValue || status === filterValue) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
    
    // ============================================
    // Drag and Drop Reordering for Entries
    // ============================================
    // Now handled by MouseDragDrop in initializeEntryDragHandlers()
    
    
    /**
     * Reorder entries
     */
    async function reorderEntries(entryId, targetEntryId, insertBefore) {
        const rows = Array.from(document.querySelectorAll('.table-row'));
        const currentOrder = rows.map(row => row.dataset.entryId).filter(id => id);
        
        // Find current and target positions
        const currentIndex = currentOrder.indexOf(entryId);
        const targetIndex = currentOrder.indexOf(targetEntryId);
        
        if (currentIndex === -1 || targetIndex === -1 || currentIndex === targetIndex) {
            return;
        }
        
        // Remove from current position
        currentOrder.splice(currentIndex, 1);
        
        // Calculate new target index after removal
        let newTargetIndex = targetIndex;
        if (currentIndex < targetIndex) {
            newTargetIndex = targetIndex - 1;
        }
        
        // Insert at correct position
        const insertIndex = insertBefore ? newTargetIndex : newTargetIndex + 1;
        currentOrder.splice(insertIndex, 0, entryId);
        
        // Save new order
        try {
            // Use collection ID if available, otherwise fall back to slug
            const collectionIdentifier = window.collectionId || collectionSlug;
            
            if (!collectionIdentifier) {
                console.error('[Reorder] No collection identifier found');
                alert('Error: Collection not found. Please refresh the page.');
                return;
            }
            
            
            const response = await fetch(apiUrl('/api/content.php?action=set_entry_order'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    collection: collectionIdentifier,
                    order: currentOrder,
                    _csrf: getCSRFToken()
                })
            });
            
            const responseText = await response.text();
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error('[Reorder] Failed to parse JSON:', e, 'Response:', responseText);
                alert('Error saving entry order: Invalid response from server');
                return;
            }
            
            
            if (data.status === 'ok') {
                // Reorder DOM elements to match new order
                const tableBody = document.querySelector('.table-body');
                const entryMap = {};
                rows.forEach(row => {
                    entryMap[row.dataset.entryId] = row;
                });
                
                // Clear and re-append in new order
                tableBody.innerHTML = '';
                currentOrder.forEach(id => {
                    if (entryMap[id]) {
                        tableBody.appendChild(entryMap[id]);
                    }
                });
                
                // Re-initialize drag handlers for reordered rows
                initializeEntryDragHandlers();
            } else {
                alert('Failed to save entry order: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error reordering entries:', error);
            alert('Error saving entry order. Please try again.');
        }
    }
    
    /**
     * Initialize drag handlers for entry rows using MouseDragDrop
     */
    let entrySorter = null;
    
    function initializeEntryDragHandlers() {
        const tableBody = document.querySelector('.table-body');
        if (!tableBody) return;
        
        // Destroy existing sorter if any
        if (entrySorter) {
            entrySorter.destroy();
        }
        
        // Create new sorter
        entrySorter = new MouseDragDrop({
            container: tableBody,
            itemSelector: '.table-row',
            handleSelector: '.cell-drag',
            onReorder: function(fromIndex, toIndex, placeholder, draggedItem) {
                // MouseDragDrop has already moved the dragged item to placeholder position
                // We need to determine the target entry ID from the placeholder's position
                const draggedEntryId = draggedItem.dataset.entryId;
                if (!draggedEntryId) return;
                
                // Find target entry ID based on placeholder position
                let targetEntryId = null;
                let insertBefore = false;
                
                if (placeholder && placeholder.parentNode) {
                    // Check next sibling (insert before this row)
                    let sibling = placeholder.nextSibling;
                    while (sibling) {
                        if (sibling.classList && sibling.classList.contains('table-row')) {
                            targetEntryId = sibling.dataset.entryId;
                            insertBefore = true;
                            break;
                        }
                        sibling = sibling.nextSibling;
                    }
                    
                    // If no next sibling, check previous sibling (insert after this row)
                    if (!targetEntryId) {
                        sibling = placeholder.previousSibling;
                        while (sibling) {
                            if (sibling.classList && sibling.classList.contains('table-row')) {
                                targetEntryId = sibling.dataset.entryId;
                                insertBefore = false;
                                break;
                            }
                            sibling = sibling.previousSibling;
                        }
                    }
                }
                
                // Fallback: use index-based calculation
                if (!targetEntryId) {
                    const allRows = Array.from(tableBody.querySelectorAll('.table-row'))
                        .filter(row => row !== draggedItem && !row.classList.contains('drag-placeholder'));
                    
                    if (toIndex < allRows.length) {
                        targetEntryId = allRows[toIndex].dataset.entryId;
                        insertBefore = true;
                    } else if (allRows.length > 0) {
                        targetEntryId = allRows[allRows.length - 1].dataset.entryId;
                        insertBefore = false;
                    }
                }
                
                if (targetEntryId && draggedEntryId !== targetEntryId) {
                    reorderEntries(draggedEntryId, targetEntryId, insertBefore);
                }
            }
        });
    }
    
    // Initialize drag handlers on page load
    // Use setTimeout to ensure DOM is fully ready
    setTimeout(function() {
        initializeEntryDragHandlers();
    }, 100);
    
    /**
     * Sync action cell widths between header and rows
     * Ensures header action cell matches the width of row action cells
     */
    function syncActionCellWidths() {
        const tables = document.querySelectorAll('.entries-smart-table');
        
        tables.forEach(table => {
            const headActions = table.querySelector('.head-actions');
            const rowActions = table.querySelectorAll('.cell-actions');
            
            if (!headActions || rowActions.length === 0) return;
            
            // Find the widest row action cell
            let maxWidth = 0;
            rowActions.forEach(cell => {
                // Temporarily remove any inline styles to get natural width
                const originalWidth = cell.style.width;
                const originalMinWidth = cell.style.minWidth;
                cell.style.width = '';
                cell.style.minWidth = '';
                
                const width = cell.getBoundingClientRect().width;
                if (width > maxWidth) {
                    maxWidth = width;
                }
                
                // Restore original styles
                cell.style.width = originalWidth;
                cell.style.minWidth = originalMinWidth;
            });
            
            // Set header action cell to match the widest row action cell
            // Use flex-basis instead of width to work better with flexbox
            if (maxWidth > 0) {
                headActions.style.flexBasis = maxWidth + 'px';
                headActions.style.width = maxWidth + 'px';
                headActions.style.minWidth = maxWidth + 'px';
                
                // Set CSS variable for padding calculation in fields-region
                table.style.setProperty('--action-cell-width', maxWidth + 'px');
            }
        });
    }
    
    // Sync action cell widths on page load and window resize
    function initializeActionCellSync() {
        syncActionCellWidths();
        
        // Re-sync on window resize
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(syncActionCellWidths, 100);
        });
        
        // Re-sync when table content changes (e.g., after AJAX updates)
        const observer = new MutationObserver(function() {
            syncActionCellWidths();
        });
        
        document.querySelectorAll('.entries-smart-table').forEach(table => {
            observer.observe(table, { childList: true, subtree: true });
        });
    }
    
    // Initialize action cell width sync
    setTimeout(function() {
        initializeActionCellSync();
    }, 200);
})();

