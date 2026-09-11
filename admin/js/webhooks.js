/**
 * Webhooks management (settings tab + standalone page)
 */
(function() {
    'use strict';

    let initialized = false;
    let collections = [];
    let webhooks = [];

    function apiUrl(path) {
        if (typeof Birkly !== 'undefined' && typeof Birkly.url === 'function') {
            return Birkly.url(path);
        }
        return path;
    }

    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function t(key, fallback, params) {
        var text = (window.Birkly && window.Birkly.i18n)
            ? window.Birkly.i18n.t('admin.' + key, fallback || key)
            : (fallback || key);
        if (params && typeof params === 'object') {
            Object.keys(params).forEach(function (name) {
                text = text.split('{' + name + '}').join(String(params[name]));
            });
        }
        return text;
    }

    function isWebhooksContext() {
        return !!document.getElementById('webhooks-list');
    }

    window.initWebhooksManager = function initWebhooksManager(data) {
        if (!isWebhooksContext()) {
            return;
        }
        if (data && Array.isArray(data.collections)) {
            collections = data.collections;
            populateCollectionOptions();
        }
        if (initialized) {
            loadWebhooks();
            return;
        }
        initialized = true;
        bindEvents();
        loadCollections().finally(loadWebhooks);
    };

    function bindEvents() {
        document.getElementById('create-webhook')?.addEventListener('click', async function () {
            openWebhookModal();
        });
        document.getElementById('save-webhook')?.addEventListener('click', saveWebhook);
        document.getElementById('test-webhook')?.addEventListener('click', testWebhook);

        const tbody = document.getElementById('webhooks-list');
        if (tbody && !tbody.dataset.actionsBound) {
            tbody.dataset.actionsBound = '1';
            tbody.addEventListener('click', async function (e) {
                const editBtn = e.target.closest('[data-edit-webhook]');
                if (editBtn) {
                    e.preventDefault();
                    openWebhookModal(editBtn.getAttribute('data-edit-webhook'));
                    return;
                }
                const deleteBtn = e.target.closest('[data-delete-webhook]');
                if (deleteBtn) {
                    e.preventDefault();
                    deleteWebhook(deleteBtn.getAttribute('data-delete-webhook'));
                }
            });
        }
    }

    async function loadCollections() {
        if (collections.length) {
            populateCollectionOptions();
            return;
        }
        if (window.webhooksData && Array.isArray(window.webhooksData.collections) && window.webhooksData.collections.length) {
            collections = window.webhooksData.collections;
            populateCollectionOptions();
            return;
        }
        try {
            const response = await fetch(apiUrl('/api/content.php?action=list_collections'));
            const result = await response.json();
            if (result.status === 'ok' && Array.isArray(result.collections)) {
                collections = result.collections.map(function(item) {
                    return {
                        slug: item.slug || item.id || item.name,
                        title: item.name || item.title || item.slug
                    };
                });
            }
        } catch (error) {
            console.error('Failed to load collections for webhooks:', error);
        }
        populateCollectionOptions();
    }

    function populateCollectionOptions() {
        const select = document.getElementById('webhook-collections');
        if (!select) return;
        const selected = Array.from(select.selectedOptions).map(function(opt) { return opt.value; });
        select.innerHTML = '<option value="*">' + escapeHtml(t('webhooks_all_collections', 'All collections')) + '</option>';
        collections.forEach(function(collection) {
            const option = document.createElement('option');
            option.value = collection.slug;
            option.textContent = collection.title || collection.slug;
            select.appendChild(option);
        });
        Array.from(select.options).forEach(function(option) {
            if (selected.includes(option.value)) {
                option.selected = true;
            }
        });
    }

    async function loadWebhooks() {
        const tbody = document.getElementById('webhooks-list');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="6"><div class="loading-state"><p>' + escapeHtml(t('webhooks_loading', 'Loading...')) + '</p></div></td></tr>';

        try {
            const response = await fetch(apiUrl('/api/webhooks.php?action=list'));
            const result = await response.json();
            webhooks = (result.status === 'ok' && Array.isArray(result.webhooks)) ? result.webhooks : [];
            renderWebhooks();
        } catch (error) {
            tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state empty-state--compact empty-state--error" role="alert"><p class="empty-state__description">' + escapeHtml(t('webhooks_load_failed', 'Failed to load webhooks.')) + '</p></div></td></tr>';
        }
    }

    function renderWebhooks() {
        const tbody = document.getElementById('webhooks-list');
        if (!tbody) return;

        if (!webhooks.length) {
            tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state empty-state--compact"><p class="empty-state__description">' + escapeHtml(t('webhooks_empty', 'No webhooks configured yet.')) + '</p></div></td></tr>';
            return;
        }

        tbody.innerHTML = webhooks.map(function(webhook) {
            const events = (webhook.events || []).join(', ') || '—';
            const cols = (webhook.collections || []).join(', ') || '—';
            const status = webhook.active
                ? '<span class="badge badge-success">' + escapeHtml(t('webhooks_status_active', 'Active')) + '</span>'
                : '<span class="badge badge-neutral">' + escapeHtml(t('webhooks_status_inactive', 'Inactive')) + '</span>';
            const lastTriggered = webhook.last_triggered ? escapeHtml(webhook.last_triggered) : '—';

            return '<tr>' +
                '<td><code>' + escapeHtml(webhook.url || '') + '</code></td>' +
                '<td>' + escapeHtml(events) + '</td>' +
                '<td>' + escapeHtml(cols) + '</td>' +
                '<td>' + status + '</td>' +
                '<td>' + lastTriggered + '</td>' +
                '<td class="cell-actions">' +
                    '<button type="button" class="btn btn-sm btn-secondary" data-edit-webhook="' + escapeHtml(webhook.id) + '">' + escapeHtml(t('webhooks_btn_edit', 'Edit')) + '</button> ' +
                    '<button type="button" class="btn btn-sm btn-danger" data-delete-webhook="' + escapeHtml(webhook.id) + '">' + escapeHtml(t('webhooks_btn_delete', 'Delete')) + '</button>' +
                '</td>' +
            '</tr>';
        }).join('');
    }

    function openWebhookModal(webhookId) {
        const modal = document.getElementById('webhook-modal');
        const form = document.getElementById('webhook-form');
        const title = document.getElementById('webhook-modal-title');
        if (!modal || !form) return;

        form.reset();
        document.getElementById('webhook-id').value = '';
        document.getElementById('webhook-active').checked = true;
        populateCollectionOptions();

        if (webhookId) {
            const webhook = webhooks.find(function(item) { return item.id === webhookId; });
            if (!webhook) return;
            if (title) title.textContent = t('webhooks_modal_edit_title', 'Edit webhook');
            document.getElementById('webhook-id').value = webhook.id;
            document.getElementById('webhook-url').value = webhook.url || '';
            document.getElementById('webhook-active').checked = !!webhook.active;

            form.querySelectorAll('input[name="events[]"]').forEach(function(input) {
                input.checked = (webhook.events || []).includes(input.value) || (webhook.events || []).includes('*');
            });

            const select = document.getElementById('webhook-collections');
            if (select) {
                const selected = webhook.collections || ['*'];
                Array.from(select.options).forEach(function(option) {
                    option.selected = selected.includes(option.value);
                });
            }
        } else if (title) {
            title.textContent = t('webhooks_modal_create_title', 'Create webhook');
        }

        modal.style.display = 'flex';
    }

    window.closeWebhookModal = function closeWebhookModal() {
        const modal = document.getElementById('webhook-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    };

    function readWebhookForm() {
        const form = document.getElementById('webhook-form');
        const events = [];
        form.querySelectorAll('input[name="events[]"]:checked').forEach(function(input) {
            events.push(input.value);
        });

        const collectionsSelect = document.getElementById('webhook-collections');
        const selectedCollections = collectionsSelect
            ? Array.from(collectionsSelect.selectedOptions).map(function(opt) { return opt.value; })
            : ['*'];

        return {
            id: document.getElementById('webhook-id').value || undefined,
            url: document.getElementById('webhook-url').value.trim(),
            events: events.length ? events : ['*'],
            collections: selectedCollections.length ? selectedCollections : ['*'],
            secret: document.getElementById('webhook-secret').value.trim(),
            active: document.getElementById('webhook-active').checked,
            _csrf: getCsrfToken()
        };
    }

    async function saveWebhook() {
        const payload = readWebhookForm();
        const isEdit = !!payload.id;
        const action = isEdit ? 'update' : 'create';

        try {
            const response = await fetch(apiUrl('/api/webhooks.php?action=' + action), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await response.json();
            if (result.status === 'ok') {
                closeWebhookModal();
                loadWebhooks();
            } else {
                BirklyFeedback.toast(result.message || t('webhooks_save_failed', 'Failed to save webhook'), 'error')
            }
        } catch (error) {
            BirklyFeedback.toast(t('webhooks_save_error', 'Failed to save webhook: {message}', { message: error.message }), 'error')
        }
    }

    async function deleteWebhook(webhookId) {
        if (!webhookId || !(await BirklyFeedback.confirm(t('webhooks_delete_confirm', 'Delete this webhook?')))) {
            return;
        }
        try {
            const response = await fetch(apiUrl('/api/webhooks.php?action=delete'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: webhookId, _csrf: getCsrfToken() })
            });
            const result = await response.json();
            if (result.status === 'ok') {
                loadWebhooks();
            } else {
                BirklyFeedback.toast(result.message || t('webhooks_delete_failed', 'Failed to delete webhook'), 'error')
            }
        } catch (error) {
            BirklyFeedback.toast(t('webhooks_delete_error', 'Failed to delete webhook: {message}', { message: error.message }), 'error')
        }
    }

    async function testWebhook() {
        const payload = readWebhookForm();
        if (!payload.id) {
            BirklyFeedback.toast(t('webhooks_test_save_first', 'Save the webhook before testing.'), 'error')
            return;
        }
        try {
            const response = await fetch(apiUrl('/api/webhooks.php?action=test'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: payload.id, _csrf: getCsrfToken() })
            });
            const result = await response.json();
            if (result.status === 'ok') {
                BirklyFeedback.toast(t('webhooks_test_success', 'Test webhook sent successfully.'), 'success')
            } else {
                BirklyFeedback.toast(result.message || t('webhooks_test_failed', 'Webhook test failed'), 'error')
            }
        } catch (error) {
            BirklyFeedback.toast(t('webhooks_test_error', 'Webhook test failed: {message}', { message: error.message }), 'error')
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (isWebhooksContext()) {
            const data = window.webhooksData || {};
            initWebhooksManager(data);
        }
    });
})();
