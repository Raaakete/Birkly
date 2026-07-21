(function () {
    'use strict';

    let initialized = false;
    let redirects = [];

    function apiUrl(path) {
        return (window.Birkly && typeof window.Birkly.url === 'function') ? window.Birkly.url(path) : path;
    }

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = String(value ?? '');
        return element.innerHTML;
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function getElements() {
        return {
            form: document.getElementById('redirect-form'),
            idInput: document.getElementById('redirect-id'),
            fromInput: document.getElementById('redirect-from-path'),
            toInput: document.getElementById('redirect-to-path'),
            typeInput: document.getElementById('redirect-type'),
            enabledInput: document.getElementById('redirect-enabled'),
            feedback: document.getElementById('redirect-feedback'),
            emptyState: document.getElementById('redirects-empty'),
            tableBody: document.getElementById('redirects-table-body'),
            resetButton: document.getElementById('redirect-form-reset'),
        };
    }

    function setFeedback(message, tone) {
        const { feedback } = getElements();
        if (!feedback) {
            return;
        }
        if (!message) {
            feedback.style.display = 'none';
            feedback.textContent = '';
            feedback.style.color = '';
            return;
        }
        feedback.style.display = 'block';
        feedback.textContent = message;
        feedback.style.color = tone === 'error'
            ? 'var(--danger-600, #dc2626)'
            : 'var(--success-600, #16a34a)';
    }

    function resetForm() {
        const { idInput, fromInput, toInput, typeInput, enabledInput } = getElements();
        if (!idInput || !fromInput || !toInput || !typeInput || !enabledInput) {
            return;
        }
        idInput.value = '';
        fromInput.value = '';
        toInput.value = '';
        typeInput.value = '301';
        enabledInput.checked = true;
        setFeedback('');
    }

    function renderTable() {
        const { tableBody, emptyState } = getElements();
        if (!tableBody || !emptyState) {
            return;
        }
        if (!redirects.length) {
            tableBody.innerHTML = '';
            emptyState.style.display = 'block';
            return;
        }

        emptyState.style.display = 'none';
        tableBody.innerHTML = redirects.map(function (redirect) {
            return '<tr>' +
                '<td><code>' + escapeHtml(redirect.from_path || '') + '</code></td>' +
                '<td><code>' + escapeHtml(redirect.to_path || '') + '</code></td>' +
                '<td>' + escapeHtml(String(redirect.type || 301)) + '</td>' +
                '<td>' + (redirect.enabled ? '<span class="status-badge published">Enabled</span>' : '<span class="status-badge unpublished">Disabled</span>') + '</td>' +
                '<td>' + escapeHtml(redirect.updated_at || '—') + '</td>' +
                '<td>' +
                    '<div style="display:flex; gap:0.5rem; flex-wrap:wrap;">' +
                        '<button type="button" class="btn btn-secondary btn-sm redirect-edit-btn" data-id="' + escapeHtml(redirect.id || '') + '">Edit</button>' +
                        '<button type="button" class="btn btn-danger btn-sm redirect-delete-btn" data-id="' + escapeHtml(redirect.id || '') + '">Delete</button>' +
                    '</div>' +
                '</td>' +
            '</tr>';
        }).join('');
    }

    async function loadRedirects() {
        try {
            const response = await fetch(apiUrl('/api/redirects.php?action=list'), { credentials: 'same-origin' });
            const payload = await response.json();
            if (!response.ok || payload.status !== 'ok') {
                throw new Error(payload.message || 'Failed to load redirects');
            }
            redirects = Array.isArray(payload?.data?.redirects) ? payload.data.redirects : [];
            renderTable();
        } catch (error) {
            setFeedback(error.message || 'Failed to load redirects', 'error');
        }
    }

    async function saveRedirect(event) {
        event.preventDefault();
        const { idInput, fromInput, toInput, typeInput, enabledInput } = getElements();
        setFeedback('');

        try {
            const response = await fetch(apiUrl('/api/redirects.php?action=save'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    id: idInput?.value || undefined,
                    from_path: fromInput?.value,
                    to_path: toInput?.value,
                    type: Number(typeInput?.value || 301),
                    enabled: Boolean(enabledInput?.checked),
                    _csrf: csrfToken(),
                }),
            });
            const payload = await response.json();
            if (!response.ok || payload.status !== 'ok') {
                throw new Error(payload.message || 'Failed to save redirect');
            }
            resetForm();
            setFeedback(payload.message || 'Redirect saved.', 'success');
            await loadRedirects();
        } catch (error) {
            setFeedback(error.message || 'Failed to save redirect', 'error');
        }
    }

    async function deleteRedirect(id) {
        const { idInput } = getElements();
        if (!id || !window.confirm('Delete this redirect?')) {
            return;
        }

        try {
            const response = await fetch(apiUrl('/api/redirects.php?action=delete'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    id: id,
                    _csrf: csrfToken(),
                }),
            });
            const payload = await response.json();
            if (!response.ok || payload.status !== 'ok') {
                throw new Error(payload.message || 'Failed to delete redirect');
            }
            if (idInput && idInput.value === id) {
                resetForm();
            }
            setFeedback(payload.message || 'Redirect deleted.', 'success');
            await loadRedirects();
        } catch (error) {
            setFeedback(error.message || 'Failed to delete redirect', 'error');
        }
    }

    function editRedirect(id) {
        const { fromInput } = getElements();
        const redirect = redirects.find(function (item) {
            return String(item.id || '') === String(id || '');
        });
        if (!redirect) {
            return;
        }
        const { idInput, toInput, typeInput, enabledInput } = getElements();
        if (idInput) idInput.value = redirect.id || '';
        if (fromInput) fromInput.value = redirect.from_path || '';
        if (toInput) toInput.value = redirect.to_path || '';
        if (typeInput) typeInput.value = String(redirect.type || 301);
        if (enabledInput) enabledInput.checked = Boolean(redirect.enabled);
        setFeedback('Editing redirect ' + (redirect.from_path || ''), 'success');
        fromInput?.focus();
    }

    function bindEvents() {
        const { form, resetButton, tableBody } = getElements();
        if (!form || form.dataset.redirectsBound === '1') {
            return;
        }

        form.dataset.redirectsBound = '1';
        form.addEventListener('submit', saveRedirect);
        resetButton?.addEventListener('click', resetForm);
        tableBody?.addEventListener('click', function (event) {
            const editButton = event.target.closest('.redirect-edit-btn');
            if (editButton) {
                editRedirect(editButton.getAttribute('data-id') || '');
                return;
            }
            const deleteButton = event.target.closest('.redirect-delete-btn');
            if (deleteButton) {
                deleteRedirect(deleteButton.getAttribute('data-id') || '');
            }
        });
    }

    window.initRedirectsManager = function initRedirectsManager() {
        const panel = document.getElementById('redirects');
        if (!panel || !panel.querySelector('#redirect-form')) {
            return;
        }

        bindEvents();
        loadRedirects();
        initialized = true;
    };

    if (document.getElementById('redirects')?.classList.contains('active')) {
        window.initRedirectsManager();
    }

    window.Birkly = window.Birkly || {};
    window.Birkly.Redirects = {
        init: window.initRedirectsManager,
        isInitialized: function () {
            return initialized;
        },
    };
})();
