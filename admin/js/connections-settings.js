/**
 * Settings → Connections UI.
 */

(function () {
    'use strict';

    function apiUrl(path) {
        return (window.Birkly && typeof Birkly.url === 'function') ? Birkly.url(path) : path;
    }

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : (window.csrfToken || '');
    }

    function escapeHtml(text) {
        if (text == null) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    function showToast(message, type) {
        if (window.BirklyFeedback && typeof BirklyFeedback.toast === 'function') {
            BirklyFeedback.toast(message, type === 'success' ? 'success' : 'error');
        }
    }

    let providers = {};
    let connections = [];

    const root = document.getElementById('connections-settings-root');
    if (!root) return;

    const listEl = document.getElementById('connections-list');
    const modal = document.getElementById('connection-modal');
    const form = document.getElementById('connection-form');
    const providerSelect = document.getElementById('connection-provider');
    const providerFields = document.getElementById('connection-provider-fields');
    const formError = document.getElementById('connection-form-error');
    const modalTitle = document.getElementById('connection-modal-title');

    async function api(action, method, body) {
        const url = apiUrl('/api/connections.php?action=' + encodeURIComponent(action));
        const opts = {
            method: method,
            headers: { 'Content-Type': 'application/json' },
        };
        if (body) {
            opts.body = JSON.stringify(Object.assign({}, body, { _csrf: getCsrfToken() }));
        }
        const res = await fetch(url, opts);
        return await res.json();
    }

    async function loadProviders() {
        const result = await api('providers', 'GET');
        if (result.status === 'ok') {
            providers = result.providers || {};
            providerSelect.innerHTML = '<option value="">Select a provider…</option>' +
                Object.entries(providers).map(([key, p]) => '<option value="' + escapeHtml(key) + '">' + escapeHtml(p.label) + '</option>').join('');
        }
    }

    async function loadConnections() {
        const result = await api('list', 'GET');
        if (result.status !== 'ok') {
            listEl.innerHTML = '<p class="message message-error">Failed to load connections.</p>';
            return;
        }
        connections = result.connections || [];
        renderConnections();
    }

    function renderConnections() {
        if (connections.length === 0) {
            listEl.innerHTML = '<div class="empty-state"><p>No email connections yet. Add one to start sending.</p></div>';
            return;
        }

        const rows = connections.map(function (c) {
            const providerLabel = (providers[c.provider] || {}).label || escapeHtml(c.provider);
            const defaultBadge = c.is_default ? '<span class="badge badge-accent">Default</span>' : '';
            const statusBadge = (c.enabled === false)
                ? '<span class="badge badge-neutral">Disabled</span>'
                : '<span class="badge badge-success">Enabled</span>';
            return '<div class="card card--sunken connection-card" data-id="' + escapeHtml(c.id) + '">' +
                '<div class="card-header">' +
                '<div class="u-stack-1">' +
                '<h3 class="card-title">' + escapeHtml(c.name) + ' ' + defaultBadge + '</h3>' +
                '<p class="card-description">' + escapeHtml(c.alias) + ' · ' + escapeHtml(providerLabel) + ' · ' + escapeHtml(c.from_email || '') + '</p>' +
                '</div>' +
                '<div class="u-cluster">' + statusBadge +
                '<button type="button" class="btn btn-secondary btn-sm" data-action="test" data-alias="' + escapeHtml(c.alias) + '">Test</button>' +
                '<button type="button" class="btn btn-secondary btn-sm" data-action="edit">Edit</button>' +
                (c.is_default ? '' : '<button type="button" class="btn btn-secondary btn-sm" data-action="set-default">Set default</button>') +
                '<button type="button" class="btn btn-danger btn-sm" data-action="delete">Delete</button>' +
                '</div>' +
                '</div>' +
                '</div>';
        }).join('');

        listEl.innerHTML = '<div class="u-stack-3">' + rows + '</div>';

        listEl.querySelectorAll('[data-action]').forEach(function (btn) {
            btn.addEventListener('click', handleListAction);
        });
    }

    async function handleListAction(event) {
        const btn = event.currentTarget;
        const card = btn.closest('.connection-card');
        const id = card.dataset.id;
        const action = btn.dataset.action;
        const connection = connections.find(function (c) { return c.id === id; });
        if (!connection) return;

        if (action === 'edit') {
            openEditModal(connection);
        } else if (action === 'delete') {
            if (window.BirklyFeedback && typeof BirklyFeedback.confirm === 'function') {
                const ok = await BirklyFeedback.confirm('Delete connection "' + connection.name + '"?');
                if (!ok) return;
            } else if (!confirm('Delete connection "' + connection.name + '"?')) {
                return;
            }
            const result = await api('delete', 'POST', { id: id });
            if (result.status === 'ok') {
                showToast('Connection deleted.', 'success');
                await loadConnections();
            } else {
                showToast(result.message || 'Delete failed.', 'error');
            }
        } else if (action === 'set-default') {
            const result = await api('set_default', 'POST', { id: id });
            if (result.status === 'ok') {
                showToast('Default connection updated.', 'success');
                await loadConnections();
            } else {
                showToast(result.message || 'Update failed.', 'error');
            }
        } else if (action === 'test') {
            const testEmail = prompt('Send test email to:', '');
            if (!testEmail) return;
            const result = await api('test', 'POST', { alias: connection.alias, test_email: testEmail });
            if (result.status === 'ok' && result.send_result && result.send_result.status !== 'failed') {
                showToast('Test status: ' + result.send_result.status, 'success');
            } else {
                showToast('Test failed: ' + (result.send_result && result.send_result.error ? result.send_result.error : 'unknown'), 'error');
            }
        }
    }

    function renderProviderFields(providerKey, values) {
        providerFields.innerHTML = '';
        const schema = providers[providerKey];
        if (!schema || !schema.fields) return;

        schema.fields.forEach(function (field) {
            const value = values && values[field.name] !== undefined ? values[field.name] : (field.value || '');
            const name = field.name;
            const id = name.replace(/[\[\]]/g, '-');
            const wrapper = document.createElement('div');
            wrapper.className = 'form-group';

            const label = document.createElement('label');
            label.htmlFor = id;
            label.textContent = field.label + (field.required ? ' *' : '');
            wrapper.appendChild(label);

            let input;
            if (field.type === 'select') {
                input = document.createElement('select');
                input.className = 'input';
                Object.entries(field.options || {}).forEach(function ([k, v]) {
                    const opt = document.createElement('option');
                    opt.value = k;
                    opt.textContent = v;
                    if (String(value) === k) opt.selected = true;
                    input.appendChild(opt);
                });
            } else {
                input = document.createElement('input');
                input.type = field.type === 'password' ? 'password' : (field.type === 'number' ? 'number' : 'text');
                input.className = 'input';
                input.placeholder = field.placeholder || '';
                input.value = value;
            }

            input.id = id;
            input.name = name;
            if (field.required) input.required = true;
            wrapper.appendChild(input);
            providerFields.appendChild(wrapper);
        });
    }

    function openCreateModal() {
        form.reset();
        document.getElementById('connection-id').value = '';
        document.getElementById('connection-type').value = 'email';
        providerSelect.value = '';
        providerFields.innerHTML = '';
        formError.hidden = true;
        modalTitle.textContent = 'Add email connection';
        document.getElementById('connection-submit-btn').textContent = 'Save connection';
        openModal();
    }

    function openEditModal(connection) {
        form.reset();
        document.getElementById('connection-id').value = connection.id;
        document.getElementById('connection-type').value = connection.type || 'email';
        providerSelect.value = connection.provider;
        document.getElementById('connection-alias').value = connection.alias || '';
        document.getElementById('connection-name').value = connection.name || '';
        document.getElementById('connection-from-email').value = connection.from_email || '';
        document.getElementById('connection-from-name').value = connection.from_name || '';
        document.getElementById('connection-enabled').checked = connection.enabled !== false;
        formError.hidden = true;
        modalTitle.textContent = 'Edit email connection';
        document.getElementById('connection-submit-btn').textContent = 'Update connection';
        renderProviderFields(connection.provider, {
            'config[host]': (connection.config || {}).host,
            'config[port]': (connection.config || {}).port,
            'config[encryption]': (connection.config || {}).encryption,
            'config[auth]': (connection.config || {}).auth,
            'config[region]': (connection.config || {}).region,
            'config[domain]': (connection.config || {}).domain,
        });
        openModal();
    }

    function openModal() {
        modal.classList.add('active');
        modal.style.display = 'flex';
    }

    function closeModal() {
        modal.classList.remove('active');
        modal.style.display = 'none';
    }

    function formDataToObject(form) {
        const data = { type: 'email' };
        const formData = new FormData(form);
        formData.forEach(function (value, key) {
            if (key === 'enabled') {
                data[key] = true;
                return;
            }
            if (key.indexOf('[') > 0) {
                const match = key.match(/^([^\[]+)\[(.*)\]$/);
                if (match) {
                    const group = match[1];
                    const sub = match[2];
                    if (!data[group]) data[group] = {};
                    data[group][sub] = value;
                }
            } else {
                data[key] = value;
            }
        });
        if (!('enabled' in data)) data.enabled = false;
        return data;
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        formError.hidden = true;
        const data = formDataToObject(form);
        const isEdit = !!data.id;
        const result = await api(isEdit ? 'update' : 'create', 'POST', data);
        if (result.status === 'ok') {
            closeModal();
            showToast(isEdit ? 'Connection updated.' : 'Connection created.', 'success');
            await loadConnections();
        } else {
            const messages = result.errors
                ? Object.values(result.errors).join(' ')
                : (result.message || 'Save failed.');
            formError.textContent = messages;
            formError.hidden = false;
        }
    });

    providerSelect.addEventListener('change', function () {
        renderProviderFields(providerSelect.value, {});
    });

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-connection-action]');
        if (!btn) return;
        const action = btn.dataset.connectionAction;
        if (action === 'open-create') {
            openCreateModal();
        } else if (action === 'close-modal') {
            closeModal();
        }
    });

    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });

    async function init() {
        await loadProviders();
        await loadConnections();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
