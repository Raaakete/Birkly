/**
 * Reusable email connection picker for admin forms.
 */
(function (global) {
    'use strict';

    var cache = null;
    var cacheAt = 0;
    var CACHE_MS = 30000;

    function pickerT(key, fallback) {
        if (global.Birkly && global.Birkly.i18n && typeof global.Birkly.i18n.t === 'function') {
            return global.Birkly.i18n.t('admin.' + key, fallback);
        }
        return fallback != null ? fallback : key;
    }

    function apiUrl(path) {
        return (global.Birkly && typeof global.Birkly.url === 'function') ? global.Birkly.url(path) : path;
    }

    function escapeHtml(text) {
        if (text == null) return '';
        var div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    function settingsConnectionsUrl() {
        return apiUrl('/admin/index.php?page=settings&tab=connections&section=email');
    }

    function readMountOptions(mountEl, overrides) {
        overrides = overrides || {};
        return {
            name: overrides.name || mountEl.getAttribute('data-picker-name') || 'email_connection_alias',
            value: overrides.value != null ? overrides.value : (mountEl.getAttribute('data-picker-value') || ''),
            id: overrides.id || mountEl.getAttribute('data-picker-id') || ('email-conn-picker-' + Math.random().toString(36).slice(2, 9)),
            help: overrides.help || mountEl.getAttribute('data-picker-help') || '',
        };
    }

    async function loadPickerData(force) {
        if (!force && cache && (Date.now() - cacheAt) < CACHE_MS) {
            return cache;
        }
        var res = await fetch(apiUrl('/api/connections.php?action=email_picker'), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        });
        var data = await res.json();
        if (!res.ok || data.status !== 'ok') {
            throw new Error(data.message || 'Failed to load email connections.');
        }
        cache = data;
        cacheAt = Date.now();
        return data;
    }

    function providerLabel(provider) {
        var labels = {
            log: 'Log only',
            smtp: 'SMTP',
            resend: 'Resend',
            postmark: 'Postmark',
            ses: 'Amazon SES',
            mailgun: 'Mailgun',
            mail: 'PHP mail()',
        };
        return labels[provider] || provider;
    }

    function renderPickerHtml(options, data) {
        var connections = data.connections || [];
        var defaultAlias = data.default_alias || '';
        var selected = options.value || '';
        if (!selected && defaultAlias) {
            selected = defaultAlias;
        }

        var fieldName = options.name || 'email_connection_alias';
        var fieldId = options.id;
        var html = '<div class="form-group sys-conn-picker-field">';
        html += '<label for="' + escapeHtml(fieldId) + '">Send using</label>';

        if (!data.has_connections) {
            html += '<select id="' + escapeHtml(fieldId) + '" class="input" disabled>';
            html += '<option value="">' + escapeHtml(pickerT('email_picker_no_connection', 'No email connection set up yet')) + '</option>';
            html += '</select>';
            html += '<p class="form-help">Set up email in <a href="' + escapeHtml(settingsConnectionsUrl()) + '">Settings → Connections</a> before notifications can be sent.</p>';
        } else {
            html += '<select id="' + escapeHtml(fieldId) + '" name="' + escapeHtml(fieldName) + '" class="input">';
            connections.forEach(function (conn) {
                var label = conn.name || conn.alias;
                var meta = providerLabel(conn.provider);
                if (conn.from_email) meta += ' · ' + conn.from_email;
                if (conn.is_default) meta += ' (default)';
                var isSelected = selected === conn.alias;
                html += '<option value="' + escapeHtml(conn.alias) + '"' + (isSelected ? ' selected' : '') + '>' +
                    escapeHtml(label) + ' — ' + escapeHtml(meta) + '</option>';
            });
            html += '</select>';
            html += '<p class="form-help">' + escapeHtml(options.help || 'Choose which email connection delivers these notifications. The site default is pre-selected.') + '</p>';
        }

        html += '</div>';
        return html;
    }

    /**
     * @param {HTMLElement} mountEl
     * @param {{ name?: string, value?: string, id?: string, help?: string, onChange?: function }} [options]
     */
    async function mount(mountEl, options) {
        if (!mountEl) return null;
        options = readMountOptions(mountEl, options || {});

        mountEl.innerHTML = '<div class="loading-state"><p>Loading email connections…</p></div>';
        mountEl.setAttribute('data-picker-mounted', '1');

        try {
            var data = await loadPickerData(false);
            mountEl.innerHTML = renderPickerHtml(options, data);
            var select = mountEl.querySelector('select');
            if (select && typeof options.onChange === 'function') {
                select.addEventListener('change', function () {
                    options.onChange(select.value, select);
                });
            }
            return select;
        } catch (err) {
            mountEl.innerHTML = '<div class="form-group sys-conn-picker-field">' +
                '<label>Send using</label>' +
                '<p class="message message-error">Could not load email connections. ' +
                '<a href="' + escapeHtml(settingsConnectionsUrl()) + '">Open Settings → Connections</a> or refresh the page.</p>' +
                '</div>';
            return null;
        }
    }

    function mountAll(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var nodes = scope.querySelectorAll ? scope.querySelectorAll('[data-email-connection-picker]') : [];
        nodes.forEach(function (el) {
            mount(el, readMountOptions(el, {}));
        });
    }

    global.BirklyEmailConnectionPicker = {
        mount: mount,
        mountAll: mountAll,
        refreshAll: function () {
            cache = null;
            mountAll(document);
        },
        loadPickerData: loadPickerData,
        settingsConnectionsUrl: settingsConnectionsUrl,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            mountAll(document);
        });
    } else {
        mountAll(document);
    }
})(window);
