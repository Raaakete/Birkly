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
})();

