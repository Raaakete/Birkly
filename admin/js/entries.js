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
    const tableFields = Array.isArray(window.entriesTableFields) ? window.entriesTableFields : [];
    const hasSubCollections = Boolean(window.entriesHasSubCollections);
    const hasDragColumn = Boolean(window.entriesHasDragColumn);
    const initialListState = window.entriesListState || {};

    if (!collectionSlug) {
        return;
    }

    let entriesTable = document.querySelector('.entries-smart-table');
    let tableBody = entriesTable ? entriesTable.querySelector('.table-body') : null;
    let paginationContainer = document.getElementById('entries-pagination');
    let selectAllCheckbox = document.getElementById('select-all-entries');
    let entryCheckboxes = Array.from(document.querySelectorAll('.entry-checkbox'));
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
    let entrySorter = null;
    let listRequestId = 0;
    let listRequestController = null;
    let searchDebounceId = 0;
    let listState = normalizeListState(initialListState);

    function normalizePositiveInteger(value, fallback) {
        const parsed = Number.parseInt(value, 10);
        return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
    }

    function normalizeListState(rawState) {
        rawState = rawState || {};
        const page = normalizePositiveInteger(rawState.page ?? urlParams.get('page'), 1);
        const perPage = normalizePositiveInteger(rawState.per_page ?? initialListState.per_page, 20);
        const total = Math.max(0, normalizePositiveInteger(rawState.total, 0));
        const totalPages = Math.max(1, normalizePositiveInteger(rawState.total_pages, 1));
        const sort = String(rawState.sort ?? urlParams.get('sort') ?? initialListState.sort ?? '').trim();
        const order = String(rawState.order ?? urlParams.get('order') ?? initialListState.order ?? 'asc').trim().toLowerCase() === 'desc' ? 'desc' : 'asc';
        const q = String(rawState.q ?? urlParams.get('q') ?? initialListState.q ?? '').trim();
        const status = String(rawState.status ?? urlParams.get('status') ?? window.entriesStatusFilter ?? '').trim();

        return {
            page: Math.min(page, totalPages),
            per_page: perPage,
            total: total,
            total_pages: totalPages,
            q: q,
            sort: sort,
            order: order,
            status: status,
        };
    }

    function refreshTableElements() {
        entriesTable = document.querySelector('.entries-smart-table');
        tableBody = entriesTable ? entriesTable.querySelector('.table-body') : null;
        paginationContainer = document.getElementById('entries-pagination');
        selectAllCheckbox = document.getElementById('select-all-entries');
        entryCheckboxes = Array.from(document.querySelectorAll('.entry-checkbox'));
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function icon(name, opts) {
        if (window.Birkly && typeof window.Birkly.icon === 'function') {
            return window.Birkly.icon(name, opts || {});
        }
        return '';
    }

    function normalizeStatus(status) {
        return status === 'draft' ? 'unpublished' : (status || 'unpublished');
    }

    function statusLabel(status) {
        const normalized = normalizeStatus(status);
        const translated = t(normalized, normalized.replace(/_/g, ' ').replace(/\b\w/g, function (char) {
            return char.toUpperCase();
        }));
        return translated || normalized;
    }

    function isTrashView() {
        return String(listState.status || '') === 'trashed';
    }

    function canDragCurrentView() {
        return hasDragColumn && !isTrashView() && !listState.q && !listState.status && Number(listState.page || 1) === 1;
    }

    function buildTitleUrl(entry) {
        const entryId = entry && entry.id ? String(entry.id) : '';
        const subEntryCount = Number(entry && entry.__sub_entry_count ? entry.__sub_entry_count : 0);
        if (subEntryCount > 0 || hasSubCollections) {
            return adminUrl('entry-detail', { collection: collectionSlug, entry: entryId });
        }
        return adminUrl('entry-editor', { collection: collectionSlug, entry: entryId });
    }

    function buildEditUrl(entry) {
        const entryId = entry && entry.id ? String(entry.id) : '';
        const subEntryCount = Number(entry && entry.__sub_entry_count ? entry.__sub_entry_count : 0);
        if (subEntryCount > 0) {
            return adminUrl('entry-detail', { collection: collectionSlug, entry: entryId });
        }
        return adminUrl('entry-editor', { collection: collectionSlug, entry: entryId });
    }

    function buildEntriesPageUrl(nextState) {
        const params = { collection: collectionSlug };
        if (nextState.status) {
            params.status = nextState.status;
        }
        if (nextState.q) {
            params.q = nextState.q;
        }
        if (nextState.sort) {
            params.sort = nextState.sort;
            if (nextState.order) {
                params.order = nextState.order;
            }
        }
        if (normalizePositiveInteger(nextState.page, 1) > 1) {
            params.page = String(normalizePositiveInteger(nextState.page, 1));
        }
        return adminUrl('entries', params);
    }

    function buildEntriesApiUrl(nextState) {
        const params = new URLSearchParams({
            action: 'list',
            collection: collectionSlug,
            page: String(normalizePositiveInteger(nextState.page, 1)),
            per_page: String(normalizePositiveInteger(nextState.per_page, 20)),
        });
        if (nextState.q) {
            params.set('q', nextState.q);
        }
        if (nextState.status) {
            params.set('status', nextState.status);
        }
        if (nextState.sort) {
            params.set('sort', nextState.sort);
            if (nextState.order) {
                params.set('order', nextState.order);
            }
        }
        return apiUrl('/api/admin_entries.php?' + params.toString());
    }

    function updateBrowserUrl(nextState, replaceHistory) {
        const nextUrl = buildEntriesPageUrl(nextState);
        if (replaceHistory) {
            window.history.replaceState({}, '', nextUrl);
        } else {
            window.history.pushState({}, '', nextUrl);
        }
    }

    function getColumnCount() {
        const headRow = document.querySelector('.entries-smart-table .head-row');
        return headRow ? headRow.children.length : Math.max(1, tableFields.length + 3);
    }

    function readStateFromLocation() {
        const currentParams = new URLSearchParams(window.location.search);
        return normalizeListState({
            page: currentParams.get('page') || 1,
            per_page: currentParams.get('per_page') || listState.per_page,
            q: currentParams.get('q') || '',
            sort: currentParams.get('sort') || listState.sort,
            order: currentParams.get('order') || listState.order,
            status: currentParams.get('status') || '',
            total: listState.total,
            total_pages: listState.total_pages,
        });
    }

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

    function setListLoading(isLoading) {
        refreshTableElements();
        entriesTable?.classList.toggle('is-loading', isLoading);
        if (paginationContainer) {
            paginationContainer.setAttribute('aria-busy', isLoading ? 'true' : 'false');
        }
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
        const nextState = Object.assign({}, listState, {
            status: value || '',
            page: 1,
        });
        window.location.href = buildEntriesPageUrl(nextState);
    }

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

    function renderFieldCell(entry, field) {
        const fieldName = field && field.name ? String(field.name) : '';
        if (!fieldName) {
            return '<span class="ft-list-empty" aria-label="' + escapeHtml(t('no_value', 'No value')) + '">–</span>';
        }
        if (entry.__cells && entry.__cells[fieldName + '_html']) {
            return entry.__cells[fieldName + '_html'];
        }
        if (entry.__cells && entry.__cells[fieldName]) {
            return escapeHtml(entry.__cells[fieldName]);
        }
        if (entry[fieldName] != null) {
            return escapeHtml(entry[fieldName]);
        }
        return '<span class="ft-list-empty" aria-label="' + escapeHtml(t('no_value', 'No value')) + '">–</span>';
    }

    function renderEntryRow(entry) {
        const entryId = entry && entry.id ? String(entry.id) : '';
        const title = entry && entry.__cells && entry.__cells.__title ? entry.__cells.__title : (entry.title || entry.slug || entryId || 'Untitled');
        const normalizedStatus = normalizeStatus(entry && entry.__cells ? entry.__cells.__status : entry.status);
        const statusClassMap = {
            pending_review: ' pending review-status',
            approved: ' approved review-status',
            rejected: ' rejected review-status',
            published: '',
            unpublished: '',
            scheduled: '',
            trashed: '',
        };
        const statusClass = normalizedStatus + (statusClassMap[normalizedStatus] || '');
        const statusText = statusLabel(normalizedStatus);
        const reviewer = entry && entry.reviewer ? String(entry.reviewer) : '';
        const reviewedBy = entry && entry.reviewed_by ? String(entry.reviewed_by) : '';
        const reviewTitle = reviewer
            ? ' title="Assigned to: ' + escapeHtml(reviewer) + '"'
            : (reviewedBy ? ' title="Reviewed by: ' + escapeHtml(reviewedBy) + '"' : '');
        const titleUrl = buildTitleUrl(entry);
        const editUrl = buildEditUrl(entry);
        const subEntryCount = Number(entry && entry.__sub_entry_count ? entry.__sub_entry_count : 0);
        const isTrashedEntry = normalizedStatus === 'trashed';
        const allowDragHandle = hasDragColumn
            ? '<td class="cell cell-drag" aria-label="' + escapeHtml(t('drag_to_reorder', 'Drag to reorder')) + '">' +
                (canDragCurrentView()
                    ? '<span class="entry-drag-handle" aria-hidden="true">' + icon('dots-six-vertical', { size: 16 }) + '</span>'
                    : '') +
            '</td>'
            : '';

        const fieldCells = tableFields.map(function (field) {
            const width = escapeHtml(field.width || '240px');
            const minWidth = escapeHtml(field.min_width || field.width || '240px');
            return '<td class="cell cell-field" style="--col-field: ' + width + '; --col-field-min: ' + minWidth + '">' +
                renderFieldCell(entry, field) +
            '</td>';
        }).join('');

        return '' +
            '<tr class="table-row" data-entry-id="' + escapeHtml(entryId) + '">' +
                '<td class="cell cell-checkbox" aria-label="' + escapeHtml(t('select_entry', 'Select entry')) + '">' +
                    '<input type="checkbox" class="entry-checkbox" value="' + escapeHtml(entryId) + '" aria-label="' + escapeHtml(t('select_entry', 'Select entry') + ' ' + title) + '">' +
                '</td>' +
                allowDragHandle +
                (hasSubCollections
                    ? '<td class="cell cell-sub-entries">' +
                        (subEntryCount > 0
                            ? '<a href="' + escapeHtml(titleUrl) + '" class="sub-entry-count-badge" title="' + escapeHtml(String(subEntryCount) + ' sub-entries') + '" style="display: inline-flex; align-items: center; justify-content: center; min-width: 32px; height: 24px; padding: 0 8px; background: var(--primary-100, #e0e7ff); color: var(--primary-700, #4338ca); border-radius: 12px; font-size: 12px; font-weight: 600; text-decoration: none; transition: all 0.2s;"><span style="margin-right: 2px;">+</span>' + escapeHtml(subEntryCount) + '</a>'
                            : '<span style="color: var(--text-tertiary, #9ca3af); font-size: 12px;">–</span>') +
                    '</td>'
                    : '') +
                '<td class="cell cell-title">' +
                    '<a href="' + escapeHtml(titleUrl) + '" class="title-text">' + escapeHtml(title) + '</a>' +
                '</td>' +
                '<td class="cell cell-status">' +
                    '<span class="status-badge ' + escapeHtml(statusClass.trim()) + '" data-status="' + escapeHtml(normalizedStatus) + '" aria-label="' + escapeHtml(t('status', 'Status') + ': ' + statusText) + '"' + reviewTitle + '>' + escapeHtml(statusText) + '</span>' +
                    (normalizedStatus === 'pending_review' && reviewer
                        ? '<div class="reviewer-indicator" title="Assigned to: ' + escapeHtml(reviewer) + '">' + icon('user', { size: 12 }) + '</div>'
                        : '') +
                '</td>' +
                fieldCells +
                '<td class="cell cell-actions">' +
                    '<div class="cell-actions-inner">' +
                        '<a class="btn-icon btn-icon--edit" href="' + escapeHtml(editUrl) + '" aria-label="' + escapeHtml(t('edit_entry', 'Edit entry') + ' ' + title) + '" title="' + escapeHtml(t('edit_entry', 'Edit entry')) + '">' +
                            icon('pencil-simple', { size: 16 }) +
                        '</a>' +
                        (!isTrashedEntry
                            ? '<button type="button" class="btn-icon duplicate-entry-btn" data-entry-id="' + escapeHtml(entryId) + '" aria-label="' + escapeHtml('Duplicate ' + title) + '" title="Duplicate">' + icon('copy', { size: 16 }) + '</button>'
                            : '') +
                        (isTrashedEntry
                            ? '<button type="button" class="btn-icon restore-entry-btn" data-entry-id="' + escapeHtml(entryId) + '" aria-label="' + escapeHtml('Restore ' + title) + '" title="Restore">' + icon('arrow-counter-clockwise', { size: 16 }) + '</button>'
                            : '<button type="button" class="btn-icon btn-icon--danger delete-entry-btn" data-entry-id="' + escapeHtml(entryId) + '" aria-label="' + escapeHtml('Move to trash ' + title) + '" title="Move to trash">' + icon('trash', { size: 16 }) + '</button>') +
                    '</div>' +
                '</td>' +
            '</tr>';
    }

    function renderEntriesTable(entries) {
        refreshTableElements();
        if (!tableBody) {
            return;
        }
        if (!Array.isArray(entries) || entries.length === 0) {
            tableBody.innerHTML = '<tr class="table-row table-row--empty"><td class="cell cell-empty" colspan="' + getColumnCount() + '">No entries found.</td></tr>';
            return;
        }
        tableBody.innerHTML = entries.map(renderEntryRow).join('');
    }

    function buildPaginationMarkup(meta, visibleCount) {
        const total = Math.max(0, Number(meta.total || 0));
        const page = Math.max(1, Number(meta.page || 1));
        const perPage = Math.max(1, Number(meta.per_page || listState.per_page || 20));
        const totalPages = Math.max(1, Number(meta.total_pages || 1));
        const rangeStart = total > 0 ? ((page - 1) * perPage) + 1 : 0;
        const rangeEnd = total > 0 ? Math.min(rangeStart + Math.max(visibleCount, 0) - 1, total) : 0;
        const windowStart = Math.max(1, Math.min(page - 2, Math.max(1, totalPages - 4)));
        const windowEnd = Math.min(totalPages, windowStart + 4);

        let controls = '';
        if (totalPages > 1) {
            if (page > 1) {
                const prevState = Object.assign({}, listState, meta, { page: page - 1 });
                controls += '<a class="entries-pagination__link entries-pagination__link--nav" href="' + escapeHtml(buildEntriesPageUrl(prevState)) + '" data-entries-page="' + escapeHtml(page - 1) + '">Previous</a>';
            } else {
                controls += '<span class="entries-pagination__link entries-pagination__link--nav is-disabled" aria-disabled="true">Previous</span>';
            }

            controls += '<div class="entries-pagination__pages">';
            for (let pageNumber = windowStart; pageNumber <= windowEnd; pageNumber += 1) {
                if (pageNumber === page) {
                    controls += '<span class="entries-pagination__link is-active" aria-current="page">' + escapeHtml(pageNumber) + '</span>';
                } else {
                    const pageState = Object.assign({}, listState, meta, { page: pageNumber });
                    controls += '<a class="entries-pagination__link" href="' + escapeHtml(buildEntriesPageUrl(pageState)) + '" data-entries-page="' + escapeHtml(pageNumber) + '">' + escapeHtml(pageNumber) + '</a>';
                }
            }
            controls += '</div>';

            if (page < totalPages) {
                const nextState = Object.assign({}, listState, meta, { page: page + 1 });
                controls += '<a class="entries-pagination__link entries-pagination__link--nav" href="' + escapeHtml(buildEntriesPageUrl(nextState)) + '" data-entries-page="' + escapeHtml(page + 1) + '">Next</a>';
            } else {
                controls += '<span class="entries-pagination__link entries-pagination__link--nav is-disabled" aria-disabled="true">Next</span>';
            }
        }

        return '' +
            '<div class="entries-pagination__summary">' +
                (total > 0 ? ('Showing ' + rangeStart + '-' + rangeEnd + ' of ' + total + ' entries') : 'No entries found') +
            '</div>' +
            '<div class="entries-pagination__controls">' + controls + '</div>';
    }

    function renderPagination(meta, visibleCount) {
        refreshTableElements();
        if (!paginationContainer) {
            return;
        }
        paginationContainer.innerHTML = buildPaginationMarkup(meta, visibleCount);
    }

    async function fetchEntriesPage(nextState, options) {
        options = options || {};
        const normalizedState = normalizeListState(nextState);

        if (normalizedState.status !== listState.status && normalizedState.status !== (window.entriesStatusFilter || '')) {
            window.location.href = buildEntriesPageUrl(normalizedState);
            return;
        }

        listRequestId += 1;
        const requestId = listRequestId;
        if (listRequestController && typeof listRequestController.abort === 'function') {
            listRequestController.abort();
        }
        listRequestController = typeof AbortController !== 'undefined' ? new AbortController() : null;
        setListLoading(true);

        try {
            const response = await fetch(buildEntriesApiUrl(normalizedState), {
                credentials: 'same-origin',
                signal: listRequestController ? listRequestController.signal : undefined,
            });
            const text = await response.text();
            let payload = null;
            try {
                payload = JSON.parse(text);
            } catch (error) {
                throw new Error('Invalid JSON response');
            }
            if (!response.ok || payload?.status === 'error') {
                throw new Error(payload?.message || ('HTTP ' + response.status));
            }

            if (requestId !== listRequestId) {
                return;
            }

            const data = payload && payload.data ? payload.data : {};
            const entries = Array.isArray(data.entries) ? data.entries : [];
            listState = normalizeListState(Object.assign({}, normalizedState, data, {
                total: data.total,
                total_pages: data.total_pages,
                page: data.page,
                per_page: data.per_page,
            }));

            renderEntriesTable(entries);
            refreshTableElements();
            clearSelection();
            renderPagination(listState, entries.length);
            updateBulkUI();
            syncSearchInputs(listState.q);
            syncStatusFilters(listState.status);
            updateBrowserUrl(listState, Boolean(options.replaceHistory));
            initializeEntryDragHandlers();
            initializeViewportScrollState();
        } catch (error) {
            if (error && error.name === 'AbortError') {
                return;
            }
            BirklyFeedback.toast('Error: ' + (error && error.message ? error.message : 'Failed to load entries'), 'error');
        } finally {
            if (requestId === listRequestId) {
                setListLoading(false);
            }
        }
    }

    function handleSearchInput(value) {
        syncSearchInputs(value);
        window.clearTimeout(searchDebounceId);
        searchDebounceId = window.setTimeout(function () {
            const nextQuery = String(value || '').trim();
            if (nextQuery === listState.q && Number(listState.page || 1) === 1) {
                return;
            }
            fetchEntriesPage(Object.assign({}, listState, {
                q: nextQuery,
                page: 1,
            }), { replaceHistory: true });
        }, 250);
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

    document.addEventListener('change', function (event) {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        if (target.id === 'select-all-entries') {
            handleSelectAll();
            return;
        }

        if (target.classList.contains('entry-checkbox')) {
            if (target.checked) {
                selectedEntries.add(target.value);
            } else {
                selectedEntries.delete(target.value);
            }
            updateBulkUI();
        }
    });

    document.addEventListener('click', async function (event) {
        if (!(event.target instanceof Element)) {
            return;
        }
        const paginationLink = event.target.closest('[data-entries-page]');
        if (paginationLink && paginationContainer && paginationContainer.contains(paginationLink)) {
            event.preventDefault();
            const pageNumber = normalizePositiveInteger(paginationLink.getAttribute('data-entries-page'), 1);
            if (pageNumber !== listState.page) {
                fetchEntriesPage(Object.assign({}, listState, { page: pageNumber }), { replaceHistory: false });
            }
            return;
        }

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

    function EntryTableDrag(options) {
        this.tbody = options.container;
        this.rowSelector = options.rowSelector || '.table-row:not(.table-row--empty)';
        this.handleSelector = options.handleSelector || '.entry-drag-handle';
        this.onReorder = options.onReorder || function () {};
        this.dragging = false;
        this.draggedRow = null;
        this.placeholder = null;
        this.fromIndex = -1;
        this.activePointerId = null;

        this.onPointerDown = this.onPointerDown.bind(this);
        this.onPointerMove = this.onPointerMove.bind(this);
        this.onPointerUp = this.onPointerUp.bind(this);

        if (this.tbody) {
            this.tbody.addEventListener('pointerdown', this.onPointerDown);
        }
    }

    EntryTableDrag.prototype.getRows = function () {
        if (!this.tbody) {
            return [];
        }
        return Array.from(this.tbody.querySelectorAll(this.rowSelector));
    };

    EntryTableDrag.prototype.onPointerDown = function (event) {
        if (this.dragging || (typeof event.button === 'number' && event.button !== 0)) {
            return;
        }

        const handle = event.target.closest(this.handleSelector);
        if (!handle) {
            return;
        }

        const row = handle.closest(this.rowSelector);
        if (!row || !this.tbody.contains(row)) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        this.dragging = true;
        this.draggedRow = row;
        this.fromIndex = this.getRows().indexOf(row);
        this.activePointerId = typeof event.pointerId === 'number' ? event.pointerId : null;

        this.placeholder = document.createElement('tr');
        this.placeholder.className = 'drag-placeholder-row';
        this.placeholder.setAttribute('aria-hidden', 'true');
        const cell = document.createElement('td');
        cell.colSpan = Math.max(row.cells.length, 1);
        cell.className = 'drag-placeholder-cell';
        this.placeholder.appendChild(cell);

        row.parentNode.insertBefore(this.placeholder, row);
        row.classList.add('is-drag-source');
        row.style.display = 'none';

        document.addEventListener('pointermove', this.onPointerMove);
        document.addEventListener('pointerup', this.onPointerUp);
        document.addEventListener('pointercancel', this.onPointerUp);
        document.body.style.cursor = 'grabbing';
    };

    EntryTableDrag.prototype.findRowAt = function (clientY) {
        const rows = this.getRows();
        for (let index = 0; index < rows.length; index += 1) {
            const row = rows[index];
            if (row === this.draggedRow || row.style.display === 'none') {
                continue;
            }
            const rect = row.getBoundingClientRect();
            if (clientY >= rect.top && clientY <= rect.bottom) {
                return row;
            }
        }
        return null;
    };

    EntryTableDrag.prototype.movePlaceholder = function (clientY) {
        if (!this.placeholder) {
            return;
        }

        const target = this.findRowAt(clientY);
        if (!target) {
            return;
        }

        const rect = target.getBoundingClientRect();
        const insertBefore = clientY < rect.top + rect.height / 2;
        const parent = target.parentNode;
        if (!parent) {
            return;
        }

        if (insertBefore) {
            if (this.placeholder.nextSibling !== target) {
                parent.insertBefore(this.placeholder, target);
            }
        } else if (this.placeholder.previousSibling !== target) {
            parent.insertBefore(this.placeholder, target.nextSibling);
        }
    };

    EntryTableDrag.prototype.getPlaceholderIndex = function () {
        if (!this.tbody || !this.placeholder) {
            return -1;
        }

        let index = 0;
        const children = this.tbody.children;
        for (let nodeIndex = 0; nodeIndex < children.length; nodeIndex += 1) {
            const node = children[nodeIndex];
            if (node === this.placeholder) {
                return index;
            }
            if (node.matches && node.matches(this.rowSelector) && node !== this.draggedRow) {
                index += 1;
            }
        }
        return -1;
    };

    EntryTableDrag.prototype.onPointerMove = function (event) {
        if (!this.dragging) {
            return;
        }
        if (this.activePointerId !== null && typeof event.pointerId === 'number' && event.pointerId !== this.activePointerId) {
            return;
        }

        event.preventDefault();
        this.movePlaceholder(event.clientY);
    };

    EntryTableDrag.prototype.onPointerUp = function (event) {
        if (!this.dragging) {
            return;
        }
        if (this.activePointerId !== null && typeof event.pointerId === 'number' && event.pointerId !== this.activePointerId) {
            return;
        }

        const toIndex = this.getPlaceholderIndex();
        if (this.placeholder && this.placeholder.parentNode && this.draggedRow) {
            this.placeholder.parentNode.insertBefore(this.draggedRow, this.placeholder);
        }

        const draggedRow = this.draggedRow;
        const fromIndex = this.fromIndex;
        this.cleanup();

        if (draggedRow && toIndex !== -1 && toIndex !== fromIndex) {
            this.onReorder(fromIndex, toIndex, draggedRow);
        }
    };

    EntryTableDrag.prototype.cleanup = function () {
        document.removeEventListener('pointermove', this.onPointerMove);
        document.removeEventListener('pointerup', this.onPointerUp);
        document.removeEventListener('pointercancel', this.onPointerUp);
        document.body.style.cursor = '';

        if (this.draggedRow) {
            this.draggedRow.style.display = '';
            this.draggedRow.classList.remove('is-drag-source');
        }
        if (this.placeholder && this.placeholder.parentNode) {
            this.placeholder.remove();
        }

        this.dragging = false;
        this.draggedRow = null;
        this.placeholder = null;
        this.fromIndex = -1;
        this.activePointerId = null;
    };

    EntryTableDrag.prototype.destroy = function () {
        this.cleanup();
        if (this.tbody) {
            this.tbody.removeEventListener('pointerdown', this.onPointerDown);
        }
        this.tbody = null;
    };

    function initializeEntryDragHandlers() {
        const tableBody = document.querySelector('.table-body');
        if (!tableBody || !canDragCurrentView()) {
            if (entrySorter) {
                entrySorter.destroy();
                entrySorter = null;
            }
            return;
        }

        if (entrySorter) {
            entrySorter.destroy();
        }

        entrySorter = new EntryTableDrag({
            container: tableBody,
            rowSelector: '.table-row:not(.table-row--empty)',
            handleSelector: '.entry-drag-handle',
            onReorder: function (fromIndex, toIndex, draggedRow) {
                const draggedEntryId = draggedRow?.dataset?.entryId || '';
                if (!draggedEntryId) {
                    return;
                }

                const rows = Array.from(tableBody.querySelectorAll('.table-row:not(.table-row--empty)'))
                    .filter(function (row) {
                        return row !== draggedRow && row.style.display !== 'none';
                    });

                let targetEntryId = null;
                let insertBefore = true;
                if (toIndex < rows.length) {
                    targetEntryId = rows[toIndex].dataset.entryId;
                    insertBefore = true;
                } else if (rows.length > 0) {
                    targetEntryId = rows[rows.length - 1].dataset.entryId;
                    insertBefore = false;
                }

                if (targetEntryId && draggedEntryId !== targetEntryId) {
                    reorderEntries(draggedEntryId, targetEntryId, insertBefore);
                }
            },
        });
    }

    function initializeViewportScrollState() {
        document.querySelectorAll('.entries-smart-table .table-viewport').forEach(function (viewport) {
            if (viewport.dataset.entriesViewportBound === 'true') {
                const table = viewport.closest('.entries-smart-table');
                if (table) {
                    table.classList.toggle('is-scrolled-x', viewport.scrollLeft > 1);
                }
                return;
            }
            const table = viewport.closest('.entries-smart-table');
            const onScroll = function () {
                if (!table) {
                    return;
                }
                table.classList.toggle('is-scrolled-x', viewport.scrollLeft > 1);
            };
            viewport.dataset.entriesViewportBound = 'true';
            viewport.addEventListener('scroll', onScroll, { passive: true });
            onScroll();
        });
    }

    window.addEventListener('popstate', function () {
        const nextState = readStateFromLocation();
        syncSearchInputs(nextState.q);
        syncStatusFilters(nextState.status);
        if (nextState.status !== listState.status) {
            window.location.href = buildEntriesPageUrl(nextState);
            return;
        }
        fetchEntriesPage(nextState, { replaceHistory: true });
    });

    updateBulkUI();
    initializeViewportScrollState();
    window.setTimeout(initializeEntryDragHandlers, 100);
})();

