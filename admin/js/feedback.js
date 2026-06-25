/**
 * Admin feedback list — triage and status workflow
 */
(function () {
    'use strict';

    let items = [];
    let selectedId = null;
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

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

    function statusBadge(status) {
        const labels = {
            new: 'New',
            in_progress: 'In progress',
            resolved: 'Resolved',
            closed: 'Closed',
        };
        return `<span class="status-badge status-${escapeHtml(status)}">${escapeHtml(labels[status] || status)}</span>`;
    }

    function typeLabel(type) {
        const labels = { bug: 'Bug', feature: 'Feature', feedback: 'Feedback', question: 'Question' };
        return labels[type] || type;
    }

    async function loadFeedback() {
        const loading = document.getElementById('feedback-list-loading');
        const empty = document.getElementById('feedback-list-empty');
        const wrap = document.getElementById('feedback-table-wrap');
        const status = document.getElementById('feedback-filter-status')?.value || '';
        const type = document.getElementById('feedback-filter-type')?.value || '';

        if (loading) loading.style.display = 'block';
        if (empty) empty.style.display = 'none';
        if (wrap) wrap.style.display = 'none';

        const params = new URLSearchParams({ action: 'list' });
        if (status) params.set('status', status);
        if (type) params.set('type', type);

        try {
            const res = await fetch('/api/feedback.php?' + params.toString());
            const data = await res.json();
            if (data.status !== 'ok') throw new Error(data.message || 'Failed to load');

            items = data.data?.items || [];
            renderTable();
        } catch (err) {
            if (loading) loading.textContent = 'Error: ' + err.message;
        }
    }

    function renderTable() {
        const loading = document.getElementById('feedback-list-loading');
        const empty = document.getElementById('feedback-list-empty');
        const wrap = document.getElementById('feedback-table-wrap');
        const tbody = document.getElementById('feedback-table-body');

        if (loading) loading.style.display = 'none';

        if (!items.length) {
            if (empty) empty.style.display = 'block';
            if (wrap) wrap.style.display = 'none';
            return;
        }

        if (empty) empty.style.display = 'none';
        if (wrap) wrap.style.display = 'block';

        if (!tbody) return;

        tbody.innerHTML = items.map((item) => `
            <tr>
                <td>${escapeHtml(formatDate(item.created_at))}</td>
                <td>${escapeHtml(typeLabel(item.type))}</td>
                <td>${escapeHtml(item.title)}</td>
                <td>${escapeHtml(item.user_email || item.user_id || '—')}</td>
                <td>${statusBadge(item.status)}</td>
                <td><button type="button" class="btn btn-small btn-secondary" data-feedback-id="${escapeHtml(item.id)}">View</button></td>
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
            body.innerHTML = `
                <p><strong>Type:</strong> ${escapeHtml(typeLabel(item.type))}</p>
                <p><strong>From:</strong> ${escapeHtml(item.user_email || item.user_id)} (${escapeHtml(item.user_role || '')})</p>
                <p><strong>Page:</strong> ${escapeHtml(item.page_context || '—')}</p>
                <p><strong>Submitted:</strong> ${escapeHtml(formatDate(item.created_at))}</p>
                <hr>
                <p style="white-space: pre-wrap;">${escapeHtml(item.description)}</p>
                <div class="form-group" style="margin-top: 1rem;">
                    <label for="feedback-admin-notes">Internal notes</label>
                    <textarea id="feedback-admin-notes" class="form-control" rows="3">${escapeHtml(item.admin_notes || '')}</textarea>
                </div>
            `;
        }
        if (modal) modal.style.display = 'flex';
    }

    function closeDetail() {
        const modal = document.getElementById('feedback-detail-modal');
        if (modal) modal.style.display = 'none';
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
            if (data.status !== 'ok') throw new Error(data.message || 'Save failed');

            closeDetail();
            await loadFeedback();
        } catch (err) {
            alert('Error: ' + err.message);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('feedback-refresh-btn')?.addEventListener('click', loadFeedback);
        document.getElementById('feedback-filter-status')?.addEventListener('change', loadFeedback);
        document.getElementById('feedback-filter-type')?.addEventListener('change', loadFeedback);
        document.getElementById('feedback-save-status-btn')?.addEventListener('click', saveStatus);
        document.querySelectorAll('[data-close-feedback-modal]').forEach((el) => {
            el.addEventListener('click', closeDetail);
        });
        loadFeedback();
    });
})();
