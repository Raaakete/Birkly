/**
 * Entries Page - Bulk Operations
 * Handles bulk selection, actions, and operations
 */

(function() {
    'use strict';
    
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
            const response = await fetch('/api/admin_entries.php?action=bulk', {
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
            
            const data = await response.json();
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
                
                const status = statusBadge.textContent.trim().toLowerCase().replace(/\s+/g, '_');
                
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
    
    let draggedRow = null;
    let draggedEntryId = null;
    
    const tableBody = document.querySelector('.table-body');
    if (tableBody) {
        // Make rows draggable
        const rows = document.querySelectorAll('.table-row');
        rows.forEach(row => {
            const dragCell = row.querySelector('.cell-drag');
            if (dragCell) {
                row.draggable = true;
                dragCell.style.cursor = 'grab';
                
                // Add drag event listeners
                row.addEventListener('dragstart', function(e) {
                    // Only start drag from drag handle
                    if (!e.target.closest('.cell-drag') && e.target !== dragCell) {
                        e.preventDefault();
                        return false;
                    }
                    
                    draggedRow = this;
                    draggedEntryId = this.dataset.entryId;
                    this.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', draggedEntryId);
                    
                    // Hide the row being dragged (but keep space)
                    this.style.opacity = '0.5';
                });
                
                row.addEventListener('dragend', function(e) {
                    this.classList.remove('dragging');
                    this.style.opacity = '';
                    removeEntryDropIndicator();
                    draggedRow = null;
                    draggedEntryId = null;
                });
            }
        });
        
        // Set up drop zone
        tableBody.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.dataTransfer.dropEffect = 'move';
            
            if (!draggedRow || !draggedEntryId) return;
            
            // Find the row we're hovering over
            const targetRow = e.target.closest('.table-row');
            if (targetRow && targetRow !== draggedRow) {
                showEntryDropIndicator(e, targetRow);
            } else {
                removeEntryDropIndicator();
            }
        });
        
        tableBody.addEventListener('dragleave', function(e) {
            // Only remove indicator if we're actually leaving the table body
            if (!tableBody.contains(e.relatedTarget)) {
                removeEntryDropIndicator();
            }
        });
        
        tableBody.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            removeEntryDropIndicator();
            
            if (!draggedRow || !draggedEntryId) return;
            
            const dropIndicator = document.querySelector('.entry-drop-indicator');
            let targetEntryId = null;
            let insertBefore = false;
            
            if (dropIndicator && dropIndicator.dataset.targetEntryId) {
                targetEntryId = dropIndicator.dataset.targetEntryId;
                insertBefore = dropIndicator.dataset.insertBefore === 'true';
            } else {
                // Fallback: find target row
                const targetRow = e.target.closest('.table-row');
                if (targetRow && targetRow !== draggedRow) {
                    targetEntryId = targetRow.dataset.entryId;
                    // Determine if we should insert before or after
                    const dropY = e.clientY;
                    const rect = targetRow.getBoundingClientRect();
                    insertBefore = dropY < rect.top + rect.height / 2;
                }
            }
            
            if (targetEntryId && targetEntryId !== draggedEntryId) {
                reorderEntries(draggedEntryId, targetEntryId, insertBefore);
            }
        });
    }
    
    /**
     * Show drop indicator for entry reordering
     */
    function showEntryDropIndicator(e, targetRow) {
        removeEntryDropIndicator();
        
        const dropY = e.clientY;
        const rect = targetRow.getBoundingClientRect();
        const insertBefore = dropY < rect.top + rect.height / 2;
        
        const indicator = document.createElement('div');
        indicator.className = 'entry-drop-indicator';
        indicator.dataset.targetEntryId = targetRow.dataset.entryId;
        indicator.dataset.insertBefore = insertBefore;
        
        const tableBody = document.querySelector('.table-body');
        const tableBodyRect = tableBody.getBoundingClientRect();
        
        if (insertBefore) {
            indicator.style.top = (rect.top - tableBodyRect.top - 1) + 'px';
        } else {
            indicator.style.top = (rect.bottom - tableBodyRect.top + 1) + 'px';
        }
        
        indicator.style.left = '0';
        indicator.style.width = '100%';
        indicator.style.height = '3px';
        indicator.style.position = 'absolute';
        
        tableBody.style.position = 'relative';
        tableBody.appendChild(indicator);
    }
    
    /**
     * Remove drop indicator
     */
    function removeEntryDropIndicator() {
        const indicator = document.querySelector('.entry-drop-indicator');
        if (indicator) {
            indicator.remove();
        }
    }
    
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
            const response = await fetch('/api/content.php?action=set_entry_order', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    collection: collectionSlug,
                    order: currentOrder,
                    _csrf: getCSRFToken()
                })
            });
            
            const data = await response.json();
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
     * Initialize drag handlers for entry rows
     */
    function initializeEntryDragHandlers() {
        const rows = document.querySelectorAll('.table-row');
        rows.forEach(row => {
            const dragCell = row.querySelector('.cell-drag');
            if (dragCell && !row.hasAttribute('data-drag-initialized')) {
                row.draggable = true;
                dragCell.style.cursor = 'grab';
                row.setAttribute('data-drag-initialized', 'true');
                
                row.addEventListener('dragstart', function(e) {
                    if (!e.target.closest('.cell-drag') && e.target !== dragCell) {
                        e.preventDefault();
                        return false;
                    }
                    
                    draggedRow = this;
                    draggedEntryId = this.dataset.entryId;
                    this.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', draggedEntryId);
                    this.style.opacity = '0.5';
                });
                
                row.addEventListener('dragend', function(e) {
                    this.classList.remove('dragging');
                    this.style.opacity = '';
                    removeEntryDropIndicator();
                    draggedRow = null;
                    draggedEntryId = null;
                });
            }
        });
    }
    
    // Initialize drag handlers on page load
    initializeEntryDragHandlers();
})();

