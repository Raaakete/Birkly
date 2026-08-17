/**
 * Reusable email connection picker for admin forms.
 */
(function (global) {
    'use strict';

    var cache = null;
    var cacheAt = 0;
    var CACHE_MS = 30000;

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

    async function loadPickerData(force) {
        if (!force && cache && (Date.now() - cacheAt) < CACHE_MS) {
            return cache;
        }
        var res = await fetch(apiUrl('/api/connections.php?action=email_picker'));
        var data = await res.json();
        if (data.status !== 'ok') {
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

  /**
   * @param {HTMLElement} mountEl
   * @param {{ name: string, value?: string, id?: string, help?: string }} options
   */
    async function mount(mountEl, options) {
        if (!mountEl) return;
        options = options || {};
        var selected = options.value || '';
        var fieldName = options.name || 'email_connection_alias';
        var fieldId = options.id || ('email-conn-picker-' + Math.random().toString(36).slice(2, 9));

        mountEl.innerHTML = '<div class="loading-state"><p>Loading email connections…</p></div>';

        try {
            var data = await loadPickerData(false);
            var connections = data.connections || [];
            var defaultAlias = data.default_alias || '';
            if (!selected && defaultAlias) {
                selected = defaultAlias;
            }

            var html = '<div class="form-group sys-conn-picker-field">';
            html += '<label for="' + escapeHtml(fieldId) + '">Send using</label>';

            if (!data.has_connections) {
                html += '<select id="' + escapeHtml(fieldId) + '" class="input" disabled>';
                html += '<option value="">No email connection set up yet</option>';
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
            mountEl.innerHTML = html;
        } catch (err) {
            mountEl.innerHTML = '<p class="message message-error">Could not load email connections. Refresh the page or open Settings → Connections.</p>';
        }
    }

    global.BirklyEmailConnectionPicker = {
        mount: mount,
        refreshAll: function () {
            cache = null;
            document.querySelectorAll('[data-email-connection-picker]').forEach(function (el) {
                mount(el, {
                    name: el.getAttribute('data-picker-name') || '',
                    value: el.getAttribute('data-picker-value') || '',
                    id: el.getAttribute('data-picker-id') || '',
                    help: el.getAttribute('data-picker-help') || '',
                });
            });
        },
        loadPickerData: loadPickerData,
        settingsConnectionsUrl: settingsConnectionsUrl,
    };
})(window);
