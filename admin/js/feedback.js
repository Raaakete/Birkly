/**
 * Admin feedback list — triage and status workflow
 */
(function () {
    'use strict';

    let items = [];
    let selectedId = null;
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    function fbT(key, fallback) {
        if (typeof Birkly !== 'undefined' && Birkly.i18n && typeof Birkly.i18n.t === 'function') {
            return Birkly.i18n.t('admin.' + key, fallback);
        }
        return fallback || key;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    function formatDate(iso) {
        if (!iso) return '—';
        try {
            return new Date(iso).toLocaleString();
        } catch (_) {
            return iso;
        }
    }

    function statusLabel(status) {
        const keys = {
            new: ['feedback_status_new', 'New'],
            in_progress: ['feedback_status_in_progress', 'In progress'],
            resolved: ['feedback_status_resolved', 'Resolved'],
            closed: ['feedback_status_closed', 'Closed'],
        };
        const pair = keys[status];
        return pair ? fbT(pair[0], pair[1]) : status;
    }

    function statusBadge(status) {
        const tones = {
            new: 'badge-info',
            in_progress: 'badge-warning',
            resolved: 'badge-success',
            closed: 'badge-neutral',
        };
        const tone = tones[status] || 'badge-neutral';
        return `<span class="badge ${tone}">${escapeHtml(statusLabel(status))}</span>`;
    }

    function typeLabel(type) {
        const keys = {
            bug: ['feedback_type_bug', 'Bug report'],
            feature: ['feedback_type_feature', 'Feature request'],
            feedback: ['feedback_type_general', 'General feedback'],
            question: ['feedback_type_question', 'Question / support'],
        };
        const pair = keys[type];
        return pair ? fbT(pair[0], pair[1]) : type;
    }

    function categoryLabel(category) {
        const keys = {
            admin: ['feedback_category_admin', 'Admin & settings'],
            content: ['feedback_category_content', 'Content & editor'],
            commerce: ['feedback_category_commerce', 'Commerce'],
            marketplace: ['feedback_category_marketplace', 'Hub & plugins'],
            plugins: ['feedback_category_plugins', 'Installed plugins'],
            ai: ['feedback_category_ai', 'AI & MCP'],
            other: ['feedback_category_other', 'Other'],
        };
        const pair = keys[category];
        return pair ? fbT(pair[0], pair[1]) : (category || fbT('feedback_category_other', 'Other'));
    }

    async function loadFeedback() {
        const loading = document.getElementById('feedback-list-loading');
        const empty = document.getElementById('feedback-list-empty');
        const wrap = document.getElementById('feedback-table-wrap');
        const status = document.getElementById('feedback-filter-status')?.value || '';
        const type = document.getElementById('feedback-filter-type')?.value || '';
        const category = document.getElementById('feedback-filter-category')?.value || '';

        if (loading) {
            loading.hidden = false;
            loading.textContent = fbT('feedback_loading', 'Loading feedback…');
        }
        if (empty) empty.hidden = true;
        if (wrap) wrap.hidden = true;

        const params = new URLSearchParams({ action: 'list' });
        if (status) params.set('status', status);
        if (type) params.set('type', type);
        if (category) params.set('category', category);

        try {
            const res = await fetch('/api/feedback.php?' + params.toString());
            const data = await res.json();
            if (data.status !== 'ok') {
                throw new Error(data.message || fbT('feedback_load_failed', 'Failed to load'));
            }

            items = data.data?.items || [];
            renderTable();
        } catch (err) {
            if (loading) {
                loading.textContent = fbT('feedback_error_prefix', 'Error:') + ' ' + err.message;
            }
        }
    }

    function renderTable() {
        const loading = document.getElementById('feedback-list-loading');
        const empty = document.getElementById('feedback-list-empty');
        const wrap = document.getElementById('feedback-table-wrap');
        const tbody = document.getElementById('feedback-table-body');

        if (loading) loading.hidden = true;

        if (!items.length) {
            if (empty) empty.hidden = false;
            if (wrap) wrap.hidden = true;
            return;
        }

        if (empty) empty.hidden = true;
        if (wrap) wrap.hidden = false;

        if (!tbody) return;

        const viewLabel = escapeHtml(fbT('feedback_view_btn', 'View'));

        tbody.innerHTML = items.map((item) => `
            <tr>
                <td>${escapeHtml(formatDate(item.created_at))}</td>
                <td>${escapeHtml(typeLabel(item.type))}</td>
                <td>${escapeHtml(categoryLabel(item.category))}</td>
                <td>${escapeHtml(item.title)}</td>
                <td>${escapeHtml(item.user_email || item.user_id || '—')}</td>
                <td>${statusBadge(item.status)}</td>
                <td><button type="button" class="btn btn-sm btn-secondary" data-feedback-id="${escapeHtml(item.id)}">${viewLabel}</button></td>
            </tr>
        `).join('');

        tbody.querySelectorAll('[data-feedback-id]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(btn.getAttribute('data-feedback-id')));
        });
    }

    function openDetail(id) {
        const item = items.find((i) => i.id === id);
        if (!item) return;

        selectedId = id;
        const modal = document.getElementById('feedback-detail-modal');
        const title = document.getElementById('feedback-detail-title');
        const body = document.getElementById('feedback-detail-body');
        const statusSelect = document.getElementById('feedback-detail-status');

        if (title) title.textContent = item.title;
        if (statusSelect) statusSelect.value = item.status || 'new';
        if (body) {
            const typeLbl = fbT('feedback_detail_type', 'Type');
            const catLbl = fbT('feedback_detail_category', 'Category');
            const fromLbl = fbT('feedback_detail_from', 'From');
            const pageLbl = fbT('feedback_detail_page', 'Page');
            const submittedLbl = fbT('feedback_detail_submitted', 'Submitted');
            const notesLbl = fbT('feedback_admin_notes', 'Internal notes');
            body.innerHTML = `
                <p><strong>${escapeHtml(typeLbl)}:</strong> ${escapeHtml(typeLabel(item.type))}</p>
                <p><strong>${escapeHtml(catLbl)}:</strong> ${escapeHtml(categoryLabel(item.category))}</p>
                <p><strong>${escapeHtml(fromLbl)}:</strong> ${escapeHtml(item.user_email || item.user_id)} (${escapeHtml(item.user_role || '')})</p>
                <p><strong>${escapeHtml(pageLbl)}:</strong> ${escapeHtml(item.page_context || '—')}</p>
                <p><strong>${escapeHtml(submittedLbl)}:</strong> ${escapeHtml(formatDate(item.created_at))}</p>
                <hr>
                <p style="white-space: pre-wrap;">${escapeHtml(item.description)}</p>
                <div class="form-group" style="margin-top: 1rem;">
                    <label for="feedback-admin-notes">${escapeHtml(notesLbl)}</label>
                    <textarea id="feedback-admin-notes" class="form-control" rows="3">${escapeHtml(item.admin_notes || '')}</textarea>
                </div>
            `;
        }
        if (modal) modal.classList.add('show');
    }

    function closeDetail() {
        const modal = document.getElementById('feedback-detail-modal');
        if (modal) modal.classList.remove('show');
        selectedId = null;
    }

    async function saveStatus() {
        if (!selectedId) return;
        const status = document.getElementById('feedback-detail-status')?.value;
        const notes = document.getElementById('feedback-admin-notes')?.value || '';

        try {
            const res = await fetch('/api/feedback.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_status',
                    id: selectedId,
                    status,
                    admin_notes: notes,
                    _csrf: csrf(),
                }),
            });
            const data = await res.json();
            if (data.status !== 'ok') {
                throw new Error(data.message || fbT('feedback_save_failed', 'Save failed'));
            }

            closeDetail();
            await loadFeedback();
        } catch (err) {
            BirklyFeedback.toast(fbT('feedback_error_prefix', 'Error:') + ' ' + err.message, 'error');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('feedback-refresh-btn')?.addEventListener('click', loadFeedback);
        document.getElementById('feedback-filter-status')?.addEventListener('change', loadFeedback);
        document.getElementById('feedback-filter-type')?.addEventListener('change', loadFeedback);
        document.getElementById('feedback-filter-category')?.addEventListener('change', loadFeedback);
        document.getElementById('feedback-save-status-btn')?.addEventListener('click', saveStatus);
        document.querySelectorAll('[data-close-feedback-modal]').forEach((el) => {
            el.addEventListener('click', closeDetail);
        });
        loadFeedback();
    });
})();
