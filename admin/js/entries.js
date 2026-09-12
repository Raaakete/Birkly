/**
 * Entries page actions and bulk operations.
 */
(function () {
    'use strict';

    function t(key, fallback, params) {
        var text = (window.Birkly && window.Birkly.i18n && typeof window.Birkly.i18n.t === 'function')
            ? window.Birkly.i18n.t('admin.' + key, fallback != null ? fallback : key)
            : (fallback != null ? fallback : key);
        if (params && typeof params === 'object') {
            Object.keys(params).forEach(function (name) {
                text = text.split('{' + name + '}').join(String(params[name]));
            });
        }
        return text;
    }

    function toastError(error) {
        var message = error && error.message ? error.message : t('entries_load_failed', 'Failed to load entries');
        BirklyFeedback.toast(t('entries_error_prefix', 'Error: {message}', { message: message }), 'error');
    }

    function bulkCountLabel(oneKey, manyKey, count, oneFallback, manyFallback) {
        if (count === 1) {
            return t(oneKey, oneFallback);
        }
        return t(manyKey, manyFallback).replace('%count%', String(count));
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
    const entriesL2Actions = Boolean(window.entriesL2Actions);
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
        if (entriesL2Actions && entriesTable && !entriesTable.classList.contains('entries-l2-actions')) {
            entriesTable.classList.add('entries-l2-actions');
        }
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
            bulkActionsToolbar.hidden = count === 0;
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
            throw new Error(t('entries_invalid_json', 'Invalid JSON response'));
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
            toastError(error);
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

        const progress = showProgress(t('entries_bulk_updating_fields', 'Updating field values…'));
        try {
            const result = await performBulkFieldEdit(Array.from(selectedEntries), fieldNameValue, fieldValueValue);
            progress.remove();
            BirklyFeedback.toast(result.message || t('entries_bulk_field_updated', 'Field updated successfully.'), 'success');
            window.location.reload();
        } catch (error) {
            progress.remove();
            toastError(error);
        }
    }

    async function handleDeleteEntry(entryId) {
        if (!(await BirklyFeedback.confirm(t('entries_move_to_trash_confirm', 'Move this entry to trash?')))) {
            return;
        }
        const progress = showProgress(t('entries_moving_to_trash', 'Moving entry to trash…'));
        try {
            await performEntryAction('delete', entryId);
            progress.remove();
            window.location.reload();
        } catch (error) {
            progress.remove();
            toastError(error);
        }
    }

    async function handleRestoreEntry(entryId) {
        if (!(await BirklyFeedback.confirm(t('entries_restore_confirm', 'Restore this entry?')))) {
            return;
        }
        const progress = showProgress(t('entries_restoring_entry', 'Restoring entry…'));
        try {
            await performEntryAction('restore', entryId);
            progress.remove();
            window.location.reload();
        } catch (error) {
            progress.remove();
            toastError(error);
        }
    }

    async function handleDuplicateEntry(entryId) {
        const progress = showProgress(t('entries_duplicating_entry', 'Duplicating entry…'));
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
            toastError(error);
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
        var count = selectedEntries.size;
        runBulkAction(
            'publish',
            t('entries_bulk_publish_confirm', 'Publish the selected entries?'),
            t('entries_bulk_publish_progress', 'Publishing entries…'),
            bulkCountLabel('entries_bulk_published_one', 'entries_bulk_published', count, 'Published 1 entry.', 'Published %count% entries.')
        );
    });

    bulkUnpublishBtn?.addEventListener('click', async function () {
        var count = selectedEntries.size;
        runBulkAction(
            'unpublish',
            t('entries_bulk_unpublish_confirm', 'Unpublish the selected entries?'),
            t('entries_bulk_unpublish_progress', 'Unpublishing entries…'),
            bulkCountLabel('entries_bulk_unpublished_one', 'entries_bulk_unpublished', count, 'Unpublished 1 entry.', 'Unpublished %count% entries.')
        );
    });

    bulkDeleteBtn?.addEventListener('click', async function () {
        var count = selectedEntries.size;
        runBulkAction(
            'delete',
            t('entries_bulk_trash_confirm', 'Move the selected entries to trash?'),
            t('entries_bulk_trash_progress', 'Moving entries to trash…'),
            bulkCountLabel('entries_bulk_trashed_one', 'entries_bulk_trashed', count, 'Moved 1 entry to trash.', 'Moved %count% entries to trash.')
        );
    });

    bulkRestoreBtn?.addEventListener('click', async function () {
        var count = selectedEntries.size;
        runBulkAction(
            'restore',
            t('entries_bulk_restore_confirm', 'Restore the selected entries?'),
            t('entries_bulk_restore_progress', 'Restoring entries…'),
            bulkCountLabel('entries_bulk_restored_one', 'entries_bulk_restored', count, 'Restored 1 entry.', 'Restored %count% entries.')
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

    function copyTextToClipboard(text, successMessage) {
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                BirklyFeedback.toast(successMessage || t('copied', 'Copied'), 'success');
            }).catch(function () {
                BirklyFeedback.toast(t('copy_failed', 'Could not copy to clipboard'), 'error');
            });
            return;
        }
        BirklyFeedback.toast(t('copy_failed', 'Could not copy to clipboard'), 'error');
    }

    async function handlePublishEntry(entryId, publish) {
        const action = publish ? 'publish' : 'unpublish';
        const confirmMsg = publish
            ? t('entries_publish_confirm', 'Publish this entry?')
            : t('entries_unpublish_confirm', 'Unpublish this entry?');
        if (!(await BirklyFeedback.confirm(confirmMsg))) {
            return;
        }
        const progress = showProgress(publish ? t('entries_publishing_entry', 'Publishing entry…') : t('entries_unpublishing_entry', 'Unpublishing entry…'));
        try {
            await performBulkOperation(action, [entryId]);
            progress.remove();
            window.location.reload();
        } catch (error) {
            progress.remove();
            toastError(error);
        }
    }

    function renderL2ActionsCell(entry, entryId, title, editUrl, normalizedStatus, isTrashedEntry) {
        const slug = entry && entry.slug ? String(entry.slug) : '';
        if (isTrashedEntry) {
            return '<td class="cell cell-actions"><div class="cell-actions-inner">' +
                '<button type="button" class="btn-icon btn-icon-sm restore-entry-btn" data-entry-id="' + escapeHtml(entryId) + '" aria-label="' + escapeHtml(t('restore', 'Restore') + ' ' + title) + '" title="' + escapeHtml(t('restore', 'Restore')) + '">' +
                    icon('arrow-counter-clockwise', { size: 16 }) +
                '</button>' +
            '</div></td>';
        }

        const publishBtn = normalizedStatus === 'published'
            ? '<button type="button" class="btn-icon btn-icon-sm entry-unpublish-btn" data-entry-id="' + escapeHtml(entryId) + '" aria-label="' + escapeHtml(t('unpublish', 'Unpublish') + ' ' + title) + '" title="' + escapeHtml(t('unpublish', 'Unpublish')) + '">' +
                icon('archive', { size: 16 }) +
              '</button>'
            : '<button type="button" class="btn-icon btn-icon-sm entry-publish-btn" data-entry-id="' + escapeHtml(entryId) + '" aria-label="' + escapeHtml(t('publish', 'Publish') + ' ' + title) + '" title="' + escapeHtml(t('publish', 'Publish')) + '">' +
                icon('globe', { size: 16 }) +
              '</button>';

        const moreMenu = '' +
            '<div class="dropdown entry-row-more-menu">' +
                '<button type="button" class="dropdown-trigger btn-icon btn-icon-sm" aria-label="' + escapeHtml(t('more', 'More')) + '" title="' + escapeHtml(t('more', 'More')) + '" aria-expanded="false" aria-haspopup="true">' +
                    icon('dots-three-vertical', { size: 16 }) +
                '</button>' +
                '<div class="dropdown-menu dropdown-menu-right dropdown-menu--up">' +
                    '<button type="button" class="dropdown-item dropdown-item-danger delete-entry-btn" data-entry-id="' + escapeHtml(entryId) + '">' +
                        icon('trash', { size: 16, class: 'dropdown-item-icon' }) +
                        '<span class="dropdown-item-label">' + escapeHtml(t('entries_bulk_move_to_trash', 'Move to trash')) + '</span>' +
                    '</button>' +
                    '<button type="button" class="dropdown-item duplicate-entry-btn" data-entry-id="' + escapeHtml(entryId) + '">' +
                        icon('copy', { size: 16, class: 'dropdown-item-icon' }) +
                        '<span class="dropdown-item-label">' + escapeHtml(t('duplicate', 'Duplicate')) + '</span>' +
                    '</button>' +
                    (slug
                        ? '<button type="button" class="dropdown-item entry-copy-slug-btn" data-slug="' + escapeHtml(slug) + '">' +
                            icon('link', { size: 16, class: 'dropdown-item-icon' }) +
                            '<span class="dropdown-item-label">' + escapeHtml(t('copy_slug', 'Copy slug')) + '</span>' +
                        '</button>'
                        : '') +
                    '<button type="button" class="dropdown-item entry-copy-id-btn" data-entry-id="' + escapeHtml(entryId) + '">' +
                        icon('hash', { size: 16, class: 'dropdown-item-icon' }) +
                        '<span class="dropdown-item-label">' + escapeHtml(t('copy_id', 'Copy id')) + '</span>' +
                    '</button>' +
                '</div>' +
            '</div>';

        return '<td class="cell cell-actions"><div class="cell-actions-inner">' +
            '<div class="cell-actions-primary">' +
            '<a class="btn-icon btn-icon-sm btn-icon--edit" href="' + escapeHtml(editUrl) + '" aria-label="' + escapeHtml(t('edit_entry', 'Edit entry') + ' ' + title) + '" title="' + escapeHtml(t('edit', 'Edit')) + '">' +
                icon('pencil-simple', { size: 16 }) +
            '</a>' +
            publishBtn +
            '</div>' +
            '<div class="cell-actions-more">' +
            moreMenu +
            '</div>' +
        '</div></td>';
    }

    function renderEntryRow(entry) {
        const entryId = entry && entry.id ? String(entry.id) : '';
        const title = entry && entry.__cells && entry.__cells.__title ? entry.__cells.__title : (entry.title || entry.slug || entryId || t('untitled', 'Untitled'));
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
            ? ' title="' + escapeHtml(t('entries_assigned_to', 'Assigned to: {name}', { name: reviewer })) + '"'
            : (reviewedBy ? ' title="' + escapeHtml(t('entries_reviewed_by', 'Reviewed by: {name}', { name: reviewedBy })) + '"' : '');
        const titleUrl = buildTitleUrl(entry);
        const editUrl = buildEditUrl(entry);
        const subEntryCount = Number(entry && entry.__sub_entry_count ? entry.__sub_entry_count : 0);
        const isTrashedEntry = normalizedStatus === 'trashed';
        const allowDragHandle = hasDragColumn
            ? '<td class="cell cell-drag">' +
                (canDragCurrentView()
                    ? '<button type="button" class="entry-drag-handle" aria-label="' + escapeHtml(t('drag_to_reorder', 'Drag to reorder')) + '" tabindex="-1">' + icon('dots-six-vertical', { size: 16 }) + '</button>'
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
                            ? '<a href="' + escapeHtml(titleUrl) + '" class="badge badge-accent badge-count sub-entry-count-badge" title="' + escapeHtml(t('entries_sub_entries_count', '{count} sub-entries', { count: subEntryCount })) + '">+' + escapeHtml(subEntryCount) + '</a>'
                            : '<span class="u-text-xs u-fg-tertiary">&ndash;</span>') +
                    '</td>'
                    : '') +
                '<td class="cell cell-title">' +
                    '<a href="' + escapeHtml(titleUrl) + '" class="title-text">' + escapeHtml(title) + '</a>' +
                '</td>' +
                '<td class="cell cell-status">' +
                    '<span class="status-badge ' + escapeHtml(statusClass.trim()) + '" data-status="' + escapeHtml(normalizedStatus) + '" aria-label="' + escapeHtml(t('status', 'Status') + ': ' + statusText) + '"' + reviewTitle + '>' + escapeHtml(statusText) + '</span>' +
                    (normalizedStatus === 'pending_review' && reviewer
                        ? '<div class="reviewer-indicator" title="' + escapeHtml(t('entries_assigned_to', 'Assigned to: {name}', { name: reviewer })) + '">' + icon('user', { size: 12 }) + '</div>'
                        : '') +
                '</td>' +
                fieldCells +
                (entriesL2Actions
                    ? renderL2ActionsCell(entry, entryId, title, editUrl, normalizedStatus, isTrashedEntry)
                    : ('<td class="cell cell-actions">' +
                        '<div class="cell-actions-inner">' +
                            '<a class="btn-icon btn-icon-sm btn-icon--edit" href="' + escapeHtml(editUrl) + '" aria-label="' + escapeHtml(t('edit_entry', 'Edit entry') + ' ' + title) + '" title="' + escapeHtml(t('edit_entry', 'Edit entry')) + '">' +
                                icon('pencil-simple', { size: 16 }) +
                            '</a>' +
                            (!isTrashedEntry
                                ? '<button type="button" class="btn-icon btn-icon-sm duplicate-entry-btn" data-entry-id="' + escapeHtml(entryId) + '" aria-label="' + escapeHtml('Duplicate ' + title) + '" title="Duplicate">' + icon('copy', { size: 16 }) + '</button>'
                                : '') +
                            (isTrashedEntry
                                ? '<button type="button" class="btn-icon btn-icon-sm restore-entry-btn" data-entry-id="' + escapeHtml(entryId) + '" aria-label="' + escapeHtml('Restore ' + title) + '" title="Restore">' + icon('arrow-counter-clockwise', { size: 16 }) + '</button>'
                                : '<button type="button" class="btn-icon btn-icon-sm btn-icon--danger delete-entry-btn" data-entry-id="' + escapeHtml(entryId) + '" aria-label="' + escapeHtml('Move to trash ' + title) + '" title="Move to trash">' + icon('trash', { size: 16 }) + '</button>') +
                        '</div>' +
                    '</td>')) +
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
                controls += '<a class="btn btn-secondary btn-sm" href="' + escapeHtml(buildEntriesPageUrl(prevState)) + '" data-entries-page="' + escapeHtml(page - 1) + '">Previous</a>';
            } else {
                controls += '<span class="btn btn-secondary btn-sm" aria-disabled="true">Previous</span>';
            }

            controls += '<div class="entries-pagination__pages">';
            for (let pageNumber = windowStart; pageNumber <= windowEnd; pageNumber += 1) {
                if (pageNumber === page) {
                    controls += '<span class="btn btn-primary btn-sm" aria-current="page">' + escapeHtml(pageNumber) + '</span>';
                } else {
                    const pageState = Object.assign({}, listState, meta, { page: pageNumber });
                    controls += '<a class="btn btn-secondary btn-sm" href="' + escapeHtml(buildEntriesPageUrl(pageState)) + '" data-entries-page="' + escapeHtml(pageNumber) + '">' + escapeHtml(pageNumber) + '</a>';
                }
            }
            controls += '</div>';

            if (page < totalPages) {
                const nextState = Object.assign({}, listState, meta, { page: page + 1 });
                controls += '<a class="btn btn-secondary btn-sm" href="' + escapeHtml(buildEntriesPageUrl(nextState)) + '" data-entries-page="' + escapeHtml(page + 1) + '">Next</a>';
            } else {
                controls += '<span class="btn btn-secondary btn-sm" aria-disabled="true">Next</span>';
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
                throw new Error(t('entries_invalid_json', 'Invalid JSON response'));
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
            BirklyFeedback.toast(t('entries_error_prefix', 'Error: {message}', {
                message: (error && error.message) ? error.message : t('entries_load_failed', 'Failed to load entries'),
            }), 'error');
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
            return;
        }

        const publishButton = event.target.closest('.entry-publish-btn');
        if (publishButton) {
            handlePublishEntry(publishButton.dataset.entryId || '', true);
            return;
        }

        const unpublishButton = event.target.closest('.entry-unpublish-btn');
        if (unpublishButton) {
            handlePublishEntry(unpublishButton.dataset.entryId || '', false);
            return;
        }

        const copySlugButton = event.target.closest('.entry-copy-slug-btn');
        if (copySlugButton) {
            copyTextToClipboard(copySlugButton.dataset.slug || '', t('copy_slug_success', 'Slug copied'));
            return;
        }

        const copyIdButton = event.target.closest('.entry-copy-id-btn');
        if (copyIdButton) {
            copyTextToClipboard(copyIdButton.dataset.entryId || '', t('copy_id_success', 'ID copied'));
        }
    });

    async function saveEntryOrder(orderedIds) {
        if (!Array.isArray(orderedIds) || orderedIds.length === 0) {
            return;
        }

        try {
            const response = await requestJson('/api/content.php?action=set_entry_order', {
                collection: collectionSlug,
                order: orderedIds,
                _csrf: getCSRFToken(),
            });

            if (response.status !== 'ok') {
                throw new Error(response.message || 'Failed to save entry order');
            }

            const tableBodyEl = entriesTableBody();
            const entryMap = {};
            orderedIds.forEach(function (entryId) {
                const row = tableBodyEl ? tableBodyEl.querySelector('.table-row[data-entry-id="' + entryId + '"]') : null;
                if (row) {
                    entryMap[entryId] = row;
                }
            });
            if (!tableBodyEl) {
                return;
            }
            tableBodyEl.innerHTML = '';
            orderedIds.forEach(function (entryId) {
                if (entryMap[entryId]) {
                    tableBodyEl.appendChild(entryMap[entryId]);
                }
            });
            refreshTableElements();
            initializeEntryDragHandlers();
        } catch (error) {
            BirklyFeedback.toast(t('entries_save_order_failed', 'Error saving entry order: {message}', { message: error.message }), 'error');
            throw error;
        }
    }

    function getEntryRowOrder(tableBodyEl) {
        if (!tableBodyEl) {
            return [];
        }
        return Array.from(tableBodyEl.querySelectorAll('.table-row:not(.table-row--empty)'))
            .map(function (row) { return row.dataset.entryId || ''; })
            .filter(Boolean);
    }

    function entriesTableRoot() {
        return document.querySelector('.entries-smart-table');
    }

    function entriesTableBody() {
        const root = entriesTableRoot();
        return root ? root.querySelector('tbody.table-body') : null;
    }

    function entriesTableHasDragHandles() {
        const tbody = entriesTableBody();
        return Boolean(tbody && tbody.querySelector('.entry-drag-handle'));
    }

    function EntryTableDrag(options) {
        this.tbody = options.container;
        this.onReorder = options.onReorder || function () {};
        this.dragging = false;
        this.draggedRow = null;
        this.placeholder = null;
        this.orderAtDragStart = [];

        this.onDragStart = this.onDragStart.bind(this);
        this.onDragMove = this.onDragMove.bind(this);
        this.onDragEnd = this.onDragEnd.bind(this);

        if (this.tbody) {
            this.tbody.addEventListener('mousedown', this.onDragStart, true);
        }
    }

    EntryTableDrag.prototype.getRows = function () {
        if (!this.tbody) {
            return [];
        }
        return Array.from(this.tbody.querySelectorAll('.table-row:not(.table-row--empty)'));
    };

    EntryTableDrag.prototype.onDragStart = function (event) {
        if (this.dragging || (typeof event.button === 'number' && event.button !== 0)) {
            return;
        }

        const handle = event.target.closest('.entry-drag-handle');
        if (!handle || !this.tbody || !this.tbody.contains(handle)) {
            return;
        }

        const row = handle.closest('.table-row');
        if (!row || row.classList.contains('table-row--empty') || !this.tbody.contains(row)) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        this.dragging = true;
        this.draggedRow = row;
        this.orderAtDragStart = getEntryRowOrder(this.tbody);

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

        document.addEventListener('mousemove', this.onDragMove);
        document.addEventListener('mouseup', this.onDragEnd);
        document.body.style.cursor = 'grabbing';
        document.body.classList.add('entries-table-dragging');
    };

    EntryTableDrag.prototype.findRowAt = function (clientY) {
        const rows = this.getRows().filter(function (row) {
            return row !== this.draggedRow && row.style.display !== 'none';
        }, this);

        for (let index = 0; index < rows.length; index += 1) {
            const row = rows[index];
            const rect = row.getBoundingClientRect();
            if (clientY >= rect.top && clientY <= rect.bottom) {
                return row;
            }
        }

        let closest = null;
        let minDistance = Infinity;
        for (let index = 0; index < rows.length; index += 1) {
            const row = rows[index];
            const rect = row.getBoundingClientRect();
            const centerY = rect.top + rect.height / 2;
            const distance = Math.abs(clientY - centerY);
            if (distance < minDistance) {
                minDistance = distance;
                closest = row;
            }
        }
        return closest;
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
            return;
        }

        if (this.placeholder.previousSibling !== target) {
            parent.insertBefore(this.placeholder, target.nextSibling);
        }
    };

    EntryTableDrag.prototype.onDragMove = function (event) {
        if (!this.dragging) {
            return;
        }
        event.preventDefault();
        this.movePlaceholder(event.clientY);
    };

    EntryTableDrag.prototype.onDragEnd = function () {
        if (!this.dragging) {
            return;
        }

        if (this.placeholder && this.placeholder.parentNode && this.draggedRow) {
            this.placeholder.parentNode.insertBefore(this.draggedRow, this.placeholder);
        }

        const nextOrder = getEntryRowOrder(this.tbody);
        const startKey = this.orderAtDragStart.join('\u0001');
        const nextKey = nextOrder.join('\u0001');
        this.cleanup();

        if (nextOrder.length > 0 && startKey !== nextKey) {
            this.onReorder(nextOrder);
        }
    };

    EntryTableDrag.prototype.cleanup = function () {
        document.removeEventListener('mousemove', this.onDragMove);
        document.removeEventListener('mouseup', this.onDragEnd);
        document.body.style.cursor = '';
        document.body.classList.remove('entries-table-dragging');

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
        this.orderAtDragStart = [];
    };

    EntryTableDrag.prototype.destroy = function () {
        this.cleanup();
        if (this.tbody) {
            this.tbody.removeEventListener('mousedown', this.onDragStart, true);
        }
        this.tbody = null;
    };

    function initializeEntryDragHandlers() {
        const tableBodyEl = entriesTableBody();
        if (!tableBodyEl || !entriesTableHasDragHandles()) {
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
            container: tableBodyEl,
            onReorder: function (orderedIds) {
                saveEntryOrder(orderedIds);
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
    initializeEntryDragHandlers();
    window.addEventListener('load', initializeEntryDragHandlers);
})();

