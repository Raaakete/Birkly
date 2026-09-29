(function () {
    'use strict';

    let initialized = false;
    let redirects = [];

    function t(key, fallback, params) {
        if ((window.Birkly && window.Birkly.i18n && typeof window.Birkly.i18n.t === 'function')) {
            return window.Birkly.i18n.t('admin.' + key, fallback || key, params);
        }
        let text = fallback || key;
        if (params && typeof params === 'object') {
            Object.keys(params).forEach(function (name) {
                var value = String(params[name]);
                text = String(text).split('{{' + name + '}}').join(value);
                text = text.split('{' + name + '}').join(value);
            });
        }
        return text;
    }

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
        feedback.classList.remove('u-fg-danger', 'u-fg-success');
        if (!message) {
            feedback.hidden = true;
            feedback.textContent = '';
            return;
        }
        feedback.hidden = false;
        feedback.textContent = message;
        feedback.classList.add(tone === 'error' ? 'u-fg-danger' : 'u-fg-success');
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
            emptyState.hidden = false;
            return;
        }

        emptyState.hidden = true;
        tableBody.innerHTML = redirects.map(function (redirect) {
            return '<tr>' +
                '<td><code>' + escapeHtml(redirect.from_path || '') + '</code></td>' +
                '<td><code>' + escapeHtml(redirect.to_path || '') + '</code></td>' +
                '<td>' + escapeHtml(String(redirect.type || 301)) + '</td>' +
                '<td>' + (redirect.enabled
                    ? '<span class="badge badge-success">' + escapeHtml(t('enabled', 'Enabled')) + '</span>'
                    : '<span class="badge badge-neutral">' + escapeHtml(t('disabled', 'Disabled')) + '</span>') + '</td>' +
                '<td>' + escapeHtml(redirect.updated_at || '—') + '</td>' +
                '<td class="cell-actions">' +
                    '<button type="button" class="btn btn-secondary btn-sm redirect-edit-btn" data-id="' + escapeHtml(redirect.id || '') + '">' + escapeHtml(t('edit', 'Edit')) + '</button>' +
                    '<button type="button" class="btn btn-danger btn-sm redirect-delete-btn" data-id="' + escapeHtml(redirect.id || '') + '">' + escapeHtml(t('delete', 'Delete')) + '</button>' +
                '</td>' +
            '</tr>';
        }).join('');
    }

    async function loadRedirects() {
        try {
            const response = await fetch(apiUrl('/api/redirects.php?action=list'), { credentials: 'same-origin' });
            const payload = await response.json();
            if (!response.ok || payload.status !== 'ok') {
                throw new Error(payload.message || t('settings_redirects_load_failed', 'Failed to load redirects'));
            }
            redirects = Array.isArray(payload?.data?.redirects) ? payload.data.redirects : [];
            renderTable();
        } catch (error) {
            setFeedback(error.message || t('settings_redirects_load_failed', 'Failed to load redirects'), 'error');
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
                throw new Error(payload.message || t('settings_redirects_save_failed', 'Failed to save redirect'));
            }
            resetForm();
            setFeedback(payload.message || t('settings_redirects_saved', 'Redirect saved.'), 'success');
            await loadRedirects();
        } catch (error) {
            setFeedback(error.message || t('settings_redirects_save_failed', 'Failed to save redirect'), 'error');
        }
    }

    async function deleteRedirect(id) {
        const { idInput } = getElements();
        if (!id || !(await BirklyFeedback.confirm(t('settings_redirects_delete_confirm', 'Delete this redirect?')))) {
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
                throw new Error(payload.message || t('settings_redirects_delete_failed', 'Failed to delete redirect'));
            }
            if (idInput && idInput.value === id) {
                resetForm();
            }
            setFeedback(payload.message || t('settings_redirects_deleted', 'Redirect deleted.'), 'success');
            await loadRedirects();
        } catch (error) {
            setFeedback(error.message || t('settings_redirects_delete_failed', 'Failed to delete redirect'), 'error');
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
        setFeedback(t('settings_redirects_editing', 'Editing redirect {path}', { path: redirect.from_path || '' }), 'success');
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
        tableBody?.addEventListener('click', async function (event) {
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
