/**
 * Entries page actions and bulk operations.
 */
(function () {
    'use strict';

    function t(key, fallback) {
        if (window.Birkly && window.Birkly.i18n && typeof window.Birkly.i18n.t === 'function') {
            return window.Birkly.i18n.t('admin.' + key, fallback);
        }
        return fallback != null ? fallback : key;
    }

    function apiUrl(path) {
        return (window.Birkly && typeof window.Birkly.url === 'function') ? window.Birkly.url(path) : path;
    }

    function adminUrl(page, params) {
        if (window.Birkly && typeof window.Birkly.adminUrl === 'function') {
            return window.Birkly.adminUrl(page, params);
        }
        return '/admin/index.php?' + new URLSearchParams(Object.assign({ page: page }, params || {})).toString();
    }

    function getCSRFToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    const urlParams = new URLSearchParams(window.location.search);
    const collectionSlug = window.collectionSlug || urlParams.get('collection') || '';
    const isTrashView = Boolean(window.entriesTrashView);

    if (!collectionSlug) {
        return;
    }

    const selectAllCheckbox = document.getElementById('select-all-entries');
    const entryCheckboxes = Array.from(document.querySelectorAll('.entry-checkbox'));
    const bulkActionsToolbar = document.getElementById('bulk-actions-toolbar');
    const bulkSelectedCount = document.getElementById('bulk-selected-count');
    const bulkPublishBtn = document.getElementById('bulk-publish-btn');
    const bulkUnpublishBtn = document.getElementById('bulk-unpublish-btn');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
    const bulkRestoreBtn = document.getElementById('bulk-restore-btn');
    const bulkCancelBtn = document.getElementById('bulk-cancel-btn');
    const bulkFieldName = document.getElementById('bulk-field-name');
    const bulkFieldValue = document.getElementById('bulk-field-value');
    const bulkFieldApplyBtn = document.getElementById('bulk-field-apply-btn');
    const statusFilter = document.getElementById('status-filter') || document.getElementById('status-filter-desktop');
    const statusFilterDesktop = document.getElementById('status-filter-desktop');
    const statusFilterMobile = document.getElementById('status-filter');
    const entriesSearchDesktop = document.getElementById('entries-search-desktop');
    const entriesSearchMobile = document.getElementById('entries-search');
    const filterToggle = document.getElementById('entries-filter-toggle');
    const filterDrawer = document.getElementById('entries-filter-drawer');

    const selectedEntries = new Set();

    function updateBulkUI() {
        const count = selectedEntries.size;
        if (bulkSelectedCount) {
            bulkSelectedCount.textContent = String(count);
        }

        if (bulkActionsToolbar) {
            bulkActionsToolbar.style.display = count > 0 ? 'block' : 'none';
        }

        if (selectAllCheckbox) {
            selectAllCheckbox.checked = count > 0 && count === entryCheckboxes.length;
            selectAllCheckbox.indeterminate = count > 0 && count < entryCheckboxes.length;
        }

        entryCheckboxes.forEach(function (checkbox) {
            const row = checkbox.closest('.table-row');
            if (!row) return;
            if (selectedEntries.has(checkbox.value)) {
                row.setAttribute('data-entry-selected', 'true');
            } else {
                row.removeAttribute('data-entry-selected');
            }
        });
    }

    async function requestJson(path, payload) {
        const response = await fetch(apiUrl(path), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload || {}),
        });
        const text = await response.text();
        let data = null;
        try {
            data = JSON.parse(text);
        } catch (error) {
            throw new Error('Invalid JSON response');
        }
        if (!response.ok || data?.status === 'error') {
            throw new Error(data?.message || ('HTTP ' + response.status));
        }
        return data;
    }

    async function performBulkOperation(action, entryIds) {
        return requestJson('/api/admin_entries.php?action=bulk', {
            action: action,
            collection: collectionSlug,
            entry_ids: entryIds,
            _csrf: getCSRFToken(),
        });
    }

    async function performBulkFieldEdit(entryIds, fieldNameValue, fieldValueValue) {
        return requestJson('/api/admin_entries.php?action=bulk_field_edit', {
            collection: collectionSlug,
            entry_ids: entryIds,
            field_name: fieldNameValue,
            value: fieldValueValue,
            _csrf: getCSRFToken(),
        });
    }

    async function performEntryAction(action, entryId) {
        return requestJson('/api/admin_entries.php?action=' + encodeURIComponent(action), {
            collection: collectionSlug,
            entry_id: entryId,
            _csrf: getCSRFToken(),
        });
    }

    function showProgress(message) {
        const progress = document.createElement('div');
        progress.className = 'bulk-progress';
        progress.style.cssText = 'position:fixed;top:20px;right:20px;background:var(--bg-elevated);padding:var(--space-3);border-radius:var(--radius-sm,8px);box-shadow:0 4px 12px rgba(0,0,0,.15);z-index:1000;';
        progress.textContent = message;
        document.body.appendChild(progress);
        return progress;
    }

    function clearSelection() {
        selectedEntries.clear();
        entryCheckboxes.forEach(function (checkbox) {
            checkbox.checked = false;
        });
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        }
        updateBulkUI();
    }

    async function runBulkAction(action, confirmation, progressLabel, successLabel) {
        if (selectedEntries.size === 0) {
            return;
        }
        if (!(await BirklyFeedback.confirm(confirmation))) {
            return;
        }

        const progress = showProgress(progressLabel);
        try {
            const result = await performBulkOperation(action, Array.from(selectedEntries));
            progress.remove();
            BirklyFeedback.toast(successLabel.replace('%count%', String(result.count || selectedEntries.size)), 'success');
            window.location.reload();
        } catch (error) {
            progress.remove();
            BirklyFeedback.toast('Error: ' + error.message, 'error');;
        }
    }

    async function handleBulkFieldEdit() {
        const fieldNameValue = bulkFieldName?.value || '';
        const fieldValueValue = bulkFieldValue?.value ?? '';

        if (selectedEntries.size === 0) {
            return;
        }
        if (!fieldNameValue) {
            BirklyFeedback.toast(t('entries_bulk_choose_field', 'Choose a field to edit first.'), 'warning');
            return;
        }
        var entityLabel = selectedEntries.size === 1
            ? t('entries_bulk_entity_entry', 'entry')
            : t('entries_bulk_entity_entries', 'entries');
        var confirmMsg = t('entries_bulk_apply_confirm', 'Apply this field update to %count% selected %entity%?')
            .replace('%count%', String(selectedEntries.size))
            .replace('%entity%', entityLabel);
        if (!(await BirklyFeedback.confirm(confirmMsg))) {
            return;
        }

        const progress = showProgress('Updating field values…');
        try {
            const result = await performBulkFieldEdit(Array.from(selectedEntries), fieldNameValue, fieldValueValue);
            progress.remove();
            BirklyFeedback.toast(result.message || 'Field updated successfully.', 'success');
            window.location.reload();
        } catch (error) {
            progress.remove();
            BirklyFeedback.toast('Error: ' + error.message, 'error');;
        }
    }

    async function handleDeleteEntry(entryId) {
        if (!(await BirklyFeedback.confirm(t('entries_move_to_trash_confirm', 'Move this entry to trash?')))) {
            return;
        }
        const progress = showProgress('Moving entry to trash…');
        try {
            await performEntryAction('delete', entryId);
            progress.remove();
            window.location.reload();
        } catch (error) {
            progress.remove();
            BirklyFeedback.toast('Error: ' + error.message, 'error');
        }
    }

    async function handleRestoreEntry(entryId) {
        if (!(await BirklyFeedback.confirm(t('entries_restore_confirm', 'Restore this entry?')))) {
            return;
        }
        const progress = showProgress('Restoring entry…');
        try {
            await performEntryAction('restore', entryId);
            progress.remove();
            window.location.reload();
        } catch (error) {
            progress.remove();
            BirklyFeedback.toast('Error: ' + error.message, 'error');
        }
    }

    async function handleDuplicateEntry(entryId) {
        const progress = showProgress('Duplicating entry…');
        try {
            const result = await performEntryAction('duplicate', entryId);
            progress.remove();
            const newEntryId = result?.data?.id || '';
            if (newEntryId) {
                window.location.href = adminUrl('entry-editor', { collection: collectionSlug, entry: newEntryId });
                return;
            }
            window.location.reload();
        } catch (error) {
            progress.remove();
            BirklyFeedback.toast('Error: ' + error.message, 'error');
        }
    }

    function handleSelectAll() {
        if (!selectAllCheckbox) {
            return;
        }
        if (selectAllCheckbox.checked) {
            entryCheckboxes.forEach(function (checkbox) {
                checkbox.checked = true;
                selectedEntries.add(checkbox.value);
            });
        } else {
            clearSelection();
        }
        updateBulkUI();
    }

    function setStatusFilter(value) {
        const nextUrl = new URL(window.location.href);
        if (value) {
            nextUrl.searchParams.set('status', value);
        } else {
            nextUrl.searchParams.delete('status');
        }
        window.location.href = nextUrl.toString();
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', handleSelectAll);
    }

    entryCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            if (checkbox.checked) {
                selectedEntries.add(checkbox.value);
            } else {
                selectedEntries.delete(checkbox.value);
            }
            updateBulkUI();
        });
    });

    bulkPublishBtn?.addEventListener('click', async function () {
        runBulkAction(
            'publish',
            'Publish the selected entries?',
            'Publishing entries…',
            'Published %count% entr' + (selectedEntries.size === 1 ? 'y' : 'ies') + '.'
        );
    });

    bulkUnpublishBtn?.addEventListener('click', async function () {
        runBulkAction(
            'unpublish',
            'Unpublish the selected entries?',
            'Unpublishing entries…',
            'Unpublished %count% entr' + (selectedEntries.size === 1 ? 'y' : 'ies') + '.'
        );
    });

    bulkDeleteBtn?.addEventListener('click', async function () {
        runBulkAction(
            'delete',
            'Move the selected entries to trash?',
            'Moving entries to trash…',
            'Moved %count% entr' + (selectedEntries.size === 1 ? 'y' : 'ies') + ' to trash.'
        );
    });

    bulkRestoreBtn?.addEventListener('click', async function () {
        runBulkAction(
            'restore',
            'Restore the selected entries?',
            'Restoring entries…',
            'Restored %count% entr' + (selectedEntries.size === 1 ? 'y' : 'ies') + '.'
        );
    });

    bulkFieldApplyBtn?.addEventListener('click', handleBulkFieldEdit);
    bulkCancelBtn?.addEventListener('click', clearSelection);

    function syncStatusFilters(value) {
        if (statusFilterDesktop) statusFilterDesktop.value = value;
        if (statusFilterMobile) statusFilterMobile.value = value;
    }

    function syncSearchInputs(value) {
        if (entriesSearchDesktop) entriesSearchDesktop.value = value;
        if (entriesSearchMobile) entriesSearchMobile.value = value;
    }

    function openFilterDrawer() {
        if (!filterDrawer || !filterToggle) return;
        filterDrawer.hidden = false;
        filterToggle.setAttribute('aria-expanded', 'true');
        document.body.classList.add('entries-filter-drawer-open');
    }

    function closeFilterDrawer() {
        if (!filterDrawer || !filterToggle) return;
        filterDrawer.hidden = true;
        filterToggle.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('entries-filter-drawer-open');
    }

    statusFilter?.addEventListener('change', function () {
        syncStatusFilters(statusFilter.value);
        setStatusFilter(statusFilter.value);
    });

    statusFilterDesktop?.addEventListener('change', function () {
        syncStatusFilters(statusFilterDesktop.value);
        setStatusFilter(statusFilterDesktop.value);
    });

    function filterEntriesTable(query) {
        const normalized = String(query || '').trim().toLowerCase();
        const rows = document.querySelectorAll('.entries-smart-table .table-row');

        rows.forEach(function (row) {
            if (!normalized) {
                row.style.display = '';
                return;
            }

            const haystack = (row.textContent || '').toLowerCase();
            row.style.display = haystack.includes(normalized) ? '' : 'none';
        });
    }

    function handleSearchInput(value) {
        syncSearchInputs(value);
        filterEntriesTable(value);
    }

    entriesSearchMobile?.addEventListener('input', function () {
        handleSearchInput(entriesSearchMobile.value);
    });

    entriesSearchDesktop?.addEventListener('input', function () {
        handleSearchInput(entriesSearchDesktop.value);
    });

    filterToggle?.addEventListener('click', function () {
        if (filterDrawer && filterDrawer.hidden) {
            openFilterDrawer();
        } else {
            closeFilterDrawer();
        }
    });

    filterDrawer?.querySelectorAll('[data-entries-filter-close]').forEach(function (el) {
        el.addEventListener('click', closeFilterDrawer);
    });

    document.addEventListener('click', async function (event) {
        const deleteButton = event.target.closest('.delete-entry-btn');
        if (deleteButton) {
            handleDeleteEntry(deleteButton.dataset.entryId || '');
            return;
        }

        const restoreButton = event.target.closest('.restore-entry-btn');
        if (restoreButton) {
            handleRestoreEntry(restoreButton.dataset.entryId || '');
            return;
        }

        const duplicateButton = event.target.closest('.duplicate-entry-btn');
        if (duplicateButton) {
            handleDuplicateEntry(duplicateButton.dataset.entryId || '');
        }
    });

    let entrySorter = null;

    async function reorderEntries(entryId, targetEntryId, insertBefore) {
        const rows = Array.from(document.querySelectorAll('.table-row'));
        const currentOrder = rows.map(function (row) { return row.dataset.entryId; }).filter(Boolean);
        const currentIndex = currentOrder.indexOf(entryId);
        const targetIndex = currentOrder.indexOf(targetEntryId);

        if (currentIndex === -1 || targetIndex === -1 || currentIndex === targetIndex) {
            return;
        }

        currentOrder.splice(currentIndex, 1);
        let newTargetIndex = targetIndex;
        if (currentIndex < targetIndex) {
            newTargetIndex = targetIndex - 1;
        }
        currentOrder.splice(insertBefore ? newTargetIndex : newTargetIndex + 1, 0, entryId);

        try {
            const response = await requestJson('/api/content.php?action=set_entry_order', {
                collection: window.collectionId || collectionSlug,
                order: currentOrder,
                _csrf: getCSRFToken(),
            });

            if (response.status !== 'ok') {
                throw new Error(response.message || 'Failed to save entry order');
            }

            const tableBody = document.querySelector('.table-body');
            const entryMap = {};
            rows.forEach(function (row) {
                entryMap[row.dataset.entryId] = row;
            });
            if (!tableBody) {
                return;
            }
            tableBody.innerHTML = '';
            currentOrder.forEach(function (id) {
                if (entryMap[id]) {
                    tableBody.appendChild(entryMap[id]);
                }
            });
            initializeEntryDragHandlers();
        } catch (error) {
            BirklyFeedback.toast('Error saving entry order: ' + error.message, 'error')
        }
    }

    function initializeEntryDragHandlers() {
        const tableBody = document.querySelector('.table-body');
        if (!tableBody || isTrashView || typeof MouseDragDrop !== 'function') {
            return;
        }

        if (entrySorter) {
            entrySorter.destroy();
        }

        entrySorter = new MouseDragDrop({
            container: tableBody,
            itemSelector: '.table-row',
            handleSelector: '.cell-drag',
            onReorder: function (fromIndex, toIndex, placeholder, draggedItem) {
                const draggedEntryId = draggedItem?.dataset?.entryId || '';
                if (!draggedEntryId) return;

                let targetEntryId = null;
                let insertBefore = false;

                if (placeholder && placeholder.parentNode) {
                    let sibling = placeholder.nextSibling;
                    while (sibling) {
                        if (sibling.classList && sibling.classList.contains('table-row')) {
                            targetEntryId = sibling.dataset.entryId;
                            insertBefore = true;
                            break;
                        }
                        sibling = sibling.nextSibling;
                    }
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

                if (!targetEntryId) {
                    const allRows = Array.from(tableBody.querySelectorAll('.table-row')).filter(function (row) {
                        return row !== draggedItem && !row.classList.contains('drag-placeholder');
                    });
                    if (toIndex < allRows.length) {
                        targetEntryId = allRows[toIndex].dataset.entryId;
                        insertBefore = true;
                    } else if (allRows.length > 0) {
                        targetEntryId = allRows[allRows.length - 1].dataset.entryId;
                    }
                }

                if (targetEntryId && draggedEntryId !== targetEntryId) {
                    reorderEntries(draggedEntryId, targetEntryId, insertBefore);
                }
            },
        });
    }

    updateBulkUI();
    window.setTimeout(initializeEntryDragHandlers, 100);

    document.querySelectorAll('.entries-smart-table .table-viewport').forEach(function (viewport) {
        const table = viewport.closest('.entries-smart-table');
        const onScroll = function () {
            if (!table) {
                return;
            }
            table.classList.toggle('is-scrolled-x', viewport.scrollLeft > 1);
        };
        viewport.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    });
})();

